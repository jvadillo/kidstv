<?php

namespace App\Http\Controllers\Tv;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\TvCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Zona de adultos en la TV, protegida por el PIN familiar. Se valida en el servidor:
 * aunque se manipule la app, sin PIN correcto no hay token de adulto. Incluso con PIN,
 * la TV no puede administrar la cuenta: solo ver todo el catálogo o desvincularse.
 */
class AdultZoneController extends Controller
{
    public function unlock(Request $request)
    {
        $device = $this->device($request);
        $family = $device->family;

        if (! $family->hasPin()) {
            return response()->json([
                'error' => 'no_pin',
                'message' => __('Todavía no hay PIN parental. Configúralo desde el panel en el móvil u ordenador.'),
            ], 409);
        }

        if ($device->pin_locked_until && $device->pin_locked_until->isFuture()) {
            return $this->lockedResponse($device);
        }

        $request->validate(['pin' => ['required', 'string', 'max:6']]);

        if (! $family->checkPin((string) $request->input('pin'))) {
            $failures = $device->pin_failures + 1;
            $threshold = config('kidstv.pin_max_failures_before_lock');
            $device->pin_failures = min($failures, 255);

            // Espera progresiva: 30 s, 60 s, 120 s... hasta 15 minutos.
            if ($failures >= $threshold) {
                $seconds = min(
                    config('kidstv.pin_lock_base_seconds') * (2 ** ($failures - $threshold)),
                    config('kidstv.pin_lock_max_seconds'),
                );
                $device->pin_locked_until = now()->addSeconds($seconds);
            }
            $device->save();

            if ($device->pin_locked_until && $device->pin_locked_until->isFuture()) {
                return $this->lockedResponse($device);
            }

            return response()->json([
                'error' => 'wrong_pin',
                'message' => __('PIN incorrecto.'),
                'attempts_left' => $threshold - $failures,
            ], 422);
        }

        $device->forceFill(['pin_failures' => 0, 'pin_locked_until' => null])->save();

        $token = Str::random(40);
        Cache::put($this->cacheKey($device), hash('sha256', $token), config('kidstv.adult_mode_idle_seconds'));

        return response()->json([
            'adult_token' => $token,
            'idle_seconds' => config('kidstv.adult_mode_idle_seconds'),
        ]);
    }

    public function lock(Request $request)
    {
        Cache::forget($this->cacheKey($this->device($request)));

        return response()->json(['ok' => true]);
    }

    /** La TV lo consulta mientras se usa la zona de adultos: renueva o confirma el bloqueo. */
    public function status(Request $request)
    {
        return response()->json(['unlocked' => self::isUnlocked($request, $this->device($request))]);
    }

    public function catalog(Request $request, TvCatalog $catalog)
    {
        $device = $this->device($request);
        if (! self::isUnlocked($request, $device)) {
            return $this->lockedZone();
        }

        $contents = $device->family->contents()->ready()->ordered()->get();

        return response()->json($catalog->present($contents, $device));
    }

    /** Desvincular este mismo televisor (requiere PIN). */
    public function unlink(Request $request)
    {
        $device = $this->device($request);
        if (! self::isUnlocked($request, $device)) {
            return $this->lockedZone();
        }

        Cache::forget($this->cacheKey($device));
        $device->delete();

        return response()->json(['ok' => true]);
    }

    /** Comprueba el token de adulto y renueva su caducidad por inactividad. */
    public static function isUnlocked(Request $request, Device $device): bool
    {
        $token = (string) $request->header('X-Adult-Token');
        $key = 'tv-adult:'.$device->id;
        $hash = Cache::get($key);

        if ($token === '' || ! is_string($hash) || ! hash_equals($hash, hash('sha256', $token))) {
            return false;
        }

        Cache::put($key, $hash, config('kidstv.adult_mode_idle_seconds'));

        return true;
    }

    private function cacheKey(Device $device): string
    {
        return 'tv-adult:'.$device->id;
    }

    private function lockedResponse(Device $device)
    {
        $seconds = max(1, (int) ceil(now()->diffInSeconds($device->pin_locked_until, false)));

        return response()->json([
            'error' => 'locked',
            'message' => __('Demasiados intentos. Espera :seconds segundos.', ['seconds' => $seconds]),
            'retry_after' => $seconds,
        ], 423);
    }

    private function lockedZone()
    {
        return response()->json(['error' => 'adult_locked', 'message' => __('La Zona de adultos está bloqueada.')], 403);
    }

    private function device(Request $request): Device
    {
        return $request->attributes->get('device');
    }
}
