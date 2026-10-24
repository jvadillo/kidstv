<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['name', 'platform', 'token_hash', 'user_agent', 'last_seen_at'])]
#[Hidden(['token_hash'])]
class Device extends Model
{
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'pin_locked_until' => 'datetime',
        ];
    }

    /**
     * Crea un dispositivo para la familia y devuelve también su token en claro,
     * que solo se entrega una vez al televisor (en BD queda únicamente el hash).
     *
     * @return array{0: Device, 1: string}
     */
    public static function issue(Family $family, string $name, string $platform, ?string $userAgent): array
    {
        $plain = Str::random(64);

        $device = $family->devices()->create([
            'name' => $name,
            'platform' => $platform,
            'token_hash' => hash('sha256', $plain),
            'user_agent' => Str::limit((string) $userAgent, 490, ''),
        ]);

        return [$device, $plain];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function platformLabel(): string
    {
        return match ($this->platform) {
            'webos' => 'LG webOS',
            'tizen' => 'Samsung Tizen',
            'androidtv' => 'Android TV',
            default => __('Navegador'),
        };
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(3));
    }
}
