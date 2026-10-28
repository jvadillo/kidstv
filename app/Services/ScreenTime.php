<?php

namespace App\Services;

use App\Models\Play;
use App\Models\Profile;

/**
 * Límite diario de tele, sumando lo visto en todas las teles de la familia: un tiempo
 * compartido entre todos los niños o uno para cada niño. Lo visto desde la Zona de
 * adultos no cuenta.
 */
class ScreenTime
{
    // Por debajo de este margen se da el tiempo por agotado: los avisos de la tele van
    // redondeados y no tiene sentido dejar empezar un vídeo para unos segundos.
    private const ROUNDING_SECONDS = 15;

    /**
     * Estado del límite para un perfil, o null si la familia no tiene límite.
     *
     * @return array{limit_seconds: int, remaining_seconds: int, warn_seconds: int}|null
     */
    public function status(Profile $profile): ?array
    {
        $minutes = $profile->family->dailyLimitFor($profile);
        if ($minutes === null) {
            return null;
        }

        $limit = $minutes * 60;
        $remaining = $limit - $this->usedToday($profile);

        return [
            'limit_seconds' => $limit,
            'remaining_seconds' => $remaining < self::ROUNDING_SECONDS ? 0 : $remaining,
            // Aviso con una quinta parte del límite por delante: 1 min de 5, 5 min de una hora o más.
            'warn_seconds' => min(300, max(60, intdiv($limit, 5))),
        ];
    }

    public function isExhausted(Profile $profile): bool
    {
        $status = $this->status($profile);

        return $status !== null && $status['remaining_seconds'] === 0;
    }

    /** Segundos vistos hoy por el niño o, con tiempo compartido, por todos (también sin perfil, de apps antiguas). */
    public function usedToday(Profile $profile): int
    {
        return (int) Play::where('family_id', $profile->family_id)
            ->when($profile->family->daily_limit_mode !== 'shared', fn ($query) => $query->where('profile_id', $profile->id))
            ->where('adult', false)
            ->where('created_at', '>=', today())
            ->sum('seconds');
    }
}
