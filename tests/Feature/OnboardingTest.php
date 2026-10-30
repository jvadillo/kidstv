<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\Family;
use App\Models\User;
use App\Models\VideoList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private VideoList $bluey;

    private VideoList $pocoyo;

    private VideoList $private;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = $this->adult(['is_admin' => true]);
        $this->bluey = $this->publicList($admin->family, 'Bluey (Español)', 'es', 3, 6, 'Bluey: el parque');
        $this->pocoyo = $this->publicList($admin->family, 'Pocoyó (Español)', 'es', 0, 3, 'Pocoyó: la fiesta');
        $this->publicList($admin->family, 'Bluey (English)', 'en', 3, 6, 'Bluey: the park');
        $this->private = $admin->family->lists()->create(['name' => 'Privada', 'languages' => ['es']]);
        $this->youtubeContent($admin->family, ['title' => 'Vídeo privado', 'list_id' => $this->private->id]);
    }

    private function publicList(Family $family, string $name, string $language, int $min, int $max, string $video): VideoList
    {
        $list = $family->lists()->create(['name' => $name, 'is_public' => true, 'languages' => [$language], 'age_min' => $min, 'age_max' => $max]);
        $this->youtubeContent($family, ['title' => $video, 'youtube_id' => substr(md5($name), 0, 11), 'list_id' => $list->id, 'duration' => 1500]);

        return $list;
    }

    /** Los tres primeros pasos: para quién, qué ver y la vista previa con la decisión final. */
    private function prepareTv(string $name, string $age, array $lists, string $next): void
    {
        $this->post('/empezar', ['name' => $name])->assertRedirect('/empezar/edad');
        $this->post('/empezar/edad', ['age' => $age])->assertRedirect('/empezar/series');
        $this->post('/empezar/series', ['lists' => array_map(fn (VideoList $list) => $list->id, $lists)])->assertRedirect('/empezar/tele');
        $this->get('/empezar/tele')->assertOk()->assertSee('Esta es la tele de '.$name);
        $this->post('/empezar/tele', ['next' => $next]);
    }

    private function requestCode(string $email): VerificationCodeMail
    {
        $this->post('/entrar', ['email' => $email, 'onboarding' => '1', 'terms' => '1', 'form_token' => Crypt::encryptString((string) now()->subSeconds(5)->timestamp)])
            ->assertRedirect('/empezar/codigo');

        $sent = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use ($email, &$sent) {
            if ($mail->hasTo($email)) {
                $sent = $mail;
            }

            return true;
        });

        return $sent;
    }

    public function test_guest_prepares_the_tv_without_an_account_and_saves_it_with_the_email_code(): void
    {
        Mail::fake();

        $this->get('/')->assertOk()->assertSee(route('onboarding.profile'));
        $this->get('/empezar')->assertOk()->assertSee('¿Para quién es la tele?')->assertDontSee('3–6 años');
        $this->post('/empezar', [])->assertSessionHasErrors('name');
        $this->post('/empezar', ['name' => 'Nora'])->assertRedirect('/empezar/edad');
        $this->get('/empezar/edad')->assertOk()->assertSee('3–6 años');
        $this->post('/empezar/edad', [])->assertSessionHasErrors('age');
        $this->post('/empezar/edad', ['age' => '5-9'])->assertSessionHasErrors('age');
        $this->post('/empezar/edad', ['age' => '3-6'])->assertRedirect('/empezar/series');

        // El catálogo sale entero, con su edad y el idioma de la plataforma ya filtrados.
        $catalog = $this->get('/empezar/series')->assertOk()
            ->assertSee('¿Qué le gusta ver?')
            ->assertSee('Bluey')->assertSee('Pocoyó')->assertDontSee('Privada')
            ->assertSee('Más adelante podrás crear tus listas y subir videos')
            ->getContent();
        $this->assertMatchesRegularExpression('/data-ob-filter="edad" data-value="3-6"\s+aria-current="true"/', $catalog);
        $this->assertMatchesRegularExpression('/data-ob-filter="idioma" data-value="es"\s+aria-current="true"/', $catalog);
        // Pocoyó (0–3 años) está, pero oculto hasta cambiar el filtro de edad.
        $this->assertMatchesRegularExpression('/data-ages="0-3" data-langs="es"\s+hidden/', $catalog);

        $this->post('/empezar/series', ['lists' => []])->assertSessionHasErrors('lists');
        $this->post('/empezar/series', ['lists' => [$this->private->id]])->assertSessionHasErrors('lists.0');
        $this->post('/empezar/series', ['lists' => [$this->bluey->id, $this->pocoyo->id]])->assertRedirect('/empezar/tele');

        $this->get('/empezar/tele')->assertOk()
            ->assertSee('Esta es la tele de Nora')->assertSee('Bluey (Español)')->assertSee('Bluey: el parque')->assertSee('25:00')
            ->assertSee('Conectar mi tele')->assertSee('Lo haré más tarde');
        $this->assertGuest();
        $this->assertDatabaseMissing('profiles', ['name' => 'Nora']);

        $this->post('/empezar/tele', ['next' => 'connect'])->assertRedirect('/empezar/cuenta');
        $this->get('/empezar/cuenta')->assertOk()->assertSee('Último paso antes de conectarla')->assertSee('La tele de Nora');

        // Las condiciones se aceptan en la misma pantalla del correo.
        $this->post('/entrar', ['email' => 'nueva@example.com', 'onboarding' => '1', 'form_token' => Crypt::encryptString((string) now()->subSeconds(5)->timestamp)])
            ->assertSessionHasErrors('terms');
        $mail = $this->requestCode('nueva@example.com');
        $this->get('/empezar/codigo')->assertOk()->assertSee('nueva@example.com');

        $this->post('/entrar/codigo', ['code' => $mail->code])->assertRedirect('/empezar/conectar');

        $user = User::where('email', 'nueva@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->terms_accepted_at);
        $nora = $user->family->profiles()->sole();
        $this->assertSame(['Nora', '3-6', '3–6 años'], [$nora->name, $nora->age_range, $nora->ageLabel()]);
        $this->assertContains($nora->avatar, config('nokoplay.avatars'));

        // Las listas, en el orden en que se eligieron y visibles para Nora.
        $links = $user->family->lists()->whereNotNull('source_id')->get();
        $this->assertSame([$this->bluey->id, $this->pocoyo->id], $links->pluck('source_id')->all());
        $this->assertSame([[$nora->id], [$nora->id]], $links->map(fn ($l) => $l->profiles()->pluck('profiles.id')->all())->all());

        $this->get('/empezar/conectar')->assertOk()->assertSee('Tele de Nora guardada')->assertSee('from_onboarding', false);
        // El borrador ya se ha guardado: no se vuelve a aplicar.
        $this->get('/empezar/tele')->assertRedirect('/empezar');
    }

    public function test_pairing_from_the_wizard_ends_in_the_panel(): void
    {
        $code = $this->postJson('/api/tv/pairings', ['platform' => 'webos'])->assertCreated()->json('code');
        $user = $this->adult();
        $this->actingAsAdult($user);

        $this->post('/panel/televisores/vincular', ['code' => $code, 'name' => 'Salón', 'from_onboarding' => '1'])
            ->assertRedirect('/panel')->assertSessionHas('status');
        $this->assertSame(1, $user->family->devices()->count());
    }

    public function test_existing_adult_adds_the_child_to_their_family_by_typing_the_same_email(): void
    {
        Mail::fake();
        $user = $this->adult(['email' => 'ana@example.com']);
        $ane = $this->profile($user->family, 'Ane');
        $link = $user->family->prependList(['source_id' => $this->bluey->id, 'name' => 'Bluey (Español)']);
        $link->profiles()->sync([$ane->id]);

        $this->prepareTv('Iker', '7-12', [$this->bluey], 'later');
        $this->get('/empezar/cuenta')->assertOk()->assertSee('Guarda la tele de Iker');

        $mail = $this->requestCode('ana@example.com');
        $path = parse_url($mail->link, PHP_URL_PATH);
        // También con el enlace del correo.
        $this->post($path)->assertRedirect('/panel')->assertSessionHas('status');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(['Ane', 'Iker'], $user->family->profiles()->pluck('name')->all());
        $this->assertSame(1, $user->family->lists()->where('source_id', $this->bluey->id)->count());
        $this->assertEqualsCanonicalizing(['Ane', 'Iker'], $link->profiles()->pluck('name')->all());
        $this->assertSame(2, User::count());
    }

    public function test_signed_in_family_uses_the_wizard_without_being_asked_for_the_email(): void
    {
        Mail::fake();
        $user = $this->adult();
        $this->actingAsAdult($user);

        $this->get('/empezar')->assertOk()->assertSee('Ir al panel');
        $this->prepareTv('Leo', '0-3', [$this->pocoyo], 'later');

        Mail::assertNothingSent();
        $this->assertSame('Leo', $user->family->profiles()->sole()->name);
        $this->assertTrue($user->family->lists()->where('source_id', $this->pocoyo->id)->exists());
        $this->get('/panel')->assertOk()->assertSee('Conecta tu televisión')->assertSee('Gestiona lo que ven Leo')->assertSee('0–3 años');

        // «Conectar mi tele» lleva directamente a escribir el código de la tele.
        $this->prepareTv('Mia', '3-6', [$this->bluey], 'connect');
        $this->assertSame(['Leo', 'Mia'], $user->family->profiles()->pluck('name')->all());
    }

    public function test_each_step_needs_the_previous_one(): void
    {
        $this->get('/empezar/edad')->assertRedirect('/empezar');
        $this->get('/empezar/series')->assertRedirect('/empezar');
        $this->get('/empezar/tele')->assertRedirect('/empezar');
        $this->get('/empezar/cuenta')->assertRedirect('/empezar');
        $this->get('/empezar/codigo')->assertRedirect('/empezar/cuenta');
        $this->get('/empezar/conectar')->assertRedirect('/entrar');

        $this->post('/empezar', ['name' => 'Nora']);
        $this->get('/empezar/series')->assertRedirect('/empezar/edad');
        $this->post('/empezar/edad', ['age' => '3-6']);
        $this->get('/empezar/tele')->assertRedirect('/empezar/series');
        $this->post('/empezar/tele', ['next' => 'otra'])->assertSessionHasErrors('next');
    }

    public function test_age_stages_match_lists_without_touching_ends(): void
    {
        $list = new VideoList(['age_min' => 0, 'age_max' => 3]);
        $this->assertTrue($list->suitsAges(...VideoList::AGE_RANGES['0-3']));
        $this->assertFalse($list->suitsAges(...VideoList::AGE_RANGES['3-6']));

        $all = new VideoList(['age_min' => 0, 'age_max' => 12]);
        $this->assertTrue(collect(VideoList::AGE_RANGES)->every(fn ($range) => $all->suitsAges(...$range)));

        // Una lista de dos años seguidos en el límite sale en las dos etapas.
        $border = new VideoList(['age_min' => 6, 'age_max' => 7]);
        $this->assertTrue($border->suitsAges(...VideoList::AGE_RANGES['3-6']));
        $this->assertTrue($border->suitsAges(...VideoList::AGE_RANGES['7-12']));
    }
}
