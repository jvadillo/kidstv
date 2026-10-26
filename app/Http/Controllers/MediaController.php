<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\MediaLibrary;
use App\Services\TvCatalog;
use Illuminate\Http\Request;

/**
 * Entrega de archivos privados a televisores mediante URL firmada. Además de la firma,
 * se comprueba en cada petición que el televisor sigue vinculado a la familia
 * propietaria (o que ha añadido la lista pública que lo contiene) y que el contenido
 * sigue disponible.
 */
class MediaController extends Controller
{
    public function __invoke(Request $request, MediaLibrary $library, TvCatalog $catalog, int $content, string $variant)
    {
        $device = Device::find((int) $request->query('d'));
        abort_unless($device && ! $device->family->isBlocked(), 403);

        $content = $catalog->find($device->family, $content) ?? abort(404);

        if ($variant === 'cover') {
            abort_unless($content->cover_path, 404);

            return $library->respond($content->cover_path, null, 'private, max-age=86400');
        }

        abort_unless($content->isReady() && $content->file_path, 404);

        return $library->respond($content->file_path, $content->mime, 'private, no-store');
    }
}
