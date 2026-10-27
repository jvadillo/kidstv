<?php

namespace App\Services;

use App\Models\Content;
use App\Models\Family;
use App\Models\Play;
use App\Models\Profile;
use App\Models\VideoList;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pantalla «Actividad» del panel: qué y cuánto ha visto un niño (o todos) en un día,
 * una semana o un mes. Se calcula con las reproducciones que informan las teles, sin
 * lo visto desde la Zona de adultos. Cada reproducción cuenta en el momento en que empezó.
 */
class ActivityReport
{
    public const PERIODS = ['dia', 'semana', 'mes'];

    // Entre dos vídeos seguidos en la misma tele, una pausa más larga empieza otra sesión.
    public const SESSION_GAP_MINUTES = 15;

    public const RETENTION_MONTHS = 12;

    public readonly CarbonImmutable $start;

    /** Fin del periodo (excluido). */
    public readonly CarbonImmutable $end;

    /** @var Collection<int, Play> */
    private Collection $plays;

    /** @var Collection<int, VideoList> listas públicas enlazadas, por lista original */
    private Collection $linkedLists;

    public function __construct(
        public readonly Family $family,
        public readonly ?Profile $profile,
        public readonly string $period,
        Carbon|CarbonImmutable $date,
    ) {
        $day = CarbonImmutable::parse($date->toDateString());
        [$this->start, $this->end] = match ($period) {
            'dia' => [$day, $day->addDay()],
            'semana' => [$day->startOfWeek(CarbonImmutable::MONDAY), $day->startOfWeek(CarbonImmutable::MONDAY)->addWeek()],
            'mes' => [$day->startOfMonth(), $day->startOfMonth()->addMonth()],
        };

        $this->plays = Play::query()
            ->where('family_id', $family->id)
            ->where('adult', false)
            ->when($profile, fn ($query) => $query->where('profile_id', $profile->id))
            ->where('created_at', '>=', $this->start)
            ->where('created_at', '<', $this->end)
            ->with(['content.list', 'profile'])
            ->orderBy('created_at')
            ->get();

        $this->linkedLists = $family->lists()->whereNotNull('source_id')->with('source')->get()->keyBy('source_id');
    }

    /* ---------- Periodo y navegación ---------- */

    public function isCurrent(): bool
    {
        return now()->gte($this->start) && now()->lt($this->end);
    }

    public function hasNext(): bool
    {
        return $this->end->lte(today());
    }

    public function hasPrevious(): bool
    {
        return $this->start->gt(today()->subMonths(self::RETENTION_MONTHS));
    }

    public function previousDate(): string
    {
        return $this->start->subDay()->toDateString();
    }

    public function nextDate(): string
    {
        return $this->end->toDateString();
    }

    /** «Esta semana», «Ayer», «Septiembre de 2026»... */
    public function label(): string
    {
        return match ($this->period) {
            'dia' => match (true) {
                $this->start->isToday() => __('Hoy'),
                $this->start->isYesterday() => __('Ayer'),
                default => ucfirst($this->start->translatedFormat('l j \d\e F')),
            },
            'semana' => $this->isCurrent() ? __('Esta semana') : __('Semana del :range', ['range' => $this->range($this->start, $this->end->subDay())]),
            'mes' => $this->isCurrent() ? __('Este mes') : ucfirst($this->start->translatedFormat('F \d\e Y')),
        };
    }

    /** Fechas exactas del periodo, cortas para que quepan entre «Anterior» y «Siguiente». */
    public function dates(): string
    {
        return match ($this->period) {
            'dia' => self::shortDate($this->start).' '.$this->start->year,
            'semana' => self::shortDate($this->start).' – '.self::shortDate($this->end->subDay()),
            'mes' => $this->start->translatedFormat('F Y'),
        };
    }

    /** «26 oct», sin el punto de la abreviatura. */
    private static function shortDate(CarbonImmutable $date): string
    {
        return rtrim($date->translatedFormat('j M'), '.');
    }

    /* ---------- Tiempo de pantalla ---------- */

    public function totalSeconds(): int
    {
        return (int) $this->plays->sum('seconds');
    }

    /** Días del periodo que ya han empezado (para las medias del periodo en curso). */
    public function elapsedDays(): int
    {
        $until = $this->end->min(today()->addDay()->toImmutable());

        // Redondeado: con el cambio de hora un día dura 23 o 25 horas.
        return max(1, (int) round($this->start->diffInDays($until)));
    }

    public function daysWithActivity(): int
    {
        return $this->plays->groupBy(fn (Play $play) => $play->created_at->toDateString())->count();
    }

    public function videoCount(): int
    {
        return $this->plays->count();
    }

    /** Sesiones: vídeos seguidos en la misma tele y del mismo niño, con pausas cortas entre ellos. */
    public function sessionCount(): int
    {
        $sessions = 0;
        $last = [];
        foreach ($this->plays as $play) {
            $key = $play->device_id.'-'.$play->profile_id;
            if (! isset($last[$key]) || $last[$key]->diffInMinutes($play->created_at, true) > self::SESSION_GAP_MINUTES) {
                $sessions++;
            }
            $last[$key] = $play->updated_at->max($play->created_at);
        }

        return $sessions;
    }

    /**
     * Las dos cifras bajo el total: media por sesión y número de vídeos en un día;
     * media diaria y días con pantalla en una semana o un mes.
     *
     * @return list<array{label: string, value: string}>
     */
    public function metrics(): array
    {
        $total = $this->totalSeconds();

        if ($this->period === 'dia') {
            $sessions = $this->sessionCount();

            return [
                ['label' => __('Media por sesión'), 'value' => self::duration($sessions ? intdiv($total, $sessions) : 0)],
                ['label' => __('Número de vídeos'), 'value' => (string) $this->videoCount()],
            ];
        }

        return [
            ['label' => __('Media diaria'), 'value' => self::duration(intdiv($total, $this->elapsedDays()))],
            ['label' => __('Días con pantalla'), 'value' => __(':n de :total', ['n' => $this->daysWithActivity(), 'total' => $this->elapsedDays()])],
        ];
    }

    /**
     * Barras del gráfico: minutos por franja de 3 horas (día), por día (semana) o media
     * diaria por semana (mes).
     *
     * @return list<array{label: string, tip: string, name: string, minutes: int, future: bool}>
     */
    public function bars(): array
    {
        return match ($this->period) {
            'dia' => $this->hourBars(),
            'semana' => $this->dayBars(),
            'mes' => $this->weekBars(),
        };
    }

    private function hourBars(): array
    {
        $seconds = $this->plays->groupBy(fn (Play $play) => intdiv($play->created_at->hour, 3))->map->sum('seconds');

        return collect(range(0, 7))->map(function (int $slot) use ($seconds) {
            $from = $slot * 3;
            $minutes = self::minutes((int) ($seconds[$slot] ?? 0));
            $name = __(':from a :to h', ['from' => $from, 'to' => $from + 3]);

            return [
                'label' => $from.' h',
                'name' => $name,
                'tip' => $name.' · '.UsageStats::format($minutes),
                'minutes' => $minutes,
                'future' => $this->start->addHours($from)->isFuture(),
            ];
        })->all();
    }

    private function dayBars(): array
    {
        $seconds = $this->plays->groupBy(fn (Play $play) => $play->created_at->toDateString())->map->sum('seconds');
        $initials = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

        return collect(range(0, 6))->map(function (int $i) use ($seconds, $initials) {
            $day = $this->start->addDays($i);
            $minutes = self::minutes((int) ($seconds[$day->toDateString()] ?? 0));

            return [
                'label' => $initials[$i],
                'name' => ucfirst($day->translatedFormat('l j')),
                'tip' => $day->translatedFormat('D j').' · '.UsageStats::format($minutes),
                'minutes' => $minutes,
                'future' => $day->isFuture(),
            ];
        })->all();
    }

    /** Semanas de lunes a domingo recortadas al mes, con la media de minutos al día. */
    private function weekBars(): array
    {
        $bars = [];
        for ($from = $this->start; $from->lt($this->end); $from = $to->addDay()) {
            $to = $from->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()->min($this->end->subDay());
            $days = max(1, (int) round($from->diffInDays($to->addDay()->min(today()->addDay()->toImmutable()))));
            $seconds = (int) $this->plays->filter(fn (Play $play) => $play->created_at->betweenIncluded($from, $to->endOfDay()))->sum('seconds');
            $minutes = self::minutes(intdiv($seconds, $days));
            $range = $from->day === $to->day ? (string) $from->day : $from->day.'–'.$to->day;

            $bars[] = [
                'label' => $range,
                'name' => __('Del :range', ['range' => $this->range($from, $to)]),
                'tip' => $range.' '.$from->translatedFormat('M').' · '.__(':time al día', ['time' => UsageStats::format($minutes)]),
                'minutes' => $minutes,
                'future' => $from->isFuture(),
            ];
        }

        return $bars;
    }

    /* ---------- Lo que más ve ---------- */

    /**
     * Los 10 contenidos más vistos, por tiempo o por veces.
     *
     * @return Collection<int, object{content: Content, seconds: int, plays: int, list: string, cover: ?string}>
     */
    public function top(string $by): Collection
    {
        return $this->plays
            ->filter(fn (Play $play) => $play->content !== null)
            ->groupBy('content_id')
            ->map(fn (Collection $plays) => (object) [
                'content' => $plays->first()->content,
                'seconds' => (int) $plays->sum('seconds'),
                'plays' => $plays->count(),
            ])
            ->sort(fn ($a, $b) => $by === 'veces'
                ? [$b->plays, $b->seconds] <=> [$a->plays, $a->seconds]
                : [$b->seconds, $b->plays] <=> [$a->seconds, $a->plays])
            ->take(10)
            ->map(function ($row) {
                $row->list = $this->listName($row->content);
                $row->cover = $this->coverUrl($row->content);

                return $row;
            })
            ->values();
    }

    /* ---------- Cuándo lo ha visto ---------- */

    /** Últimas reproducciones del periodo, de la más reciente a la más antigua. */
    public function timeline(int $limit = 60): Collection
    {
        return $this->plays->sortByDesc('created_at')->take($limit)->values();
    }

    public function timelineTotal(): int
    {
        return $this->plays->count();
    }

    /* ---------- Tiempo por listas ---------- */

    /** @return Collection<int, object{name: string, seconds: int}> las 5 listas con más tiempo */
    public function lists(): Collection
    {
        return $this->plays
            ->groupBy(fn (Play $play) => $play->content ? $this->listName($play->content) : __('Contenidos eliminados'))
            ->map(fn (Collection $plays, string $name) => (object) ['name' => $name, 'seconds' => (int) $plays->sum('seconds')])
            ->sortByDesc('seconds')
            ->take(5)
            ->values();
    }

    /** Nombre de la lista tal como la ve la familia (las públicas, por su enlace). */
    public function listName(Content $content): string
    {
        if ($content->family_id !== $this->family->id) {
            return $this->linkedLists->get($content->list_id)?->displayName() ?? $content->list?->name ?? __('Lista pública');
        }

        return $content->list?->name ?? __('Sin lista');
    }

    private function coverUrl(Content $content): ?string
    {
        if (! $content->cover_path) {
            return $content->youtubeThumbnail();
        }

        return $content->family_id === $this->family->id
            ? route('contents.cover', $content)
            : route('lists.public.cover', [$content->list_id, $content->id]);
    }

    /* ---------- Formato ---------- */

    public static function minutes(int $seconds): int
    {
        return (int) round($seconds / 60);
    }

    /** Duración legible: «menos de 1 min», «25 min», «1 h 05 min». */
    public static function duration(int $seconds): string
    {
        if ($seconds > 0 && $seconds < 60) {
            return __('menos de 1 min');
        }

        return UsageStats::format(self::minutes($seconds));
    }

    private function range(CarbonImmutable $from, CarbonImmutable $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->translatedFormat('j M');
        }

        return $from->month === $to->month
            ? $from->day.' al '.$to->translatedFormat('j M')
            : $from->translatedFormat('j M').' al '.$to->translatedFormat('j M');
    }
}
