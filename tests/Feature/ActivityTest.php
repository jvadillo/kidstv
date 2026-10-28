<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Device;
use App\Models\Family;
use App\Models\Play;
use App\Models\Profile;
use App\Services\ActivityReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    private Family $family;

    private Profile $ane;

    private Profile $iker;

    private Device $device;

    private Content $song;

    private Content $story;

    protected function setUp(): void
    {
        parent::setUp();

        // Miércoles: la semana va del lunes 19 al domingo 25 (con cambio de hora el domingo).
        $this->travelTo(Carbon::parse('2026-10-21 18:00'));

        $this->family = $this->adult()->family;
        $this->ane = $this->profile($this->family, 'Ane');
        $this->iker = $this->profile($this->family, 'Iker');
        [$this->device] = $this->device($this->family);
        $list = $this->family->lists()->create(['name' => 'Canciones', 'position' => 1]);
        $this->song = $this->youtubeContent($this->family, ['title' => 'La canción del sol', 'list_id' => $list->id]);
        $this->story = $this->youtubeContent($this->family, ['title' => 'Cuento de la luna', 'youtube_id' => 'dQw4w9WgXcQ']);

        $this->played($this->ane, $this->song, '2026-10-19 10:00', 600);
        $this->played($this->ane, $this->story, '2026-10-19 10:12', 300);    // Misma sesión: 2 min de pausa.
        $this->played($this->ane, $this->song, '2026-10-21 17:00', 1200);
        $this->played($this->iker, $this->story, '2026-10-20 09:00', 900);
        $this->played(null, $this->story, '2026-10-21 12:00', 2000, adult: true);  // Zona de adultos: no cuenta.
        $this->played($this->ane, $this->song, '2026-10-15 10:00', 3000);   // Semana anterior.
    }

    public function test_week_totals_charts_and_rankings_for_everyone_and_for_one_child(): void
    {
        $all = new ActivityReport($this->family, null, 'semana', today());
        $this->assertSame(3000, $all->totalSeconds());
        $this->assertSame(3, $all->elapsedDays());
        $this->assertSame([
            ['label' => 'Media diaria', 'value' => '17 min'],
            ['label' => 'Días con pantalla', 'value' => '3 de 3'],
        ], $all->metrics());
        $this->assertSame([15, 15, 20, 0, 0, 0, 0], array_column($all->bars(), 'minutes'));
        $this->assertSame([false, false, false, true, true, true, true], array_column($all->bars(), 'future'));
        $this->assertSame(['La canción del sol', 'Cuento de la luna'], $all->top('tiempo')->pluck('content.title')->all());
        $this->assertSame([2, 2], $all->top('veces')->pluck('plays')->all());
        $this->assertSame('Canciones', $all->top('tiempo')->first()->list);
        $this->assertSame(['Canciones' => 1800, 'Sin lista' => 1200], $all->lists()->pluck('seconds', 'name')->all());
        $this->assertSame(['Ane', 'Iker', 'Ane', 'Ane'], $all->timeline()->pluck('profile.name')->all());

        $ane = new ActivityReport($this->family, $this->ane, 'semana', today());
        $this->assertSame(2100, $ane->totalSeconds());
        $this->assertSame('2 de 3', $ane->metrics()[1]['value']);
        $this->assertSame([15, 0, 20, 0, 0, 0, 0], array_column($ane->bars(), 'minutes'));

        // Semanas anteriores: completas, con navegación hacia delante.
        $previous = new ActivityReport($this->family, $this->ane, 'semana', Carbon::parse('2026-10-14'));
        $this->assertSame(3000, $previous->totalSeconds());
        $this->assertSame(7, $previous->elapsedDays());
        $this->assertTrue($previous->hasNext());
        $this->assertFalse($ane->hasNext());
        $this->assertSame('2026-10-11', $previous->previousDate());
    }

    public function test_day_uses_sessions_and_three_hour_slots_and_month_averages_by_week(): void
    {
        $day = new ActivityReport($this->family, null, 'dia', Carbon::parse('2026-10-19'));
        $this->assertSame([
            ['label' => 'Media por sesión', 'value' => '15 min'],
            ['label' => 'Número de vídeos', 'value' => '2'],
        ], $day->metrics());
        $this->assertSame([0, 0, 0, 15, 0, 0, 0, 0], array_column($day->bars(), 'minutes'));
        $this->assertSame('0 h', $day->bars()[0]['label']);

        $month = new ActivityReport($this->family, null, 'mes', today());
        $this->assertSame(['1–4', '5–11', '12–18', '19–25', '26–31'], array_column($month->bars(), 'label'));
        // Del 19 al 25 solo han pasado 3 días: 3000 s / 3 = 17 min al día.
        $this->assertSame([0, 0, 7, 17, 0], array_column($month->bars(), 'minutes'));
        $this->assertTrue($month->bars()[4]['future']);
        $this->assertSame('Este mes', $month->label());
    }

    public function test_activity_page_filters_by_child_and_period_and_never_leaks_other_families(): void
    {
        $user = $this->family->users()->first();
        $other = $this->adult()->family;
        $otherKid = $this->profile($other, 'Otro');
        $foreign = $this->youtubeContent($other, ['title' => 'Vídeo ajeno']);
        $this->played($otherKid, $foreign, '2026-10-21 11:00', 999, device: $this->device($other)[0]);
        $this->actingAsAdult($user);

        $this->get('/panel/actividad')->assertOk()
            ->assertSee('Actividad')->assertSee('Esta semana')->assertSee('50 min')
            ->assertSee('La canción del sol')->assertSee('Cuento de la luna')->assertSee('Canciones')
            ->assertSee('Cómo se mide la actividad')
            ->assertDontSee('Vídeo ajeno');

        $this->get('/panel/actividad?perfil='.$this->ane->id)->assertOk()->assertSee('35 min')->assertSee('2 de 3');
        $this->get('/panel/actividad?perfil='.$otherKid->id)->assertOk()->assertSee('50 min')->assertDontSee('Vídeo ajeno');
        $this->get('/panel/actividad?periodo=dia')->assertOk()->assertSee('Hoy')->assertSee('Media por sesión');
        $this->get('/panel/actividad?periodo=semana&fecha=2026-10-14')->assertOk()->assertSee('Semana del 12 al 18');
        // Una fecha futura se queda en hoy.
        $this->get('/panel/actividad?periodo=dia&fecha=2030-01-01')->assertOk()->assertSee('Hoy');
        $this->get('/panel/actividad?periodo=anio')->assertSessionHasErrors('periodo');

        // «Actividad» sustituye a «Teles» en la navegación; las teles siguen en Cuenta.
        $this->get('/panel')->assertSee(route('activity'))->assertDontSee('>Teles<', false);
        $this->get('/panel/cuenta')->assertSee(route('devices.index'));
    }

    public function test_watching_now_shows_who_is_watching_while_the_tv_keeps_reporting(): void
    {
        $this->actingAsAdult($this->family->users()->first());
        $this->get('/panel/actividad')->assertOk()->assertDontSee('badge-live', false);

        $this->played($this->iker, $this->story, '2026-10-21 17:59:00', 40);
        $this->played(null, $this->song, '2026-10-21 17:59:30', 20, adult: true);    // Zona de adultos: no cuenta.
        $this->get('/panel/actividad')->assertSee('Viendo ahora: Iker')->assertSee('Iker: Cuento de la luna');
        $this->get('/panel/actividad?perfil='.$this->iker->id)->assertSee('badge-live', false)->assertDontSee('Viendo ahora:');
        $this->get('/panel/actividad?perfil='.$this->ane->id)->assertDontSee('badge-live', false);

        // Sin avisos de la tele (pausa o fin), deja de mostrarse.
        $this->travel(2)->minutes();
        $this->get('/panel/actividad')->assertDontSee('badge-live', false);
    }

    public function test_deleting_a_child_keeps_the_time_without_the_child(): void
    {
        $this->actingAsAdult($this->family->users()->first());
        $this->delete('/panel/perfiles/'.$this->ane->id)->assertRedirect();

        $this->assertSame(0, Play::where('profile_id', $this->ane->id)->count());
        $this->assertSame(3000, (new ActivityReport($this->family, null, 'semana', today()))->totalSeconds());
    }

    private function played(?Profile $profile, Content $content, string $at, int $seconds, bool $adult = false, ?Device $device = null): Play
    {
        $device ??= $this->device;
        $play = Play::create([
            'family_id' => $device->family_id, 'device_id' => $device->id, 'profile_id' => $profile?->id,
            'content_id' => $content->id, 'content_type' => $content->type, 'client_key' => uniqid('k'),
            'seconds' => $seconds, 'adult' => $adult,
        ]);
        $start = Carbon::parse($at);
        $play->forceFill(['created_at' => $start, 'updated_at' => $start->copy()->addSeconds($seconds)])->saveQuietly();

        return $play;
    }
}
