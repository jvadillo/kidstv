<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticación de televisores mediante token de dispositivo. El token solo da acceso
 * a la API de consulta y reproducción (/api/tv), nunca al panel de administración.
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $device = $token ? Device::with('family')->where('token_hash', hash('sha256', $token))->first() : null;

        if (! $device) {
            return response()->json([
                'error' => $token ? 'device_revoked' : 'unauthenticated',
                'message' => $token
                    ? __('Este televisor ya no está vinculado a ninguna familia.')
                    : __('Este televisor no está vinculado.'),
            ], 401);
        }

        if ($device->family->provisional_until?->isPast()) {
            return response()->json(['error' => 'device_revoked', 'message' => __('La configuración provisional ha caducado. Prepara de nuevo esta tele.')], 401);
        }

        // Cuenta bloqueada por un administrador: la tele sigue vinculada, pero sin servicio.
        if ($device->family->isBlocked()) {
            return response()->json([
                'error' => 'account_blocked',
                'message' => __('Esta videoteca está suspendida. El adulto responsable puede escribir al soporte de NokoPlay.'),
            ], 403);
        }

        if (! $device->last_seen_at || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill(['last_seen_at' => now()])->saveQuietly();
            if ($device->family->provisional_until) {
                $device->family->forceFill(['provisional_until' => now()->addDays(30)])->saveQuietly();
            }
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
