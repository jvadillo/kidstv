<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Play;

/**
 * Límite diario de tele: cuenta lo reproducido hoy en cada televisor fuera de la Zona de
 * adultos. Se calcula con los mismos registros de uso, sin guardar qué perfil está viendo.
 */
class ScreenTime
{
    // Por debajo de este margen se da el tiempo por agotado: los avisos de la tele van
    // redondeados y no tiene sentido dejar empezar un vídeo para unos segundos.
    private const ROUNDING_SECONDS = 15;

    /**
     * Estado del límite para un televisor, o null si la familia no tiene límite.
     *
     * @return array{limit_seconds: int, remaining_seconds: int, warn_seconds: int}|null
     */
    public function status(Device $device): ?array
    {
        $minutes = $device->family->dailyLimit();
        if ($minutes === null) {
            return null;
        }

        $limit = $minutes * 60;
        $remaining = $limit - $this->usedToday($device);

        return [
            'limit_seconds' => $limit,
            'remaining_seconds' => $remaining < self::ROUNDING_SECONDS ? 0 : $remaining,
            // Aviso con una quinta parte del límite por delante: 1 min de 5, 5 min de una hora o más.
            'warn_seconds' => min(300, max(60, intdiv($limit, 5))),
        ];
    }

    public function isExhausted(Device $device): bool
    {
        $status = $this->status($device);

        return $status !== null && $status['remaining_seconds'] === 0;
    }

    public function usedToday(Device $device): int
    {
        return (int) Play::where('device_id', $device->id)
            ->where('adult', false)
            ->where('created_at', '>=', today())
            ->sum('seconds');
    }
}
