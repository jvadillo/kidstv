<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kind', 'original_name', 'size', 'received', 'expires_at'])]
class Upload extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'received' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /** Ruta temporal (relativa al disco local) donde se van acumulando los fragmentos. */
    public function tempPath(): string
    {
        return 'uploads/'.$this->id.'.part';
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isComplete(): bool
    {
        return $this->received === $this->size;
    }
}
