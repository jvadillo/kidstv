<?php

namespace Tests\Feature;

use App\Models\Play;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_tv_reports_watched_time_and_it_cannot_be_inflated(): void
    {
        $family = $this->adult()->family;
        $content = $this->youtubeContent($family);
        [$device, $token] = $this->device($family);
        $report = fn (int $seconds, string $key = 'clave12345') => $this->tv($token)
            ->postJson('/api/tv/plays', ['key' => $key, 'content_id' => $content->id, 'seconds' => $seconds]);

        // El primer aviso llega a los 30 s: no puede traer horas.
        $report(5000)->assertOk();
        $play = Play::firstOrFail();
        $this->assertSame(45, $play->seconds);
        $this->assertSame($device->id, $play->device_id);
        $this->assertSame('youtube', $play->content_type);

        // Después, como mucho el tiempo real transcurrido (más un pequeño margen).
        $this->travel(30)->seconds();
        $report(3000)->assertOk();
        $this->assertSame(90, $play->fresh()->seconds);

        $this->travel(30)->seconds();
        $report(120)->assertOk();
        $this->assertSame(120, $play->fresh()->seconds);

        // Nunca baja, y cada reproducción es un registro distinto.
        $report(10)->assertOk();
        $this->assertSame(120, $play->fresh()->seconds);
        $report(20, 'otraclave1')->assertOk();
        $this->assertSame(2, Play::count());
    }

    public function test_only_contents_the_family_can_see_are_reported(): void
    {
        $family = $this->adult()->family;
        $foreign = $this->youtubeContent($this->adult()->family);
        [, $token] = $this->device($family);

        $this->tv($token)->postJson('/api/tv/plays', ['key' => 'clave12345', 'content_id' => $foreign->id, 'seconds' => 20])->assertNotFound();
        $this->tv($token)->postJson('/api/tv/plays', ['key' => 'no válida', 'content_id' => $foreign->id, 'seconds' => 20])->assertStatus(422);
        $this->flushHeaders()->postJson('/api/tv/plays', ['key' => 'clave12345', 'content_id' => $foreign->id, 'seconds' => 20])->assertUnauthorized();
        $this->assertSame(0, Play::count());
    }

    public function test_usage_is_exported_and_pruned_after_a_year(): void
    {
        $user = $this->adult();
        $content = $this->youtubeContent($user->family);
        [$device] = $this->device($user->family);
        Play::create(['family_id' => $user->family_id, 'device_id' => $device->id, 'content_id' => $content->id,
            'content_type' => 'youtube', 'client_key' => 'reciente1', 'seconds' => 60]);
        $old = Play::create(['family_id' => $user->family_id, 'device_id' => $device->id, 'content_id' => $content->id,
            'content_type' => 'youtube', 'client_key' => 'antiguo01', 'seconds' => 60]);
        $old->forceFill(['created_at' => now()->subMonths(13)])->saveQuietly();

        $export = json_decode($this->actingAsAdult($user, confirmed: true)->get('/panel/cuenta/exportar')->streamedContent(), true);
        $this->assertCount(2, $export['usage']);
        $this->assertSame('Tele', $export['usage'][0]['device']);

        $this->artisan('nokoplay:prune')->assertSuccessful();
        $this->assertSame(['reciente1'], Play::pluck('client_key')->all());
    }
}
