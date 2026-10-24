<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'position'])]
class Category extends Model
{
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }
}
