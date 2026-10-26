<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_the_pin_requires_revalidating_email(): void
    {
        Mail::fake();
        $user = $this->adult(['email' => 'ana@example.com']);
        $this->actingAsAdult($user);

        $this->get('/panel/cuenta/pin')->assertRedirect('/confirmar');
        $this->put('/panel/cuenta/pin', ['pin' => '2468', 'pin_confirmation' => '2468'])->assertRedirect('/confirmar');
        $this->assertFalse($user->family->fresh()->hasPin());

        $this->post('/confirmar/enviar')->assertRedirect('/confirmar');
        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('ana@example.com') && $mail->link === null;
        });

        $this->post('/confirmar', ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->post('/confirmar', ['code' => $code])->assertRedirect('/panel/cuenta/pin');

        $this->put('/panel/cuenta/pin', ['pin' => '12', 'pin_confirmation' => '12'])->assertSessionHasErrors('pin');
        $this->put('/panel/cuenta/pin', ['pin' => '2468', 'pin_confirmation' => '8642'])->assertSessionHasErrors('pin');
        $this->put('/panel/cuenta/pin', ['pin' => '2468', 'pin_confirmation' => '2468'])->assertRedirect('/panel/cuenta');
        $this->assertTrue($user->family->fresh()->checkPin('2468'));

        // La confirmación caduca a los pocos minutos.
        $this->travel(11)->minutes();
        $this->get('/panel/cuenta/pin')->assertRedirect('/confirmar');
    }

    public function test_new_pin_clears_tv_lockouts(): void
    {
        $user = $this->adult();
        [$device] = $this->device($user->family);
        $device->forceFill(['pin_failures' => 5, 'pin_locked_until' => now()->addMinutes(10)])->save();

        $this->actingAsAdult($user, confirmed: true)->put('/panel/cuenta/pin', ['pin' => '97531', 'pin_confirmation' => '97531']);

        $this->assertSame(0, $device->fresh()->pin_failures);
        $this->assertNull($device->fresh()->pin_locked_until);
    }

    public function test_email_change_is_verified_on_the_new_address(): void
    {
        Mail::fake();
        $user = $this->adult(['email' => 'vieja@example.com']);
        $this->adult(['email' => 'ocupada@example.com']);
        $this->actingAsAdult($user, confirmed: true);

        $this->post('/panel/cuenta/correo', ['email' => 'ocupada@example.com'])->assertSessionHasErrors('email');
        $this->post('/panel/cuenta/correo', ['email' => 'Nueva@Example.com'])->assertSessionHas('status');

        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('nueva@example.com');
        });

        $this->post('/panel/cuenta/correo/confirmar', ['code' => $code])->assertRedirect('/panel/cuenta');
        $this->assertSame('nueva@example.com', $user->fresh()->email);
    }

    public function test_preferences_and_export(): void
    {
        $user = $this->adult(['email' => 'ana@example.com']);
        $this->profile($user->family, 'Ane');
        $this->youtubeContent($user->family, ['title' => 'Mi vídeo']);
        $this->actingAsAdult($user);

        $this->put('/panel/cuenta', ['name' => 'Ana', 'family_name' => 'La tele de casa', 'autoplay_next' => '1', 'youtube_controls' => 'always'])->assertSessionHas('status');
        $this->assertTrue($user->family->fresh()->autoplay_next);
        $this->assertSame('always', $user->family->fresh()->youtube_controls);

        $this->get('/panel/cuenta/exportar')->assertRedirect('/confirmar');

        $this->actingAsAdult($user, confirmed: true);
        $json = json_decode($this->get('/panel/cuenta/exportar')->assertOk()->streamedContent(), true);
        $this->assertSame('ana@example.com', $json['account']['email']);
        $this->assertSame('La tele de casa', $json['family']['name']);
        $this->assertSame('always', $json['family']['youtube_controls']);
        $this->assertSame('Ane', $json['profiles'][0]['name']);
        $this->assertSame('https://www.youtube.com/watch?v=aqz-KE-bpKQ', $json['contents'][0]['youtube_url']);
        $this->assertArrayNotHasKey('pin_hash', $json['family']);
    }

    public function test_youtube_controls_preferences_are_validated_and_isolated_per_family(): void
    {
        $user = $this->adult();
        $other = $this->adult();
        $this->actingAsAdult($user);

        $this->get('/panel/cuenta')->assertOk()->assertSee('Controles de YouTube en la tele');
        $this->assertSame('auto', $user->family->fresh()->youtube_controls);

        foreach (['always', 'hidden', 'auto'] as $mode) {
            $this->put('/panel/cuenta', ['youtube_controls' => $mode])->assertSessionHasNoErrors();
            $this->assertSame($mode, $user->family->fresh()->youtube_controls);
        }

        $this->put('/panel/cuenta', ['youtube_controls' => 'hidden'])->assertSessionHasNoErrors();
        $this->put('/panel/cuenta', ['name' => 'Ana'])->assertSessionHasNoErrors();
        $this->assertSame('hidden', $user->family->fresh()->youtube_controls);
        $this->assertSame('auto', $other->family->fresh()->youtube_controls);

        foreach (['invalid', '', ['always']] as $invalid) {
            $this->put('/panel/cuenta', ['youtube_controls' => $invalid])->assertSessionHasErrors('youtube_controls');
            $this->assertSame('hidden', $user->family->fresh()->youtube_controls);
        }
    }

    public function test_account_deletion_removes_family_profiles_content_files_and_devices(): void
    {
        Storage::fake('local');
        $user = $this->adult();
        $family = $user->family;
        $this->profile($family);
        Storage::disk('local')->put('families/'.$family->id.'/media/a.mp4', 'x');
        $family->contents()->create(['type' => 'video', 'title' => 'A', 'status' => 'ready', 'file_path' => 'families/'.$family->id.'/media/a.mp4']);
        [, $token] = $this->device($family);
        $other = $this->adult();

        $this->actingAsAdult($user, confirmed: true);
        $this->get('/panel/cuenta/eliminar')->assertOk()->assertSee('1 perfil infantil');
        $this->delete('/panel/cuenta', ['confirmation' => 'eliminar'])->assertSessionHasErrors('confirmation');
        $this->delete('/panel/cuenta', ['confirmation' => 'ELIMINAR'])->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull(User::find($user->id));
        $this->assertNull(Family::find($family->id));
        $this->assertDatabaseCount('profiles', 0);
        $this->assertDatabaseCount('contents', 0);
        $this->assertDatabaseCount('devices', 0);
        Storage::disk('local')->assertMissing('families/'.$family->id.'/media/a.mp4');
        $this->tv($token)->getJson('/api/tv/session')->assertUnauthorized();
        $this->assertNotNull(User::find($other->id));
    }

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Empezar con mi correo');
        $this->get('/privacidad')->assertOk()->assertSee('Política de privacidad');
        $this->get('/condiciones')->assertOk();
        $this->get('/ayuda/televisores')->assertOk()->assertSee('modo desarrollador');
        $this->get('/entrar')->assertOk()->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_panel_pages_render_for_a_new_family(): void
    {
        $user = $this->adult();
        $this->actingAsAdult($user);

        foreach (['/panel', '/panel/bienvenida', '/panel/perfiles', '/panel/perfiles/nuevo', '/panel/listas/sin-lista', '/panel/listas/publicas', '/panel/contenidos',
            '/panel/contenidos/nuevo/youtube', '/panel/contenidos/nuevo/video', '/panel/contenidos/nuevo/audio',
            '/panel/televisores', '/panel/televisores/vincular', '/panel/cuenta'] as $url) {
            $this->get($url)->assertOk();
        }

        $profile = $this->profile($user->family);
        $content = $this->youtubeContent($user->family);
        $this->get('/panel/bienvenida')->assertOk()->assertSee('¡Todo listo para probar!');
        $this->get('/panel/perfiles/'.$profile->id)->assertOk();
        $this->get('/panel/contenidos/'.$content->id)->assertOk()->assertSee('youtube-nocookie.com/embed/aqz-KE-bpKQ', false);
    }
}
