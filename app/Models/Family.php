<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

#[Fillable(['name', 'autoplay_next', 'youtube_controls', 'daily_limit_enabled', 'daily_limit_minutes'])]
#[Hidden(['pin_hash'])]
class Family extends Model
{
    public const YOUTUBE_CONTROLS = ['auto', 'always', 'hidden'];

    /** Valores del control deslizante del límite diario, en minutos. */
    public const DAILY_LIMIT_OPTIONS = [5, 10, 15, 20, 30, 40, 50, 60, 75, 90, 105, 120, 150, 300];

    protected $attributes = ['youtube_controls' => 'auto', 'daily_limit_enabled' => false, 'daily_limit_minutes' => 60];

    protected function casts(): array
    {
        return [
            'autoplay_next' => 'boolean',
            'daily_limit_enabled' => 'boolean',
            'daily_limit_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Las listas se borran en cascada en la base de datos (sin eventos): antes se quitan
        // los enlaces que otras familias tengan a las listas públicas de esta.
        static::deleting(function (Family $family) {
            VideoList::whereIn('source_id', VideoList::where('family_id', $family->id)->select('id'))->delete();
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class)->orderBy('position')->orderBy('id');
    }

    /** Listas de la familia, propias y públicas enlazadas, en el orden de la tele. */
    public function lists(): HasMany
    {
        return $this->hasMany(VideoList::class)->orderBy('position')->orderBy('id');
    }

    /** Listas propias: las únicas en las que la familia puede poner sus contenidos. */
    public function ownLists(): HasMany
    {
        return $this->lists()->whereNull('source_id');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    public function plays(): HasMany
    {
        return $this->hasMany(Play::class);
    }

    /** Sin ningún adulto activo (cuenta bloqueada por un administrador), los televisores dejan de funcionar. */
    public function isBlocked(): bool
    {
        return $this->users()->whereNull('blocked_at')->doesntExist();
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    public function setPin(string $pin): void
    {
        $this->pin_hash = Hash::make($pin);
        $this->save();
    }

    public function checkPin(string $pin): bool
    {
        return $this->pin_hash !== null && Hash::check($pin, $this->pin_hash);
    }

    /** Minutos de tele al día en cada televisor, o null si no hay límite. */
    public function dailyLimit(): ?int
    {
        return $this->daily_limit_enabled ? $this->daily_limit_minutes : null;
    }

    /** Bytes ocupados por los archivos propios de la familia. */
    public function storageUsed(): int
    {
        return (int) $this->contents()->sum('file_size');
    }

    public function storageDirectory(): string
    {
        return 'families/'.$this->id;
    }
}
