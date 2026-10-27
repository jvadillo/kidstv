<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'avatar', 'position'])]
class Profile extends Model
{
    protected static function booted(): void
    {
        // Sin clave foránea en plays: el tiempo visto se conserva, pero ya sin el niño.
        static::deleting(fn (Profile $profile) => Play::where('profile_id', $profile->id)->update(['profile_id' => null]));
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
