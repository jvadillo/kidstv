<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

#[Fillable(['name', 'autoplay_next'])]
#[Hidden(['pin_hash'])]
class Family extends Model
{
    protected function casts(): array
    {
        return [
            'autoplay_next' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class)->orderBy('position')->orderBy('id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('position')->orderBy('id');
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
