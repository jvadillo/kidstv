<?php

namespace App\Services;

use DateInterval;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Buscador de vídeos de YouTube para el panel de adultos, con la API oficial
 * (YouTube Data API v3). Solo devuelve vídeos insertables, con búsqueda segura
 * estricta, y nunca se ofrece en la experiencia infantil.
 *
 * Cada búsqueda consume ~101 unidades de cuota (search + videos), por eso los
 * resultados se guardan en caché una hora.
 */
class YouTubeSearch
{
    public const DURATIONS = ['any', 'short', 'medium', 'long'];

    /** Idiomas que el buscador ofrece primero; el resto va después, por orden alfabético. */
    public const MAIN_LANGUAGES = ['es', 'en', 'eu', 'ca'];

    private const PER_PAGE = 12;

    public static function enabled(): bool
    {
        return filled(config('services.youtube.key'));
    }

    /**
     * @return array{items: list<array<string, mixed>>, next: ?string, prev: ?string}
     *
     * @throws RuntimeException con un mensaje apto para mostrar al adulto
     */
    public function search(string $query, string $duration = 'any', ?string $pageToken = null, ?string $language = null): array
    {
        $duration = in_array($duration, self::DURATIONS, true) ? $duration : 'any';
        $language = array_key_exists((string) $language, self::languages()) ? $language : self::defaultLanguage();
        $key = 'yt-search:'.sha1(mb_strtolower(trim($query)).'|'.$duration.'|'.$pageToken.'|'.$language);

        return Cache::remember($key, now()->addHour(), fn () => $this->fetch($query, $duration, $pageToken, $language));
    }

    /**
     * Duración en segundos de varios vídeos (1 unidad de cuota por cada 50). Si el buscador
     * no está configurado o YouTube falla, devuelve lo que haya podido averiguar.
     *
     * @param  list<string>  $ids
     * @return array<string, int>
     */
    public function durations(array $ids): array
    {
        if (! self::enabled()) {
            return [];
        }

        $durations = [];
        foreach (array_chunk(array_values(array_unique($ids)), 50) as $chunk) {
            try {
                $details = $this->call('videos', ['part' => 'contentDetails', 'id' => implode(',', $chunk)]);
            } catch (RuntimeException) {
                break;
            }
            foreach ($details['items'] ?? [] as $video) {
                if ($seconds = self::parseDuration($video['contentDetails']['duration'] ?? null)) {
                    $durations[$video['id']] = $seconds;
                }
            }
        }

        return $durations;
    }

    /**
     * Idiomas preferidos para los resultados: primero los principales y después el resto
     * por orden alfabético.
     *
     * @return array<string, string>
     */
    public static function languages(): array
    {
        return [
            'es' => __('Español'), 'en' => __('Inglés'), 'eu' => __('Euskera'), 'ca' => __('Catalán'),
            'de' => __('Alemán'), 'ar' => __('Árabe'), 'zh-Hans' => __('Chino'), 'ko' => __('Coreano'),
            'fr' => __('Francés'), 'gl' => __('Gallego'), 'el' => __('Griego'), 'hi' => __('Hindi'),
            'it' => __('Italiano'), 'ja' => __('Japonés'), 'nl' => __('Neerlandés'), 'pl' => __('Polaco'),
            'pt' => __('Portugués'), 'ro' => __('Rumano'), 'ru' => __('Ruso'), 'sv' => __('Sueco'),
            'tr' => __('Turco'), 'uk' => __('Ucraniano'),
        ];
    }

    public static function defaultLanguage(): string
    {
        $language = config('services.youtube.language');

        return array_key_exists((string) $language, self::languages()) ? $language : 'es';
    }

    private function fetch(string $query, string $duration, ?string $pageToken, string $language): array
    {
        $search = $this->call('search', array_filter([
            'part' => 'snippet',
            'type' => 'video',
            'q' => $query,
            'maxResults' => self::PER_PAGE,
            'safeSearch' => 'strict',
            'videoEmbeddable' => 'true',
            'videoSyndicated' => 'true',
            'videoDuration' => $duration,
            'regionCode' => config('services.youtube.region'),
            // Preferencia, no filtro: YouTube prioriza ese idioma pero puede devolver otros.
            'relevanceLanguage' => $language,
            'pageToken' => $pageToken,
        ]));

        $ids = collect($search['items'] ?? [])
            ->filter(fn ($item) => ($item['snippet']['liveBroadcastContent'] ?? 'none') === 'none')
            ->pluck('id.videoId')
            ->filter()
            ->values();

        $items = [];
        if ($ids->isNotEmpty()) {
            $details = $this->call('videos', [
                'part' => 'snippet,contentDetails,statistics,status',
                'id' => $ids->implode(','),
                // Títulos traducidos por el autor al idioma elegido, si los hay.
                'hl' => $language,
            ]);

            $byId = collect($details['items'] ?? [])->keyBy('id');

            // Se respeta el orden de relevancia de la búsqueda.
            foreach ($ids as $id) {
                $video = $byId->get($id);
                if (! $video || ! ($video['status']['embeddable'] ?? false) || ($video['status']['privacyStatus'] ?? '') === 'private') {
                    continue;
                }

                $snippet = $video['snippet'];
                $items[] = [
                    'id' => $id,
                    'title' => $snippet['localized']['title'] ?? $snippet['title'],
                    'channel' => $snippet['channelTitle'] ?? '',
                    'thumbnail' => $snippet['thumbnails']['medium']['url'] ?? 'https://i.ytimg.com/vi/'.$id.'/mqdefault.jpg',
                    'duration' => self::parseDuration($video['contentDetails']['duration'] ?? null),
                    'published_at' => $snippet['publishedAt'] ?? null,
                    'views' => isset($video['statistics']['viewCount']) ? (int) $video['statistics']['viewCount'] : null,
                    'made_for_kids' => (bool) ($video['status']['madeForKids'] ?? false),
                ];
            }
        }

        return [
            'items' => $items,
            'next' => $search['nextPageToken'] ?? null,
            'prev' => $search['prevPageToken'] ?? null,
        ];
    }

    private function call(string $endpoint, array $params): array
    {
        try {
            $response = Http::timeout(8)->acceptJson()
                ->get('https://www.googleapis.com/youtube/v3/'.$endpoint, $params + ['key' => config('services.youtube.key')]);
        } catch (Throwable) {
            throw new RuntimeException(__('No hemos podido conectar con YouTube. Inténtalo de nuevo en un momento.'));
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $reason = $response->json('error.errors.0.reason');
        Log::warning('Error en la API de YouTube', ['endpoint' => $endpoint, 'status' => $response->status(), 'reason' => $reason]);

        throw new RuntimeException(match ($reason) {
            'quotaExceeded', 'dailyLimitExceeded', 'rateLimitExceeded' => __('Se ha alcanzado el límite diario de búsquedas en YouTube. Vuelve a intentarlo mañana o, mientras tanto, añade vídeos pegando su enlace.'),
            'keyInvalid', 'forbidden', 'accessNotConfigured', 'ipRefererBlocked' => __('El buscador de YouTube no está bien configurado en el servidor. Mientras tanto, añade vídeos pegando su enlace.'),
            default => __('YouTube no ha podido completar la búsqueda. Inténtalo de nuevo en un momento.'),
        });
    }

    /** Convierte una duración ISO 8601 (PT1H2M3S) en segundos. */
    public static function parseDuration(?string $iso): ?int
    {
        if (! $iso) {
            return null;
        }

        try {
            $interval = new DateInterval($iso);
        } catch (Throwable) {
            return null;
        }

        return ($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s ?: null;
    }

    public static function formatDuration(?int $seconds): ?string
    {
        if (! $seconds) {
            return null;
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }

    public static function formatViews(?int $views): ?string
    {
        if ($views === null) {
            return null;
        }

        return match (true) {
            $views >= 1_000_000 => __(':n M visualizaciones', ['n' => number_format($views / 1_000_000, $views >= 10_000_000 ? 0 : 1, ',', '.')]),
            $views >= 1_000 => __(':n mil visualizaciones', ['n' => number_format($views / 1_000, 0, ',', '.')]),
            default => trans_choice(':count visualización|:count visualizaciones', $views),
        };
    }
}
