<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private function formToken(): string
    {
        return Crypt::encryptString((string) now()->subSeconds(5)->timestamp);
    }

    private function requestCode(string $email): VerificationCodeMail
    {
        $this->post('/entrar', ['email' => $email, 'form_token' => $this->formToken()])
            ->assertRedirect('/entrar/codigo');

        $sent = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use ($email, &$sent) {
            if ($mail->hasTo(strtolower($email))) {
                $sent = $mail;
            }

            return true;
        });

        return $sent;
    }

    private function resetClient(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    public function test_new_adult_registers_with_email_code_without_password(): void
    {
        Mail::fake();

        $mail = $this->requestCode('Nueva@Example.com');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $mail->code);
        $this->assertNotNull($mail->link);

        $this->post('/entrar/codigo', ['code' => $mail->code])->assertRedirect('/alta');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nueva@example.com']);

        $this->post('/alta', ['name' => 'Laura'])->assertSessionHasErrors('terms');

        $this->post('/alta', ['name' => 'Laura', 'terms' => '1'])->assertRedirect(route('welcome'));

        $user = User::where('email', 'nueva@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertCount(count(config('kidstv.default_lists')), $user->family->lists);
        $this->get('/panel')->assertOk()->assertSee('Añadir vídeo de YouTube');
    }

    public function test_existing_adult_logs_in_with_single_use_link(): void
    {
        Mail::fake();
        $user = $this->adult(['email' => 'ana@example.com']);

        $mail = $this->requestCode('ana@example.com');
        $path = parse_url($mail->link, PHP_URL_PATH);

        // La página del enlace no consume el token (protección frente a escáneres de correo).
        $this->get($path)->assertOk()->assertSee('ana@example.com');
        $this->post($path)->assertRedirect('/panel');
        $this->assertAuthenticatedAs($user);

        $this->resetClient();
        $this->post($path)->assertStatus(410);
        $this->get($path)->assertStatus(410)->assertSee('Este enlace ya no sirve');
    }

    public function test_wrong_codes_are_limited_and_explained(): void
    {
        Mail::fake();
        $this->adult(['email' => 'ana@example.com']);
        $mail = $this->requestCode('ana@example.com');
        $wrong = $mail->code === '111111' ? '222222' : '111111';

        $this->post('/entrar/codigo', ['code' => $wrong])
            ->assertSessionHasErrors(['code' => 'El código no es correcto. Te quedan 4 intentos.']);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/entrar/codigo', ['code' => $wrong]);
        }

        // Ni siquiera el código correcto sirve tras agotar los intentos.
        $this->post('/entrar/codigo', ['code' => $mail->code])
            ->assertSessionHasErrors(['code' => 'Has superado el número de intentos. Solicita un código nuevo.']);
        $this->assertGuest();
    }

    public function test_expired_code_is_rejected(): void
    {
        Mail::fake();
        $this->adult(['email' => 'ana@example.com']);
        $mail = $this->requestCode('ana@example.com');

        $this->travel(16)->minutes();

        $this->post('/entrar/codigo', ['code' => $mail->code])
            ->assertSessionHasErrors(['code' => 'El código ha caducado. Solicita uno nuevo.']);
    }

    public function test_requesting_a_new_code_invalidates_the_previous_one(): void
    {
        Mail::fake();
        $this->adult(['email' => 'ana@example.com']);
        $first = $this->requestCode('ana@example.com');

        $this->travel(2)->minutes();
        $this->post('/entrar/reenviar')->assertSessionHas('status');

        $this->post('/entrar/codigo', ['code' => $first->code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_invalid_email_and_bots_are_handled(): void
    {
        Mail::fake();

        $this->post('/entrar', ['email' => 'no-es-un-correo', 'form_token' => $this->formToken()])
            ->assertSessionHasErrors('email');

        // Campo trampa relleno: se responde igual pero no se envía nada.
        $this->post('/entrar', ['email' => 'bot@example.com', 'website' => 'http://spam', 'form_token' => $this->formToken()])
            ->assertRedirect('/entrar/codigo');
        // Formulario enviado en menos de 2 segundos.
        $this->post('/entrar', ['email' => 'bot2@example.com', 'form_token' => Crypt::encryptString((string) now()->timestamp)])
            ->assertRedirect('/entrar/codigo');

        Mail::assertNothingSent();
    }

    public function test_login_requests_are_rate_limited_per_email(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/entrar', ['email' => 'ana@example.com', 'form_token' => $this->formToken()]);
        }

        $this->post('/entrar', ['email' => 'ana@example.com', 'form_token' => $this->formToken()])
            ->assertSessionHasErrors('email');
    }

    public function test_persistent_session_survives_a_new_browser_session_until_revoked(): void
    {
        Mail::fake();
        $user = $this->adult(['email' => 'ana@example.com']);
        $mail = $this->requestCode('ana@example.com');

        $response = $this->post('/entrar/codigo', ['code' => $mail->code]);
        $plain = (string) $response->getCookie(config('kidstv.auth_cookie'))?->getValue();
        // getCookie() descifra pero puede conservar el prefijo de integridad «hash|valor».
        $plain = str_contains($plain, '|') ? substr($plain, strrpos($plain, '|') + 1) : $plain;
        $this->assertSame(64, strlen($plain));

        // «Al día siguiente», sin sesión de Laravel pero con la cookie persistente.
        $this->resetClient();
        $this->travel(1)->day();
        $this->withCookie(config('kidstv.auth_cookie'), $plain)->get('/panel')->assertOk();
        $this->assertAuthenticatedAs($user);

        // Tras revocar la sesión desde otro dispositivo, la cookie deja de servir.
        AuthSession::query()->delete();
        $this->resetClient();
        $this->withCookie(config('kidstv.auth_cookie'), $plain)->get('/panel')->assertRedirect('/entrar');
    }

    public function test_revoking_current_session_logs_out_and_others_can_be_revoked(): void
    {
        $user = $this->adult();
        $other = $user->authSessions()->create(['token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDay()]);

        $this->actingAsAdult($user)->get('/panel/cuenta')->assertOk()->assertSee('Sesiones abiertas');

        $this->delete('/panel/cuenta/sesiones')->assertSessionHas('status');
        $this->assertModelMissing($other);

        $current = $user->authSessions()->first();
        $this->delete('/panel/cuenta/sesiones/'.$current->id)->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $user = $this->adult();
        $this->actingAsAdult($user)->post('/salir')->assertRedirect('/');
        $this->assertGuest();
        $this->assertSame(0, $user->authSessions()->count());
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/panel')->assertRedirect('/entrar');
        $this->get('/panel/contenidos')->assertRedirect('/entrar');
    }
}
