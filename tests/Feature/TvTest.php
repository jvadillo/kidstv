<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\DevicePairing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TvTest extends TestCase
{
    use RefreshDatabase;

    public function test_tv_pairs_with_temporary_code_authorized_from_the_panel(): void
    {
        $user = $this->adult();

        $pairing = $this->postJson('/api/tv/pairings', ['platform' => 'webos'])->assertCreated()->json();
        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{6}$/', $pairing['code']);
        $this->get($pairing['qr_url'])->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        $poll = fn () => $this->getJson('/api/tv/pairings/'.$pairing['id'], ['X-Pairing-Secret' => $pairing['secret']]);
        $poll()->assertOk()->assertJson(['status' => 'pending']);
        $this->getJson('/api/tv/pairings/'.$pairing['id'], ['X-Pairing-Secret' => 'incorrecto'])->assertNotFound();

        // El QR lleva al formulario con el código ya escrito.
        $this->actingAsAdult($user)->get('/tv/vincular?code='.$pairing['code'])->assertRedirect('/panel/televisores/vincular?code='.$pairing['code']);

        $this->post('/panel/televisores/vincular', ['code' => strtolower(substr($pairing['code'], 0, 3).' '.substr($pairing['code'], 3)), 'name' => 'Tele del salón'])
            ->assertRedirect('/panel/televisores');

        $token = $poll()->assertOk()->assertJson(['status' => 'paired', 'device' => ['name' => 'Tele del salón']])->json('token');
        $this->assertNotEmpty($token);

        // El token se entrega una sola vez y el código no se puede reutilizar.
        $poll()->assertStatus(410);
        $this->post('/panel/televisores/vincular', ['code' => $pairing['code'], 'name' => 'Otra'])->assertSessionHasErrors('code');

        $this->tv($token)->getJson('/api/tv/session')->assertOk()->assertJsonPath('device.name', 'Tele del salón');
        $this->assertSame('webos', $user->family->devices()->first()->platform);
    }

    public function test_expired_pairing_codes_cannot_be_used(): void
    {
        $user = $this->adult();
        $pairing = $this->postJson('/api/tv/pairings')->json();

        $this->travel(11)->minutes();

        $this->actingAsAdult($user)->post('/panel/televisores/vincular', ['code' => $pairing['code'], 'name' => 'Tele'])
            ->assertSessionHasErrors(['code' => 'El código no es válido o ha caducado. Comprueba el que aparece ahora en el televisor.']);
        $this->getJson('/api/tv/pairings/'.$pairing['id'], ['X-Pairing-Secret' => $pairing['secret']])->assertStatus(410);
        $this->assertSame(0, $user->family->devices()->count());
    }

    public function test_each_profile_sees_only_its_approved_ready_content(): void
    {
        $family = $this->adult()->family;
        $ane = $this->profile($family, 'Ane');
        $iker = $this->profile($family, 'Iker');
        $forAne = $this->youtubeContent($family, ['title' => 'Para Ane']);
        $forIker = $this->youtubeContent($family, ['title' => 'Para Iker']);
        $processing = $this->youtubeContent($family, ['title' => 'Procesando', 'status' => Content::STATUS_PROCESSING]);
        $this->youtubeContent($family, ['title' => 'Sin perfil']);
        $ane->contents()->attach([$forAne->id, $processing->id]);
        $iker->contents()->attach($forIker->id);
        [, $token] = $this->device($family);

        $session = $this->tv($token)->getJson('/api/tv/session')->assertOk();
        $this->assertSame(['Ane', 'Iker'], collect($session->json('profiles'))->pluck('name')->all());

        $this->tv($token)->getJson('/api/tv/profiles/'.$ane->id.'/catalog')
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.title', 'Para Ane');
        $this->tv($token)->getJson('/api/tv/profiles/'.$iker->id.'/catalog')
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.title', 'Para Iker');

        // Un perfil no puede reproducir lo asignado a otro, aunque se manipule la petición.
        $this->tv($token)->getJson('/api/tv/contents/'.$forIker->id.'/play?profile='.$ane->id)->assertNotFound();
        $this->tv($token)->getJson('/api/tv/contents/'.$forAne->id.'/play?profile='.$ane->id)
            ->assertOk()->assertJson(['type' => 'youtube', 'youtube_id' => 'aqz-KE-bpKQ'])
            ->assertJsonPath('youtube_player', route('tv.youtube', ['v' => 'aqz-KE-bpKQ', 'controls' => 'auto']));
    }

    public function test_own_media_is_served_with_signed_urls_and_revocation_cuts_access(): void
    {
        Storage::fake('local');
        $user = $this->adult();
        $family = $user->family;
        $profile = $this->profile($family);
        Storage::disk('local')->put('families/'.$family->id.'/media/v.mp4', 'contenido-video');
        $video = $family->contents()->create([
            'type' => 'video', 'title' => 'Mi vídeo', 'status' => 'ready', 'mime' => 'video/mp4',
            'file_path' => 'families/'.$family->id.'/media/v.mp4', 'file_size' => 15,
        ]);
        $video->profiles()->attach($profile);
        [$device, $token] = $this->device($family);

        $url = $this->tv($token)->getJson('/api/tv/contents/'.$video->id.'/play?profile='.$profile->id)->assertOk()->json('url');
        $this->assertStringContainsString('signature=', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $ttl = config('nokoplay.tv_media_url_ttl_minutes') * 60;
        $this->assertGreaterThan(now()->timestamp + $ttl - 1, (int) $query['expires']);
        $this->assertLessThanOrEqual(now()->timestamp + 2 * $ttl, (int) $query['expires']);

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get(preg_replace('/signature=\w+/', 'signature=falsa', $url))->assertForbidden();
        $this->get(str_replace('/file?', '/cover?', $url))->assertForbidden();

        // Caducada la URL, deja de servir aunque la tele siga vinculada.
        $this->travel(2 * $ttl + 1)->seconds();
        $this->get($url)->assertForbidden();
        $this->travelBack();

        // Desvincular desde el panel: la TV pierde la API y los archivos.
        $this->actingAsAdult($user)->delete('/panel/televisores/'.$device->id)->assertRedirect('/panel/televisores');
        $this->tv($token)->getJson('/api/tv/session')->assertUnauthorized()->assertJson(['error' => 'device_revoked']);
        $this->get($url)->assertForbidden();
    }

    public function test_tv_token_never_grants_access_to_the_panel(): void
    {
        $family = $this->adult()->family;
        [, $token] = $this->device($family);

        $this->tv($token)->get('/panel')->assertRedirect('/entrar');
        $this->tv($token)->get('/panel/cuenta')->assertRedirect('/entrar');
        $this->tv($token)->post('/panel/perfiles', ['name' => 'Hack', 'avatar' => 'sol'])->assertRedirect('/entrar');
        $this->assertSame(0, $family->profiles()->count());
    }

    public function test_adult_zone_requires_pin_with_progressive_lockout(): void
    {
        $user = $this->adult();
        $family = $user->family;
        [$device, $token] = $this->device($family);

        $this->tv($token)->postJson('/api/tv/adult/unlock', ['pin' => '1234'])->assertStatus(409)->assertJson(['error' => 'no_pin']);

        $family->setPin('482916');

        $this->tv($token)->postJson('/api/tv/adult/unlock', ['pin' => '000000'])->assertStatus(422)->assertJson(['attempts_left' => 2]);
        $this->tv($token)->postJson('/api/tv/adult/unlock', ['pin' => '000000'])->assertStatus(422)->assertJson(['attempts_left' => 1]);
        $this->tv($token)->postJson('/api/tv/adult/unlock', ['pin' => '000000'])->assertStatus(423)->assertJson(['retry_after' => 30]);
        // Bloqueado: ni el PIN correcto funciona hasta que pase la espera.
        $this->tv($token)->postJson('/api/tv/adult/unlock', ['pin' => '482916'])->assertStatus(423);

        $this->travel(31)->seconds();
        $this->tv($token)->postJson('/api/tv/adult/unlock', ['pin' => '000000'])->assertStatus(423)->assertJson(['retry_after' => 60]);

        $this->travel(61)->seconds();
        $adultToken = $this->tv($token)->postJson('/api/tv/adult/unlock', ['pin' => '482916'])->assertOk()->json('adult_token');
        $this->assertSame(0, $device->fresh()->pin_failures);

        // Sin token de adulto no hay catálogo completo; con él, sí.
        $this->tv($token)->getJson('/api/tv/adult/catalog')->assertForbidden();
        $this->tv($token)->withHeader('X-Adult-Token', $adultToken)->getJson('/api/tv/adult/catalog')->assertOk();
        $this->tv($token)->withHeader('X-Adult-Token', $adultToken)->getJson('/api/tv/adult/status')->assertJson(['unlocked' => true]);

        // Se bloquea por inactividad.
        $this->travel(config('nokoplay.adult_mode_idle_seconds') + 1)->seconds();
        $this->tv($token)->withHeader('X-Adult-Token', $adultToken)->getJson('/api/tv/adult/catalog')->assertForbidden();
    }

    public function test_adult_zone_can_unlink_this_tv(): void
    {
        $family = $this->adult()->family;
        $family->setPin('1357');
        [$device, $token] = $this->device($family);

        $this->tv($token)->deleteJson('/api/tv/adult/device')->assertForbidden();

        $adult = $this->tv($token)->postJson('/api/tv/adult/unlock', ['pin' => '1357'])->json('adult_token');
        $this->tv($token)->withHeader('X-Adult-Token', $adult)->deleteJson('/api/tv/adult/device')->assertOk();

        $this->assertModelMissing($device);
        $this->tv($token)->getJson('/api/tv/session')->assertUnauthorized();
    }

    public function test_youtube_playback_problems_mark_content_unavailable(): void
    {
        $family = $this->adult()->family;
        $profile = $this->profile($family);
        $content = $this->youtubeContent($family);
        $content->profiles()->attach($profile);
        [, $token] = $this->device($family);

        $this->tv($token)->postJson('/api/tv/contents/'.$content->id.'/problem', ['code' => 5])->assertOk();
        $this->assertSame(Content::STATUS_READY, $content->fresh()->status);

        $this->tv($token)->postJson('/api/tv/contents/'.$content->id.'/problem', ['code' => 150])->assertOk();
        $this->assertSame(Content::STATUS_UNAVAILABLE, $content->fresh()->status);
        $this->tv($token)->getJson('/api/tv/profiles/'.$profile->id.'/catalog')->assertJsonCount(0, 'items');
    }

    public function test_browser_test_mode_creates_a_device_and_hands_token_in_fragment(): void
    {
        $user = $this->adult();

        $response = $this->actingAsAdult($user)->post('/panel/televisores/este-navegador');
        $location = $response->headers->get('Location');
        $this->assertMatchesRegularExpression('~/tv/#token=[A-Za-z0-9]{64}$~', $location);

        $token = substr($location, strpos($location, '#token=') + 7);
        $this->tv($token)->getJson('/api/tv/session')->assertOk();

        // Volver a abrirlo desde el mismo navegador sustituye la «tele» de prueba anterior.
        $this->post('/panel/televisores/este-navegador');
        $this->assertSame(1, $user->family->devices()->count());
    }

    public function test_browser_test_mode_from_the_panel_script_and_reloaded_tabs(): void
    {
        $user = $this->adult();
        $this->actingAsAdult($user);

        // panel.js lo pide en segundo plano: token en JSON, sin redirección.
        $json = $this->postJson('/panel/televisores/este-navegador')->assertOk()->assertJsonPath('url', url('/tv').'/')->json();
        $this->assertMatchesRegularExpression('~^[A-Za-z0-9]{64}$~', $json['token']);
        $this->tv($json['token'])->getJson('/api/tv/session')->assertOk();

        // Una pestaña que se recarga sobre la URL del formulario ya no da 405, y no crea otra tele.
        $this->get('/panel/televisores/este-navegador')->assertRedirect('/tv/');
        $this->assertSame(1, $user->family->devices()->count());
    }

    public function test_youtube_bridge_page_is_embeddable_and_validates_id(): void
    {
        $this->get('/tv/youtube?v=aqz-KE-bpKQ')
            ->assertOk()
            ->assertSee('data-video-id="aqz-KE-bpKQ"', false)
            ->assertSee('data-controls="auto"', false)
            ->assertHeaderMissing('X-Frame-Options')
            ->assertCookieMissing(config('session.cookie'));

        $this->get('/tv/youtube?v=<script>')->assertNotFound();
    }

    public function test_youtube_playback_uses_the_latest_family_controls_preference(): void
    {
        $family = $this->adult()->family;
        $profile = $this->profile($family);
        $content = $this->youtubeContent($family);
        $content->profiles()->attach($profile);
        [, $token] = $this->device($family);

        foreach (['always', 'hidden', 'auto'] as $mode) {
            $family->update(['youtube_controls' => $mode]);
            $this->tv($token)->getJson('/api/tv/contents/'.$content->id.'/play?profile='.$profile->id)
                ->assertOk()->assertJsonPath('youtube_player', route('tv.youtube', ['v' => $content->youtube_id, 'controls' => $mode]));
            $this->get('/tv/youtube?v='.$content->youtube_id.'&controls='.$mode)
                ->assertOk()->assertSee('data-controls="'.$mode.'"', false);
        }

        $this->get('/tv/youtube?v='.$content->youtube_id.'&controls=invalid')
            ->assertOk()->assertSee('data-controls="auto"', false);
    }

    public function test_pairing_code_alphabet_is_unambiguous(): void
    {
        $this->assertStringNotContainsString('0', DevicePairing::CODE_ALPHABET);
        $this->assertStringNotContainsString('O', DevicePairing::CODE_ALPHABET);
        $this->assertStringNotContainsString('1', DevicePairing::CODE_ALPHABET);
        $this->assertStringNotContainsString('I', DevicePairing::CODE_ALPHABET);
    }
}
