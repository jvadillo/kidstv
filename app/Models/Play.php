<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una reproducción en un televisor, el perfil infantil que la veía y los segundos vistos.
 * Sirve para el límite diario de cada niño, la pantalla «Actividad» de su familia y las
 * estadísticas de uso (estas, siempre agregadas y sin perfil).
 */
#[Fillable(['family_id', 'device_id', 'profile_id', 'content_id', 'content_type', 'client_key', 'seconds', 'adult'])]
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

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
