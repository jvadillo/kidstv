<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reconocimiento de enlaces de YouTube y consulta de datos públicos mediante oEmbed.
 * Nunca descarga vídeos: solo obtiene título/autor y comprueba si se permite incrustarlos.
 */
class YouTube
{
    public static function parseId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }

        $patterns = [
            '~^(?:https?://)?(?:www\.|m\.|music\.)?youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})~i',
            '~^(?:https?://)?(?:www\.|m\.)?youtube\.com/(?:shorts|embed|live|v)/([A-Za-z0-9_-]{11})~i',
            '~^(?:https?://)?(?:www\.)?youtube-nocookie\.com/embed/([A-Za-z0-9_-]{11})~i',
            '~^(?:https?://)?youtu\.be/([A-Za-z0-9_-]{11})~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * @return array{ok: bool, checked: bool, title: ?string, author: ?string, error: ?string}
     */
    public function lookup(string $id): array
    {
        try {
            $response = Http::timeout(6)
                ->acceptJson()
                ->get('https://www.youtube.com/oembed', [
                    'format' => 'json',
                    'url' => 'https://www.youtube.com/watch?v='.$id,
                ]);
        } catch (Throwable) {
            // Sin conexión con YouTube: se permite guardar, la TV informará si no se puede reproducir.
            return ['ok' => true, 'checked' => false, 'title' => null, 'author' => null, 'error' => null];
        }

        if ($response->successful()) {
            return [
                'ok' => true,
                'checked' => true,
                'title' => mb_substr((string) $response->json('title'), 0, 120) ?: null,
                'author' => $response->json('author_name'),
                'error' => null,
            ];
        }

        $error = match ($response->status()) {
            401 => __('El propietario de este vídeo no permite reproducirlo fuera de YouTube.'),
            403 => __('Este vídeo es privado y no se puede reproducir.'),
            default => __('No encontramos este vídeo. Comprueba que el enlace es correcto y que el vídeo es público u oculto.'),
        };

        return ['ok' => false, 'checked' => true, 'title' => null, 'author' => null, 'error' => $error];
    }
}
