<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'list_id', 'type', 'title', 'description', 'status', 'status_message',
    'file_path', 'mime', 'file_size', 'duration', 'cover_path', 'youtube_id', 'position',
])]
class Content extends Model
{
    public const TYPE_VIDEO = 'video';

    public const TYPE_AUDIO = 'audio';

    public const TYPE_YOUTUBE = 'youtube';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_ERROR = 'error';

    public const MESSAGE_HIDDEN = 'Oculto por un adulto.';

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'duration' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Al borrar un contenido se eliminan también sus archivos privados.
        static::deleted(function (Content $content) {
            $disk = Storage::disk('local');
            foreach (array_filter([$content->file_path, $content->cover_path]) as $path) {
                $disk->delete($path);
            }
        });
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(VideoList::class, 'list_id');
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class);
    }

    public function plays(): HasMany
    {
        return $this->hasMany(Play::class);
    }

    public function scopeReady(Builder $query): void
    {
        $query->where('status', self::STATUS_READY);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderByDesc('id');
    }

    public function isYouTube(): bool
    {
        return $this->type === self::TYPE_YOUTUBE;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isHidden(): bool
    {
        return $this->status === self::STATUS_UNAVAILABLE && $this->status_message === self::MESSAGE_HIDDEN;
    }

    public function youtubeThumbnail(): ?string
    {
        return $this->youtube_id ? 'https://i.ytimg.com/vi/'.$this->youtube_id.'/hqdefault.jpg' : null;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_VIDEO => __('Vídeo'),
            self::TYPE_AUDIO => __('Audio'),
            self::TYPE_YOUTUBE => __('YouTube'),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PROCESSING => __('Procesando'),
            self::STATUS_READY => __('Listo'),
            self::STATUS_UNAVAILABLE => __('No disponible'),
            self::STATUS_ERROR => __('Error'),
        };
    }

    public function formattedDuration(): ?string
    {
        if (! $this->duration) {
            return null;
        }

        $h = intdiv($this->duration, 3600);
        $m = intdiv($this->duration % 3600, 60);
        $s = $this->duration % 60;

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}
