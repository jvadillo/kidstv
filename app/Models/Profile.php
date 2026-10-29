<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'avatar', 'position', 'daily_limit_minutes', 'age_range'])]
class Profile extends Model
{
    // Solo se aplica con un límite diario para cada niño (Family::$daily_limit_mode).
    protected $attributes = ['daily_limit_minutes' => 60];

    protected function casts(): array
    {
        return ['daily_limit_minutes' => 'integer'];
    }

    protected static function booted(): void
    {
        // Sin clave foránea en plays: el tiempo visto se conserva, pero ya sin el niño.
        static::deleting(fn (Profile $profile) => Play::where('profile_id', $profile->id)->update(['profile_id' => null]));
    }

    /** Etapa de edad para mostrar, por ejemplo «3–6 años», o null si no se ha indicado. */
    public function ageLabel(): ?string
    {
        $range = VideoList::AGE_RANGES[$this->age_range] ?? null;

        return $range ? VideoList::ageRangeLabel(...$range) : null;
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class);
    }

    /** Listas públicas enlazadas que ve este perfil. */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(VideoList::class, 'list_profile', 'profile_id', 'list_id');
    }
}
