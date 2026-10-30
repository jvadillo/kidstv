<?php

namespace App\Jobs;

use App\Models\Content;
use App\Services\MediaLibrary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Valida un archivo propio recién subido con ffprobe, lo prepara para streaming
 * (moov al principio) y genera portada si el adulto no subió ninguna.
 * No recodifica: el servidor es compartido y la CPU limitada.
 */
class ProcessUploadedMedia implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    // Códecs que los televisores y navegadores actuales reproducen sin problemas en <video>/<audio>.
    private const SAFE_VIDEO_CODECS = ['h264'];

    private const OTHER_PLAYABLE_VIDEO_CODECS = ['hevc', 'vp9', 'av1'];

    public function __construct(public int $contentId) {}

    public function handle(MediaLibrary $library): void
    {
        $content = Content::find($this->contentId);
        if (! $content || ! $content->file_path) {
            return;
        }

        $disk = Storage::disk('local');
        $path = $disk->path($content->file_path);

        if (! $this->toolsAvailable()) {
            // Entorno sin ffmpeg (desarrollo/tests): se acepta tal cual.
            $content->update(['status' => Content::STATUS_READY, 'status_message' => null]);

            return;
        }

        $probe = $this->probe($path);
        if ($probe === null) {
            $this->markError($content, $content->type === Content::TYPE_AUDIO
                ? __('El archivo no es un audio válido. Usa MP3 o M4A.')
                : __('El archivo no es un vídeo MP4 válido.'));

            return;
        }

        $streams = collect($probe['streams'] ?? []);
        $video = $streams->first(fn ($s) => ($s['codec_type'] ?? null) === 'video' && empty($s['disposition']['attached_pic']));
        $audio = $streams->first(fn ($s) => ($s['codec_type'] ?? null) === 'audio');
        $duration = (int) round((float) ($probe['format']['duration'] ?? 0));
        $message = null;

        if ($content->type === Content::TYPE_VIDEO) {
            if (! $video) {
                $this->markError($content, __('El archivo no contiene ninguna pista de vídeo.'));

                return;
            }

            $codec = $video['codec_name'] ?? '';
            if (! in_array($codec, self::SAFE_VIDEO_CODECS, true)) {
                if (! in_array($codec, self::OTHER_PLAYABLE_VIDEO_CODECS, true)) {
                    $this->markError($content, __('El vídeo usa un formato (:codec) que los televisores no reproducen. Conviértelo a MP4 H.264/AAC.', ['codec' => $codec]));

                    return;
                }
                $message = __('Vídeo :codec: se reproduce en la mayoría de televisores recientes, pero H.264 es el formato más compatible.', ['codec' => strtoupper($codec)]);
            }

            $this->moveMoovAtomToFront($path);
        } elseif (! $audio) {
            $this->markError($content, __('El archivo no contiene ninguna pista de audio.'));

            return;
        }

        if (! $content->cover_path) {
            $cover = $library->newCoverPath($content->family);
            if ($this->extractCover($path, $disk->path($cover), $content->type === Content::TYPE_VIDEO ? $duration : null)) {
                $content->cover_path = $cover;
            }
        }

        $content->fill([
            'duration' => $duration ?: null,
            'file_size' => filesize($path) ?: $content->file_size,
            'status' => Content::STATUS_READY,
            'status_message' => $message,
        ])->save();
    }

    public function failed(?Throwable $e): void
    {
        Log::error('Fallo procesando contenido', ['content' => $this->contentId, 'error' => $e?->getMessage()]);

        Content::whereKey($this->contentId)->update([
            'status' => Content::STATUS_ERROR,
            'status_message' => __('No se pudo procesar el archivo.'),
        ]);
    }

    private function markError(Content $content, string $message): void
    {
        $content->update(['status' => Content::STATUS_ERROR, 'status_message' => $message]);
    }

    private function toolsAvailable(): bool
    {
        return is_executable(config('nokoplay.ffprobe')) && is_executable(config('nokoplay.ffmpeg'));
    }

    private function probe(string $path): ?array
    {
        $process = new Process([config('nokoplay.ffprobe'), '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path]);
        $process->setTimeout(60)->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $data = json_decode($process->getOutput(), true);

        return is_array($data) && ! empty($data['streams']) ? $data : null;
    }

    /** Remux sin recodificar con +faststart para que el vídeo empiece a reproducirse sin descargarlo entero. */
    private function moveMoovAtomToFront(string $path): void
    {
        $tmp = $path.'.faststart.mp4';
        $process = new Process([
            config('nokoplay.ffmpeg'), '-v', 'error', '-y', '-i', $path,
            '-map', '0:v:0', '-map', '0:a?', '-c', 'copy', '-movflags', '+faststart', $tmp,
        ]);
        $process->setTimeout(600)->run();

        if ($process->isSuccessful() && is_file($tmp) && filesize($tmp) > 0) {
            rename($tmp, $path);
            @chmod($path, 0644);
        } else {
            @unlink($tmp);
        }
    }

    private function extractCover(string $source, string $target, ?int $videoDuration): bool
    {
        $args = [config('nokoplay.ffmpeg'), '-v', 'error', '-y'];
        if ($videoDuration !== null) {
            $args = [...$args, '-ss', (string) max(1, (int) ($videoDuration * 0.15))];
        }
        // En audio, la portada es la imagen incrustada en el archivo (si la hay).
        $args = [...$args, '-i', $source, '-an', '-frames:v', '1', '-vf', 'scale=min(960\,iw):-2', $target];

        $process = new Process($args);
        $process->setTimeout(60)->run();

        $ok = $process->isSuccessful() && is_file($target) && filesize($target) > 0;
        if ($ok) {
            @chmod($target, 0644);
        } else {
            @unlink($target);
        }

        return $ok;
    }
}
