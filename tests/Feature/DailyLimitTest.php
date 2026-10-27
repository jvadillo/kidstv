<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Family;
use App\Models\Play;
use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_enables_and_disables_the_daily_limit(): void
    {
        $user = $this->adult();
        $this->actingAsAdult($user);

        $this->get('/panel/cuenta')->assertOk()->assertSee('Límite diario de tele')->assertSee('1 h');
        $this->assertNull($user->family->fresh()->dailyLimit());

        $step = array_search(90, Family::DAILY_LIMIT_OPTIONS, true);
        $this->put('/panel/cuenta', ['daily_limit' => '1', 'daily_limit_step' => $step])->assertSessionHasNoErrors();
        $this->assertSame(90, $user->family->fresh()->dailyLimit());
        $this->get('/panel/cuenta')->assertSee('1 h 30 min');

        // Desactivado deja de aplicarse, pero recuerda los minutos para la próxima vez.
        $this->put('/panel/cuenta', ['daily_limit' => '0', 'daily_limit_step' => $step])->assertSessionHasNoErrors();
        $family = $user->family->fresh();
        $this->assertNull($family->dailyLimit());
        $this->assertSame(90, $family->daily_limit_minutes);

        // Otros formularios que no lo envían no lo tocan; un valor fuera del control se rechaza.
        $this->put('/panel/cuenta', ['daily_limit' => '1', 'daily_limit_step' => 0])->assertSessionHasNoErrors();
        $this->put('/panel/cuenta', ['name' => 'Ana'])->assertSessionHasNoErrors();
        $this->assertSame(5, $user->family->fresh()->dailyLimit());
        $this->put('/panel/cuenta', ['daily_limit' => '1', 'daily_limit_step' => count(Family::DAILY_LIMIT_OPTIONS)])->assertSessionHasErrors('daily_limit_step');
        $this->assertSame(5, $user->family->fresh()->dailyLimit());
    }

    public function test_each_child_gets_the_time_left_today_on_any_tv(): void
    {
        $family = $this->adult()->family;
        $ane = $this->profile($family, 'Ane');
        $iker = $this->profile($family, 'Iker');
        [$device, $token] = $this->device($family);
        [$other] = $this->device($family, 'Cocina');

        $this->tv($token)->getJson('/api/tv/session')->assertOk()->assertJsonPath('profiles.0.screen_time', null);

        $family->update(['daily_limit_enabled' => true, 'daily_limit_minutes' => 30]);
        $this->played($device, $ane, 600);
        $this->played($other, $ane, 300);             // En otra tele: también cuenta.
        $this->played($device, $iker, 1000);          // Su hermano: no cuenta para Ane.
        $this->played($device, null, 500);            // Sin perfil (app antigua): no cuenta.
        $this->played($device, null, 120, adult: true);   // Zona de adultos: no cuenta.
        $this->played($device, $ane, 1200)->forceFill(['created_at' => now()->subDay()])->saveQuietly();  // Ayer.

        $expected = ['limit_seconds' => 1800, 'remaining_seconds' => 900, 'warn_seconds' => 300];
        $this->tv($token)->getJson('/api/tv/session')
            ->assertJsonPath('profiles.0.screen_time', $expected)
            ->assertJsonPath('profiles.1.screen_time.remaining_seconds', 800);
        $this->tv($token)->getJson('/api/tv/profiles/'.$ane->id.'/catalog')->assertJsonPath('screen_time', $expected);

        // El aviso llega con una quinta parte del límite por delante, como mínimo un minuto.
        $family->update(['daily_limit_minutes' => 5]);
        $this->tv($token)->getJson('/api/tv/session')->assertJsonPath('profiles.0.screen_time.warn_seconds', 60);
    }

    public function test_playback_stops_when_the_time_is_up_except_in_the_adult_zone(): void
    {
        $family = $this->adult()->family;
        $family->setPin('2468');
        $family->update(['daily_limit_enabled' => true, 'daily_limit_minutes' => 5]);
        $profile = $this->profile($family);
        $sibling = $this->profile($family, 'Iker');
        $content = $this->youtubeContent($family);
        $content->profiles()->attach([$profile->id, $sibling->id]);
        [, $token] = $this->device($family);
        $play = fn ($who = null) => $this->tv($token)->getJson('/api/tv/contents/'.$content->id.'/play?profile='.($who ?? $profile)->id);

        $play()->assertOk()->assertJsonPath('screen_time.remaining_seconds', 300);

        // Los avisos de la tele devuelven lo que queda; a pocos segundos del final, se da por agotado.
        $report = fn (int $seconds) => $this->tv($token)
            ->postJson('/api/tv/plays', ['key' => 'clave12345', 'content_id' => $content->id, 'profile_id' => $profile->id, 'seconds' => $seconds]);
        $report(30)->assertJsonPath('screen_time.remaining_seconds', 270);
        for ($seconds = 60; $seconds <= 270; $seconds += 30) {
            $this->travel(30)->seconds();
            $report($seconds);
        }
        $this->travel(20)->seconds();
        $this->assertSame(0, $report(290)->json('screen_time.remaining_seconds'));

        $play()->assertStatus(423)->assertJson(['error' => 'time_up']);
        $this->assertSame($profile->id, Play::where('client_key', 'clave12345')->value('profile_id'));
        // Su hermano tiene su propio tiempo.
        $play($sibling)->assertOk()->assertJsonPath('screen_time.remaining_seconds', 300);

        // Desde la Zona de adultos se puede seguir viendo, y ese tiempo no cuenta para el niño.
        $adult = $this->tv($token)->postJson('/api/tv/adult/unlock', ['pin' => '2468'])->json('adult_token');
        $this->tv($token)->withHeader('X-Adult-Token', $adult)->getJson('/api/tv/contents/'.$content->id.'/play')
            ->assertOk()->assertJsonPath('screen_time', null);
        $this->tv($token)->withHeader('X-Adult-Token', $adult)
            ->postJson('/api/tv/plays', ['key' => 'adulto1234', 'content_id' => $content->id, 'profile_id' => $profile->id, 'seconds' => 40])
            ->assertJsonPath('screen_time', null);
        $adultPlay = Play::where('client_key', 'adulto1234')->first();
        $this->assertTrue($adultPlay->adult);
        $this->assertNull($adultPlay->profile_id);

        // Al día siguiente vuelve a haber tiempo; y si el adulto lo amplía, en el momento.
        $this->travelTo(today()->addDay()->setTime(8, 0));
        $this->flushHeaders();
        $play()->assertOk()->assertJsonPath('screen_time.remaining_seconds', 300);
        $this->travelBack();
        $family->update(['daily_limit_minutes' => 10]);
        $play()->assertOk()->assertJsonPath('screen_time.remaining_seconds', 310);
    }

    private function played(Device $device, ?Profile $profile, int $seconds, bool $adult = false): Play
    {
        return Play::create([
            'family_id' => $device->family_id, 'device_id' => $device->id, 'profile_id' => $profile?->id, 'content_id' => null,
            'content_type' => 'youtube', 'client_key' => uniqid('k'), 'seconds' => $seconds, 'adult' => $adult,
        ]);
    }
}
