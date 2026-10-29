<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'secret_hash', 'platform', 'user_agent', 'device_id', 'device_token', 'expires_at', 'claimed_at', 'delivered_at'])]
#[Hidden(['secret_hash', 'device_token'])]
class DevicePairing extends Model
{
    use HasUuids;

    // Alfabeto sin caracteres ambiguos (0/O, 1/I/L) para leerlo cómodamente en la tele.
    public const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const CODE_LENGTH = 6;

    protected function casts(): array
    {
        return [
            'draft' => 'array',
            'device_token' => 'encrypted',
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isClaimable(): bool
    {
        return $this->claimed_at === null && $this->expires_at->isFuture();
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public function formattedCode(): string
    {
        return substr($this->code, 0, 3).' '.substr($this->code, 3);
    }
}
