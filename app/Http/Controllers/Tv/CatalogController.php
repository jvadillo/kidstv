<?php

namespace App\Http\Controllers\Tv;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Device;
use App\Models\Play;
use App\Services\TvCatalog;
use Illuminate\Http\Request;

/** Consulta de perfiles y catálogo, y reproducción. Es todo lo que puede hacer un televisor. */
class CatalogController extends Controller
{
    // La TV avisa cada 30 s: el primer aviso no puede traer mucho más que eso.
    private const FIRST_REPORT_MAX_SECONDS = 45;

    private const REPORT_SLACK_SECONDS = 15;

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

        return response()->json(['profile' => ['id' => $profile->id, 'name' => $profile->name, 'avatar' => $profile->avatar]]
            + $this->catalog->present($device, $profile));
    }

    public function play(Request $request, int $content)
    {
        $device = $this->device($request);
        $content = $this->catalog->find($device->family, $content);

        if (! $content || ! $content->isReady()) {
            return response()->json(['error' => 'content_unavailable', 'message' => __('Este contenido ya no está disponible.')], 404);
        }

        // Fuera de la Zona de adultos, solo se reproduce lo autorizado para el perfil activo.
        if (! AdultZoneController::isUnlocked($request, $device)) {
            $profile = $device->family->profiles()->find((int) $request->query('profile'));
            if (! $profile || ! $this->catalog->allowedFor($content, $device->family, $profile)) {
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
            'youtube_player' => $content->isYouTube() ? route('tv.youtube', [
                'v' => $content->youtube_id,
                'controls' => $device->family->youtube_controls,
            ]) : null,
        ]);
    }

    /**
     * La TV informa de que YouTube rechaza un vídeo (eliminado, privado o sin permiso
     * de inserción). Se marca como no disponible para que el adulto lo vea en el panel.
     */
    public function problem(Request $request, int $content)
    {
        $device = $this->device($request);
        $content = $this->catalog->find($device->family, $content) ?? abort(404);
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

    /**
     * La TV informa periódicamente de cuántos segundos lleva vistos de una reproducción
     * (identificada por una clave aleatoria propia). Nunca se cuenta más tiempo del que
     * ha pasado realmente entre dos avisos, aunque se manipule la petición.
     */
    public function watched(Request $request)
    {
        $device = $this->device($request);
        $family = $device->family;
        $data = $request->validate([
            'key' => ['required', 'string', 'alpha_num:ascii', 'min:8', 'max:40'],
            'content_id' => ['required', 'integer'],
            'seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ]);

        $content = $this->catalog->find($family, $data['content_id']) ?? abort(404);

        $play = Play::firstOrNew(['device_id' => $device->id, 'client_key' => $data['key']]);
        if ($play->exists) {
            $elapsed = (int) $play->updated_at->diffInSeconds(now(), true);
            $play->seconds = max($play->seconds, min($data['seconds'], $play->seconds + $elapsed + self::REPORT_SLACK_SECONDS));
            $play->touch();
        } else {
            $play->fill([
                'family_id' => $family->id,
                'content_id' => $content->id,
                'content_type' => $content->type,
                'seconds' => min($data['seconds'], self::FIRST_REPORT_MAX_SECONDS),
            ])->save();
        }

        return response()->json(['ok' => true]);
    }

    private function device(Request $request): Device
    {
        return $request->attributes->get('device');
    }
}
