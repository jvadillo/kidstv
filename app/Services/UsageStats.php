<?php

namespace App\Services;

use App\Models\Play;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Estadísticas de uso de la plataforma para el panel de administración (tiempo visto). */
class UsageStats
{
    /** Minutos vistos desde una fecha, en toda la plataforma o en una familia. */
    public function minutes(Carbon $since, ?int $familyId = null): int
    {
        return intdiv((int) $this->plays($since, $familyId)->sum('seconds'), 60);
    }

    /**
     * Minutos vistos por día durante los últimos $days días (incluido hoy), sin huecos.
     *
     * @return Collection<string, int> fecha Y-m-d => minutos
     */
    public function daily(int $days, ?int $familyId = null): Collection
    {
        $since = today()->subDays($days - 1);
        $totals = $this->plays($since, $familyId)
            ->selectRaw('date(created_at) as day, sum(seconds) as seconds')
            ->groupBy('day')
            ->pluck('seconds', 'day');

        return collect(range(0, $days - 1))->mapWithKeys(function (int $i) use ($since, $totals) {
            $day = $since->copy()->addDays($i)->toDateString();

            return [$day => intdiv((int) ($totals[$day] ?? 0), 60)];
        });
    }

    /** @return Collection<string, int> tipo de contenido => minutos */
    public function byType(Carbon $since, ?int $familyId = null): Collection
    {
        return $this->plays($since, $familyId)
            ->selectRaw('content_type, sum(seconds) as seconds')
            ->groupBy('content_type')
            ->pluck('seconds', 'content_type')
            ->map(fn ($seconds) => intdiv((int) $seconds, 60))
            ->sortDesc();
    }

    /** Familias con algo visto desde una fecha. */
    public function activeFamilies(Carbon $since): int
    {
        return $this->plays($since)->distinct()->count('family_id');
    }

    /**
     * Minutos y familias por lista pública (vídeos del administrador vistos en cualquier familia).
     *
     * @param  list<int>  $listIds
     * @return Collection<int, object{minutes: int, families: int}>
     */
    public function byList(Carbon $since, array $listIds): Collection
    {
        return Play::query()
            ->join('contents', 'contents.id', '=', 'plays.content_id')
            ->whereIn('contents.list_id', $listIds)
            ->where('plays.created_at', '>=', $since)
            ->selectRaw('contents.list_id as list_id, sum(plays.seconds) as seconds, count(distinct plays.family_id) as families')
            ->groupBy('contents.list_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->list_id => (object) [
                'minutes' => intdiv((int) $row->seconds, 60),
                'families' => (int) $row->families,
            ]]);
    }

    /**
     * Vídeos de listas públicas más vistos.
     *
     * @param  list<int>  $listIds
     */
    public function topContents(Carbon $since, array $listIds, int $limit = 10): Collection
    {
        return Play::query()
            ->join('contents', 'contents.id', '=', 'plays.content_id')
            ->whereIn('contents.list_id', $listIds)
            ->where('plays.created_at', '>=', $since)
            ->selectRaw('contents.id, contents.title, contents.type, sum(plays.seconds) as seconds, count(*) as plays, count(distinct plays.family_id) as families')
            ->groupBy('contents.id', 'contents.title', 'contents.type')
            ->orderByDesc('seconds')
            ->limit($limit)
            ->get();
    }

    private function plays(Carbon $since, ?int $familyId = null): Builder
    {
        return Play::query()
            ->where('created_at', '>=', $since)
            ->when($familyId, fn ($q) => $q->where('family_id', $familyId));
    }

    /** «1 h 05 min», «12 min»... para mostrar minutos de forma legible. */
    public static function format(int $minutes): string
    {
        if ($minutes < 60) {
            return __(':m min', ['m' => $minutes]);
        }

        return __(':h h :m min', ['h' => number_format(intdiv($minutes, 60), 0, ',', '.'), 'm' => sprintf('%02d', $minutes % 60)]);
    }
}
