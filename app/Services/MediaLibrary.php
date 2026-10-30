<?php

namespace App\Services;

use App\Models\Content;
use App\Models\Family;
use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Almacenamiento de los archivos propios de cada familia en el disco local del VPS
 * (privado, fuera de la carpeta pública) y control de cuotas.
 */
class MediaLibrary
{
    public function maxBytes(string $kind): int
    {
        $mb = $kind === Content::TYPE_AUDIO ? config('nokoplay.max_audio_mb') : config('nokoplay.max_video_mb');

        return $mb * 1024 * 1024;
    }

    public function quotaBytes(): int
    {
        return config('nokoplay.family_quota_mb') * 1024 * 1024;
    }

    public function extensions(string $kind): array
    {
        return config($kind === Content::TYPE_AUDIO ? 'nokoplay.audio_extensions' : 'nokoplay.video_extensions');
    }

    /** Devuelve un mensaje de error si la familia o el servidor no pueden aceptar $bytes más. */
    public function capacityError(Family $family, int $bytes): ?string
    {
        $pending = (int) $family->uploads()->sum('size');

        if ($family->storageUsed() + $pending + $bytes > $this->quotaBytes()) {
            return __('No queda espacio en tu videoteca (límite de :quota). Elimina algún archivo para subir más.', [
                'quota' => format_bytes($this->quotaBytes()),
            ]);
        }

        $free = @disk_free_space(Storage::disk('local')->path(''));
        if ($free !== false && $free - $bytes < config('nokoplay.min_free_disk_mb') * 1024 * 1024) {
            return __('El servidor no tiene espacio suficiente en este momento. Inténtalo más tarde o con un archivo más pequeño.');
        }

        return null;
    }

    /** Mueve una subida completa a su ubicación definitiva y devuelve la ruta relativa. */
    public function finalizeUpload(Upload $upload): string
    {
        $path = $upload->family->storageDirectory().'/media/'.Str::random(32).'.'.$upload->extension();

        Storage::disk('local')->makeDirectory(dirname($path));
        rename(Storage::disk('local')->path($upload->tempPath()), Storage::disk('local')->path($path));

        return $path;
    }

    /** Guarda una portada subida por el adulto, reducida a un tamaño razonable para TV. */
    public function storeCover(Family $family, UploadedFile $file): string
    {
        $disk = Storage::disk('local');
        $path = $family->storageDirectory().'/covers/'.Str::random(32).'.jpg';
        $disk->makeDirectory(dirname($path));

        if (! $this->resizeToJpeg($file->getRealPath(), $disk->path($path), 960)) {
            $path = $family->storageDirectory().'/covers/'.Str::random(32).'.'.$file->extension();
            $disk->putFileAs(dirname($path), $file, basename($path));
        }

        return $path;
    }

    public function newCoverPath(Family $family): string
    {
        $path = $family->storageDirectory().'/covers/'.Str::random(32).'.jpg';
        Storage::disk('local')->makeDirectory(dirname($path));

        return $path;
    }

    /**
     * Respuesta con el archivo privado. En producción la entrega nginx (X-Accel-Redirect),
     * que admite peticiones parciales para avanzar y retroceder en vídeos y audios.
     */
    public function respond(string $path, ?string $mime, string $cache = 'private, max-age=3600'): Response
    {
        $disk = Storage::disk('local');
        abort_unless($path !== '' && $disk->exists($path), 404);

        $mime ??= $disk->mimeType($path) ?: 'application/octet-stream';

        if (config('nokoplay.accel_redirect')) {
            return response('', 200, [
                'Content-Type' => $mime,
                'X-Accel-Redirect' => config('nokoplay.accel_prefix').$path,
                'Cache-Control' => $cache,
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Cache-Control' => $cache,
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function deleteFamilyFiles(Family $family): void
    {
        Storage::disk('local')->deleteDirectory($family->storageDirectory());

        foreach ($family->uploads as $upload) {
            Storage::disk('local')->delete($upload->tempPath());
        }
    }

    private function resizeToJpeg(string $source, string $target, int $maxWidth): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        $data = @file_get_contents($source);
        $image = $data !== false ? @imagecreatefromstring($data) : false;
        if (! $image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width > $maxWidth) {
            $resized = imagescale($image, $maxWidth, (int) round($height * $maxWidth / $width));
            $image = $resized;
        }

        $ok = imagejpeg($image, $target, 82);
        @chmod($target, 0644);

        return $ok;
    }
}
