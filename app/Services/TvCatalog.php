<?php

namespace App\Services;

use App\Models\Content;
use App\Models\Device;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/** Construye las respuestas de catálogo para televisores, con URLs firmadas y temporales. */
class TvCatalog
{
    /**
     * @param  Collection<int, Content>  $contents
     */
    public function present(Collection $contents, Device $device): array
    {
        $categories = $device->family->categories()->get(['id', 'name']);
        $usedIds = $contents->pluck('category_id')->unique();

        $items = $contents->map(fn (Content $c) => [
            'id' => $c->id,
            'type' => $c->type,
            'title' => $c->title,
            'description' => $c->description,
            'category_id' => $c->category_id,
            'duration' => $c->duration,
            'cover' => $this->coverUrl($c, $device),
        ])->values();

        $tabs = $categories->filter(fn ($cat) => $usedIds->contains($cat->id))
            ->map(fn ($cat) => ['id' => $cat->id, 'name' => $cat->name])
            ->values();

        if ($usedIds->contains(null)) {
            $tabs->push(['id' => null, 'name' => __('Otros')]);
        }

        return [
            'categories' => $tabs,
            'items' => $items,
            // Huella del catálogo: la TV la compara para saber si algo ha cambiado.
            'version' => sha1($items->map(fn ($i) => $i['id'].':'.$i['title'].':'.$i['category_id'].':'.$i['cover'])->implode('|').$tabs->toJson()),
        ];
    }

    public function coverUrl(Content $content, Device $device): ?string
    {
        if ($content->cover_path) {
            return $this->signed($content, 'cover', $device);
        }

        return $content->youtubeThumbnail();
    }

    public function fileUrl(Content $content, Device $device): string
    {
        return $this->signed($content, 'file', $device);
    }

    /**
     * La caducidad se redondea a franjas fijas para que la URL no cambie en cada
     * consulta y el televisor pueda aprovechar la caché de imágenes.
     */
    private function signed(Content $content, string $variant, Device $device): string
    {
        $window = config('kidstv.tv_media_url_ttl_minutes') * 60;
        $expires = (intdiv(now()->timestamp, $window) + 2) * $window;

        // Debe ser una fecha: un entero se interpretaría como «segundos a partir de ahora».
        return URL::temporarySignedRoute('media.show', Carbon::createFromTimestamp($expires), [
            'content' => $content->id,
            'variant' => $variant,
            'd' => $device->id,
        ]);
    }
}
