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

    public function test_parent_chooses_no_limit_shared_time_or_a_limit_for_each_child(): void
    {
        $user = $this->adult();
        $family = $user->family;
        $ane = $this->profile($family, 'Ane');
        $iker = $this->profile($family, 'Iker');
        $foreign = $this->profile($this->adult()->family, 'Otro');
        $this->actingAsAdult($user);
        $step = fn (int $minutes) => array_search($minutes, Family::DAILY_LIMIT_OPTIONS, true);

        $this->get('/panel/cuenta')->assertOk()->assertSee('Límite diario de tele')
            ->assertSee('Sin límite')->assertSee('Un tiempo compartido entre todos')->assertSee('Un límite para cada niño')
            ->assertSee('profile_limit_steps['.$ane->id.']', false)->assertSee('1 h');
        $this->assertSame('off', $family->fresh()->daily_limit_mode);
        $this->assertNull($family->fresh()->dailyLimitFor($ane));

        $this->put('/panel/cuenta', ['daily_limit_mode' => 'shared', 'daily_limit_step' => $step(90)])->assertSessionHasNoErrors();
        $family = $family->fresh();
        $this->assertSame(90, $family->dailyLimitFor($ane));
        $this->assertSame(90, $family->dailyLimitFor($iker));
        $this->get('/panel/cuenta')->assertSee('1 h 30 min');

        // Cada niño con su tiempo; un perfil de otra familia se ignora.
        $this->put('/panel/cuenta', ['daily_limit_mode' => 'per_profile', 'daily_limit_step' => $step(90), 'profile_limit_steps' => [
            $ane->id => $step(30), $iker->id => $step(120), $foreign->id => 0,
        ]])->assertSessionHasNoErrors();
        $family = $family->fresh();
        $this->assertSame(30, $family->dailyLimitFor($ane->fresh()));
        $this->assertSame(120, $family->dailyLimitFor($iker->fresh()));
        $this->assertSame(60, $foreign->fresh()->daily_limit_minutes);

        // Sin límite deja de aplicarse, pero recuerda los minutos para la próxima vez.
        $this->put('/panel/cuenta', ['daily_limit_mode' => 'off'])->assertSessionHasNoErrors();
        $family = $family->fresh();
        $this->assertNull($family->dailyLimitFor($ane->fresh()));
        $this->assertSame(90, $family->daily_limit_minutes);
        $this->assertSame(30, $ane->fresh()->daily_limit_minutes);

        // Otros formularios que no lo envían no lo tocan; valores fuera de los controles se rechazan.
        $this->put('/panel/cuenta', ['daily_limit_mode' => 'shared'])->assertSessionHasNoErrors();
        $this->put('/panel/cuenta', ['name' => 'Ana'])->assertSessionHasNoErrors();
        $this->assertSame('shared', $family->fresh()->daily_limit_mode);
        $this->put('/panel/cuenta', ['daily_limit_mode' => 'siempre'])->assertSessionHasErrors('daily_limit_mode');
        $this->put('/panel/cuenta', ['daily_limit_step' => count(Family::DAILY_LIMIT_OPTIONS)])->assertSessionHasErrors('daily_limit_step');
        $this->put('/panel/cuenta', ['profile_limit_steps' => [$ane->id => -1]])->assertSessionHasErrors('profile_limit_steps.'.$ane->id);
        $this->assertSame(90, $family->fresh()->daily_limit_minutes);
    }

    public function test_each_child_gets_their_own_time_left_today_on_any_tv(): void
    {
        $family = $this->adult()->family;
        $ane = $this->profile($family, 'Ane');
        $iker = $this->profile($family, 'Iker');
        [$device, $token] = $this->device($family);
        [$other] = $this->device($family, 'Cocina');

        $this->tv($token)->getJson('/api/tv/session')->assertOk()->assertJsonPath('profiles.0.screen_time', null);

        $family->update(['daily_limit_mode' => 'per_profile']);
        $ane->update(['daily_limit_minutes' => 30]);
        $this->played($device, $ane, 600);
        $this->played($other, $ane, 300);             // En otra tele: también cuenta.
        $this->played($device, $iker, 1000);          // Su hermano: no cuenta para Ane.
        $this->played($device, null, 500);            // Sin perfil (app antigua): no cuenta.
        $this->played($device, null, 120, adult: true);   // Zona de adultos: no cuenta.
        $this->played($device, $ane, 1200)->forceFill(['created_at' => now()->subDay()])->saveQuietly();  // Ayer.

        $expected = ['limit_seconds' => 1800, 'remaining_seconds' => 900, 'warn_seconds' => 300];
        $this->tv($token)->getJson('/api/tv/session')
            ->assertJsonPath('profiles.0.screen_time', $expected)
            ->assertJsonPath('profiles.1.screen_time.limit_seconds', 3600)
            ->assertJsonPath('profiles.1.screen_time.remaining_seconds', 2600);
        $this->tv($token)->getJson('/api/tv/profiles/'.$ane->id.'/catalog')->assertJsonPath('screen_time', $expected);

        // El aviso llega con una quinta parte del límite por delante, como mínimo un minuto.
        $ane->update(['daily_limit_minutes' => 5]);
        $this->tv($token)->getJson('/api/tv/session')->assertJsonPath('profiles.0.screen_time.warn_seconds', 60);
    }

    public function test_shared_time_counts_what_all_children_watch_together(): void
    {
        $family = $this->adult()->family;
        $family->update(['daily_limit_mode' => 'shared', 'daily_limit_minutes' => 60]);
        $ane = $this->profile($family, 'Ane');
        $iker = $this->profile($family, 'Iker');
        $content = $this->youtubeContent($family);
        $content->profiles()->attach([$ane->id, $iker->id]);
        [$device, $token] = $this->device($family);
        [$other] = $this->device($family, 'Cocina');

        $this->played($device, $ane, 1200);           // 20 min de Ane...
        $this->played($other, $iker, 1500);           // ...25 de Iker en otra tele...
        $this->played($device, null, 300);            // ...y 5 sin perfil (app antigua): también cuentan.
        $this->played($device, null, 900, adult: true);   // Zona de adultos: no cuenta.
        $this->played($device, $iker, 1200)->forceFill(['created_at' => now()->subDay()])->saveQuietly();  // Ayer.

        $expected = ['limit_seconds' => 3600, 'remaining_seconds' => 600, 'warn_seconds' => 300];
        $this->tv($token)->getJson('/api/tv/session')
            ->assertJsonPath('profiles.0.screen_time', $expected)
            ->assertJsonPath('profiles.1.screen_time', $expected);

        // Cuando uno agota el tiempo, se acaba para todos.
        $this->played($device, $iker, 600);
        $play = fn (Profile $who) => $this->tv($token)->getJson('/api/tv/contents/'.$content->id.'/play?profile='.$who->id);
        $play($ane)->assertStatus(423)->assertJson(['error' => 'time_up']);
        $play($iker)->assertStatus(423);

        // Con un límite para cada niño (1 h por defecto), a Ane le quedan sus 40 min.
        $family->update(['daily_limit_mode' => 'per_profile']);
        $play($ane)->assertOk()->assertJsonPath('screen_time.remaining_seconds', 2400);
    }

    public function test_playback_stops_when_the_time_is_up_except_in_the_adult_zone(): void
    {
        $family = $this->adult()->family;
        $family->setPin('2468');
        $family->update(['daily_limit_mode' => 'per_profile']);
        $profile = $this->profile($family);
        $sibling = $this->profile($family, 'Iker');
        $profile->update(['daily_limit_minutes' => 5]);
        $sibling->update(['daily_limit_minutes' => 5]);
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
        $profile->update(['daily_limit_minutes' => 10]);
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
