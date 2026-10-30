<?php

namespace App\Services;

use App\Models\Content;
use App\Models\Device;
use App\Models\Family;
use App\Models\Profile;
use App\Models\VideoList;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Construye las respuestas de catálogo para televisores, con URLs firmadas y temporales,
 * y decide qué contenidos puede ver una familia: los suyos y los de las listas públicas
 * que ha añadido a su videoteca.
 */
class TvCatalog
{
    /**
     * Catálogo de un perfil o, sin perfil, el de toda la familia (Zona de adultos).
     */
    public function present(Device $device, ?Profile $profile = null): array
    {
        $family = $device->family;
        $lists = $family->lists()->with('source')->get();
        $entries = $this->visible($family, $lists, $profile);
        $usedIds = $entries->pluck('row')->unique();

        $items = $entries->map(fn (array $entry) => [
            'id' => $entry['content']->id,
            'type' => $entry['content']->type,
            'title' => $entry['content']->title,
            'description' => $entry['content']->description,
            // Fila de la tele: la lista de la familia (para las públicas, su enlace).
            'list_id' => $entry['row'],
            'duration' => $entry['content']->duration,
            'cover' => $this->coverUrl($entry['content'], $device),
        ])->values();

        $tabs = $lists->filter(fn (VideoList $list) => $usedIds->contains($list->id))
            ->map(fn (VideoList $list) => ['id' => $list->id, 'name' => $list->displayName()])
            ->values();

        if ($usedIds->contains(null)) {
            $tabs->push(['id' => null, 'name' => __('Otros')]);
        }

        return [
            'lists' => $tabs,
            'items' => $items,
            // Huella del catálogo: la TV la compara para saber si algo ha cambiado.
            'version' => sha1($items->map(fn ($i) => $i['id'].':'.$i['title'].':'.$i['list_id'].':'.$i['duration'].':'.$i['cover'])->implode('|').$tabs->toJson()),
        ];
    }

    /**
     * Contenido que la familia puede ver, en cualquier estado: suyo o de una lista
     * pública añadida a su videoteca. Nunca resuelve contenidos de otras familias.
     */
    public function find(Family $family, int $id): ?Content
    {
        $content = Content::with('list')->find($id);

        if (! $content) {
            return null;
        }

        if ($content->family_id === $family->id) {
            return $content;
        }

        return $this->linkFor($family, $content) ? $content : null;
    }

    /** Si un perfil puede ver un contenido que la familia tiene disponible. */
    public function allowedFor(Content $content, Family $family, Profile $profile): bool
    {
        if ($content->family_id === $family->id) {
            return $content->profiles()->whereKey($profile->id)->exists();
        }

        return (bool) $this->linkFor($family, $content)?->profiles()->whereKey($profile->id)->exists();
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
     * Contenidos listos, cada uno con la fila (lista de la familia) en la que aparece.
     *
     * @param  Collection<int, VideoList>  $lists
     * @return Collection<int, array{content: Content, row: ?int}>
     */
    private function visible(Family $family, Collection $lists, ?Profile $profile): Collection
    {
        $own = ($profile ? $profile->contents() : $family->contents())->ready()->ordered()->get()
            ->map(fn (Content $c) => ['content' => $c, 'row' => $c->list_id]);

        $links = $lists->filter(fn (VideoList $list) => $list->isLinked() && $list->isAvailable());
        if ($profile && $links->isNotEmpty()) {
            $allowed = $profile->lists()->pluck('lists.id');
            $links = $links->filter(fn (VideoList $list) => $allowed->contains($list->id));
        }

        if ($links->isEmpty()) {
            return $own;
        }

        $shared = Content::whereIn('list_id', $links->pluck('source_id'))->ready()->ordered()->get()->groupBy('list_id');

        return $own->concat($links->flatMap(fn (VideoList $list) => $shared->get($list->source_id, collect())
            ->map(fn (Content $c) => ['content' => $c, 'row' => $list->id])));
    }

    /** Enlace de la familia a la lista pública que contiene el contenido, si lo hay. */
    private function linkFor(Family $family, Content $content): ?VideoList
    {
        if (! $content->list?->is_public || $content->list->isLinked()) {
            return null;
        }

        return $family->lists()->where('source_id', $content->list_id)->first();
    }

    /**
     * La caducidad se redondea a franjas fijas para que la URL no cambie en cada
     * consulta y el televisor pueda aprovechar la caché de imágenes.
     */
    private function signed(Content $content, string $variant, Device $device): string
    {
        $window = config('nokoplay.tv_media_url_ttl_minutes') * 60;
        $expires = (intdiv(now()->timestamp, $window) + 2) * $window;

        // Debe ser una fecha: un entero se interpretaría como «segundos a partir de ahora».
        return URL::temporarySignedRoute('media.show', Carbon::createFromTimestamp($expires), [
            'content' => $content->id,
            'variant' => $variant,
            'd' => $device->id,
        ]);
    }
}
