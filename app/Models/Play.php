<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una reproducción en un televisor y los segundos que se han visto (estadísticas de uso).
 * No guarda el perfil infantil activo: la política de privacidad lo descarta.
 */
#[Fillable(['family_id', 'device_id', 'content_id', 'content_type', 'client_key', 'seconds', 'adult'])]
class Play extends Model
{
    protected function casts(): array
    {
        return [
            'seconds' => 'integer',
            'adult' => 'boolean',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
