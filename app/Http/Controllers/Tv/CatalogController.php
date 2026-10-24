<?php

namespace App\Http\Controllers\Tv;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Device;
use App\Services\TvCatalog;
use Illuminate\Http\Request;

/** Consulta de perfiles y catálogo, y reproducción. Es todo lo que puede hacer un televisor. */
class CatalogController extends Controller
{
    public function __construct(private TvCatalog $catalog) {}

    public function session(Request $request)
    {
        $device = $this->device($request);
        $family = $device->family;

        return response()->json([
            'device' => ['id' => $device->id, 'name' => $device->name],
            'family' => [
                'name' => $family->name,
                'has_pin' => $family->hasPin(),
                'autoplay_next' => $family->autoplay_next,
            ],
            'profiles' => $family->profiles->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'avatar' => $p->avatar]),
            'adult_idle_seconds' => config('kidstv.adult_mode_idle_seconds'),
            'manage_url' => route('panel'),
        ]);
    }

    public function catalog(Request $request, int $profile)
    {
        $device = $this->device($request);
        $profile = $device->family->profiles()->findOrFail($profile);

        $contents = $profile->contents()->ready()->ordered()->get();

        return response()->json(['profile' => ['id' => $profile->id, 'name' => $profile->name, 'avatar' => $profile->avatar]]
            + $this->catalog->present($contents, $device));
    }

    public function play(Request $request, int $content)
    {
        $device = $this->device($request);
        $content = $device->family->contents()->find($content);

        if (! $content || ! $content->isReady()) {
            return response()->json(['error' => 'content_unavailable', 'message' => __('Este contenido ya no está disponible.')], 404);
        }

        // Fuera de la Zona de adultos, solo se reproduce lo autorizado para el perfil activo.
        if (! AdultZoneController::isUnlocked($request, $device)) {
            $allowed = $content->profiles()->whereKey((int) $request->query('profile'))->exists();
            if (! $allowed) {
                return response()->json(['error' => 'content_unavailable', 'message' => __('Este contenido ya no está disponible.')], 404);
            }
        }

        return response()->json([
            'id' => $content->id,
            'type' => $content->type,
            'title' => $content->title,
            'cover' => $this->catalog->coverUrl($content, $device),
            'url' => $content->isYouTube() ? null : $this->catalog->fileUrl($content, $device),
            'mime' => $content->mime,
            'youtube_id' => $content->youtube_id,
            'youtube_player' => $content->isYouTube() ? route('tv.youtube', ['v' => $content->youtube_id]) : null,
        ]);
    }

    /**
     * La TV informa de que YouTube rechaza un vídeo (eliminado, privado o sin permiso
     * de inserción). Se marca como no disponible para que el adulto lo vea en el panel.
     */
    public function problem(Request $request, int $content)
    {
        $device = $this->device($request);
        $content = $device->family->contents()->findOrFail($content);
        $code = (int) $request->input('code');

        if ($content->isYouTube() && in_array($code, [100, 101, 150], true)) {
            $content->update([
                'status' => Content::STATUS_UNAVAILABLE,
                'status_message' => $code === 100
                    ? __('YouTube indica que el vídeo ya no existe o es privado.')
                    : __('El propietario del vídeo no permite reproducirlo fuera de YouTube.'),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function device(Request $request): Device
    {
        return $request->attributes->get('device');
    }
}
