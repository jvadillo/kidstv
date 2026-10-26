<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lista de vídeos de una familia: en la tele, una fila. («List» no puede ser nombre de
 * clase en PHP; la tabla es «lists».)
 *
 * Una lista puede ser pública (la publica un administrador) o estar enlazada a una lista
 * pública de otra familia (source_id): en ese caso no tiene contenidos propios, muestra
 * tal cual los del original y solo decide qué perfiles la ven.
 */
#[Fillable(['name', 'description', 'position', 'is_public', 'source_id'])]
class VideoList extends Model
{
    protected $table = 'lists';

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Sin clave foránea en source_id: al borrar una lista pública se quitan sus enlaces.
        static::deleting(function (VideoList $list) {
            static::where('source_id', $list->id)->get()->each->delete();
        });
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'list_id');
    }

    /** Lista pública original de una lista enlazada. */
    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_id');
    }

    /** Enlaces que otras familias tienen a esta lista pública. */
    public function links(): HasMany
    {
        return $this->hasMany(self::class, 'source_id');
    }

    /** Perfiles que ven una lista enlazada (las listas propias se asignan vídeo a vídeo). */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'list_profile', 'list_id', 'profile_id');
    }

    public function scopeOwn(Builder $query): void
    {
        $query->whereNull('source_id');
    }

    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true)->whereNull('source_id');
    }

    public function isLinked(): bool
    {
        return $this->source_id !== null;
    }

    /** Una lista enlazada cuyo original ha dejado de ser público no muestra nada. */
    public function isAvailable(): bool
    {
        return ! $this->isLinked() || (bool) $this->source?->is_public;
    }

    public function displayName(): string
    {
        return $this->isLinked() ? (string) $this->source?->name : $this->name;
    }
}
