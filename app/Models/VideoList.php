<?php

namespace App\Models;

use App\Services\YouTubeSearch;
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
 *
 * Toda lista propia está en uno o varios idiomas; las públicas indican además una edad
 * recomendada (de age_min a age_max años).
 */
#[Fillable(['name', 'description', 'position', 'is_public', 'source_id', 'languages', 'age_min', 'age_max'])]
class VideoList extends Model
{
    protected $table = 'lists';

    /** Idiomas que se ofrecen primero al crear una lista; el resto, en «Más idiomas». */
    public const MAIN_LANGUAGES = ['es', 'en', 'eu', 'ca', 'gl', 'fr', 'zxx'];

    public const AGE_MIN = 0;

    public const AGE_MAX = 12;

    /**
     * Etapas de edad: las de los perfiles infantiles y las del filtro de listas públicas
     * (salen las listas cuya edad se solapa con la etapa).
     */
    public const AGE_RANGES = ['0-3' => [0, 3], '3-6' => [3, 6], '7-12' => [7, 12]];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'position' => 'integer',
            'languages' => 'array',
            'age_min' => 'integer',
            'age_max' => 'integer',
        ];
    }

    /** Idiomas posibles de una lista: los del buscador y «sin diálogo» (código ISO zxx). */
    public static function languageOptions(): array
    {
        return YouTubeSearch::languages() + ['zxx' => __('Sin diálogo')];
    }

    public static function ageRangeLabel(int $min, int $max): string
    {
        return $min === $max ? trans_choice(':count año|:count años', $min) : __(':min–:max años', ['min' => $min, 'max' => $max]);
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

    /** Códigos de idioma de la lista (en una enlazada, los de su original). */
    public function languageCodes(): array
    {
        return ($this->isLinked() ? $this->source?->languages : $this->languages) ?? [];
    }

    /** Idiomas para mostrar, por ejemplo «Español, Inglés». */
    public function languageNames(): string
    {
        $options = self::languageOptions();

        return collect($this->languageCodes())->map(fn ($code) => $options[$code] ?? $code)->join(', ');
    }

    public function hasAgeRange(): bool
    {
        $list = $this->isLinked() ? $this->source : $this;

        return $list?->age_min !== null && $list->age_max !== null;
    }

    /** Edad recomendada para mostrar, por ejemplo «3–6 años», o null si no se ha indicado. */
    public function ageLabel(): ?string
    {
        $list = $this->isLinked() ? $this->source : $this;

        return $this->hasAgeRange() ? self::ageRangeLabel($list->age_min, $list->age_max) : null;
    }

    /**
     * Si la edad recomendada se solapa con una etapa (de $min a $max años). Las etapas
     * comparten los extremos («0–3» y «3–6»), así que tocarse en un extremo no basta: una
     * lista de 0–3 años no es de la etapa 3–6, salvo que sea de un solo año o de dos seguidos.
     */
    public function suitsAges(int $min, int $max): bool
    {
        if (! $this->hasAgeRange()) {
            return false;
        }

        $from = max($this->age_min, $min);
        $to = min($this->age_max, $max);

        return $from < $to || ($from === $to && $this->age_max - $this->age_min <= 1);
    }
}
