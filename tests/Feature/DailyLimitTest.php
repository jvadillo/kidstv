<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Family;
use App\Models\Play;
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

    public function test_tv_gets_the_time_left_today_on_that_tv(): void
    {
        $family = $this->adult()->family;
        $profile = $this->profile($family);
        [$device, $token] = $this->device($family);
        [$other] = $this->device($family, 'Cocina');

        $this->tv($token)->getJson('/api/tv/session')->assertOk()->assertJsonPath('screen_time', null);

        $family->update(['daily_limit_enabled' => true, 'daily_limit_minutes' => 30]);
        $this->played($device, 600);
        $this->played($device, 120, adult: true);   // Zona de adultos: no cuenta.
        $this->played($other, 900);                 // Otra tele: no cuenta.
        $this->played($device, 1200)->forceFill(['created_at' => now()->subDay()])->saveQuietly();  // Ayer.

        $expected = ['limit_seconds' => 1800, 'remaining_seconds' => 1200, 'warn_seconds' => 300];
        $this->tv($token)->getJson('/api/tv/session')->assertJsonPath('screen_time', $expected);
        $this->tv($token)->getJson('/api/tv/profiles/'.$profile->id.'/catalog')->assertJsonPath('screen_time', $expected);

        // El aviso llega con una quinta parte del límite por delante, como mínimo un minuto.
        $family->update(['daily_limit_minutes' => 5]);
        $this->tv($token)->getJson('/api/tv/session')->assertJsonPath('screen_time.warn_seconds', 60);
    }

    public function test_playback_stops_when_the_time_is_up_except_in_the_adult_zone(): void
    {
        $family = $this->adult()->family;
        $family->setPin('2468');
        $family->update(['daily_limit_enabled' => true, 'daily_limit_minutes' => 5]);
        $profile = $this->profile($family);
        $content = $this->youtubeContent($family);
        $content->profiles()->attach($profile);
        [$device, $token] = $this->device($family);
        $play = fn () => $this->tv($token)->getJson('/api/tv/contents/'.$content->id.'/play?profile='.$profile->id);

        $play()->assertOk()->assertJsonPath('screen_time.remaining_seconds', 300);

        // Los avisos de la tele devuelven lo que queda; a pocos segundos del final, se da por agotado.
        $report = fn (int $seconds) => $this->tv($token)
            ->postJson('/api/tv/plays', ['key' => 'clave12345', 'content_id' => $content->id, 'seconds' => $seconds]);
        $report(30)->assertJsonPath('screen_time.remaining_seconds', 270);
        for ($seconds = 60; $seconds <= 270; $seconds += 30) {
            $this->travel(30)->seconds();
            $report($seconds);
        }
        $this->travel(20)->seconds();
        $this->assertSame(0, $report(290)->json('screen_time.remaining_seconds'));

        $play()->assertStatus(423)->assertJson(['error' => 'time_up']);

        // Desde la Zona de adultos se puede seguir viendo, y ese tiempo no cuenta para el niño.
        $adult = $this->tv($token)->postJson('/api/tv/adult/unlock', ['pin' => '2468'])->json('adult_token');
        $this->tv($token)->withHeader('X-Adult-Token', $adult)->getJson('/api/tv/contents/'.$content->id.'/play')
            ->assertOk()->assertJsonPath('screen_time', null);
        $this->tv($token)->withHeader('X-Adult-Token', $adult)
            ->postJson('/api/tv/plays', ['key' => 'adulto1234', 'content_id' => $content->id, 'seconds' => 40])
            ->assertJsonPath('screen_time', null);
        $this->assertTrue(Play::where('client_key', 'adulto1234')->value('adult'));

        // Al día siguiente vuelve a haber tiempo; y si el adulto lo amplía, en el momento.
        $this->travelTo(today()->addDay()->setTime(8, 0));
        $this->flushHeaders();
        $play()->assertOk()->assertJsonPath('screen_time.remaining_seconds', 300);
        $this->travelBack();
        $family->update(['daily_limit_minutes' => 10]);
        $play()->assertOk()->assertJsonPath('screen_time.remaining_seconds', 310);
    }

    private function played(Device $device, int $seconds, bool $adult = false): Play
    {
        return Play::create([
            'family_id' => $device->family_id, 'device_id' => $device->id, 'content_id' => null,
            'content_type' => 'youtube', 'client_key' => uniqid('k'), 'seconds' => $seconds, 'adult' => $adult,
        ]);
    }
}
