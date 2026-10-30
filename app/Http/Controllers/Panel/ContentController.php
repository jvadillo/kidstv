<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\VideoList;
use App\Services\MediaLibrary;
use App\Services\YouTube;
use App\Services\YouTubeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ContentController extends Controller
{
    use ValidatesContentMetadata;

    public function __construct(private MediaLibrary $library) {}

    /**
     * Biblioteca: las listas de la familia, en el orden de las filas de la tele. Con varios
     * perfiles se puede ver solo lo de un niño y un administrador puede separar las listas
     * públicas (publicadas o añadidas) de las privadas.
     */
    public function index(Request $request)
    {
        $family = $this->family();
        $query = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $matches = fn ($name, $items) => $query === '' || mb_stripos($name, $query) !== false
            || $items->contains(fn (Content $item) => mb_stripos($item->title, $query) !== false);
        $profiles = $family->profiles;
        $profile = $profiles->count() > 1 ? $profiles->firstWhere('id', $request->integer('perfil')) : null;
        $kind = $request->user()->isAdmin() && in_array($request->query('tipo'), ['publicas', 'privadas'], true) ? $request->query('tipo') : null;
        $byList = $family->contents()->with('profiles:id')->ordered()->get()
            ->groupBy(fn (Content $c) => $c->list_id ?? 0);

        $cards = $family->lists()
            ->with(['profiles:id', 'source.contents' => fn ($q) => $q->ready()->ordered()])
            ->get()
            ->map(function (VideoList $list) use ($byList, $profiles, $matches) {
                // Una lista pública añadida decide quién la ve; en las propias, cada contenido.
                $items = $list->isLinked()
                    ? ($list->isAvailable() ? $list->source->contents : collect())
                    : $byList->get($list->id, collect());
                $profileIds = $list->isLinked() ? $list->profiles->pluck('id') : $items->flatMap->profiles->pluck('id');

                return ['list' => $list, 'matches' => $matches($list->displayName(), $items)] + $this->summary($items, $profiles->whereIn('id', $profileIds));
            });

        $unlisted = $byList->get(0, collect());
        $unlistedMatches = $matches(__('Sin lista'), $unlisted);
        $unlisted = $unlisted->isEmpty() ? null : $this->summary($unlisted, $profiles->whereIn('id', $unlisted->flatMap->profiles->pluck('id')));
        $empty = $cards->isEmpty() && ! $unlisted;

        // Una lista sale con el filtro de un perfil si ese niño ve algo de ella en la tele.
        $shows = fn (?VideoList $list, $seenBy) => (! $profile || $seenBy->contains('id', $profile->id))
            && (! $kind || ($kind === 'publicas') === (bool) ($list?->is_public || $list?->isLinked()));

        return view('panel.contents.index', [
            'cards' => $cards->filter(fn ($card) => $card['matches'] && $shows($card['list'], $card['profiles']))->values(),
            'unlisted' => $unlisted && $unlistedMatches && $shows(null, $unlisted['profiles']) ? $unlisted : null,
            'empty' => $empty,
            'profiles' => $profiles,
            'profile' => $profile,
            'kind' => $kind,
            'query' => $query,
            'filters' => ['perfil' => $profile?->id, 'tipo' => $kind, 'q' => $query],
            'publicCount' => VideoList::public()->where('family_id', '!=', $family->id)->count(),
        ]);
    }

    public function createYouTube()
    {
        return view('panel.contents.youtube', $this->formData() + ['searchEnabled' => YouTubeSearch::enabled()]);
    }

    /** Buscador de YouTube (solo panel de adultos; la tele nunca tiene buscador). */
    public function searchYouTube(Request $request, YouTubeSearch $search)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'duracion' => ['nullable', Rule::in(YouTubeSearch::DURATIONS)],
            'pagina' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'ninos' => ['nullable', 'boolean'],
            'idioma' => ['nullable', Rule::in(array_keys(YouTubeSearch::languages()))],
        ]);

        // El idioma elegido se recuerda para las siguientes búsquedas de este adulto.
        if (isset($data['idioma'])) {
            $request->session()->put('youtube_language', $data['idioma']);
        }
        $language = $data['idioma'] ?? $request->session()->get('youtube_language', YouTubeSearch::defaultLanguage());

        $query = trim($data['q'] ?? '');
        $results = null;
        $error = null;

        if ($query !== '' && YouTubeSearch::enabled()) {
            $limiter = 'youtube-search:'.$request->user()->id;
            if (RateLimiter::tooManyAttempts($limiter, 40)) {
                $error = __('Has hecho muchas búsquedas seguidas. Espera unos minutos.');
            } else {
                RateLimiter::hit($limiter, 3600);
                try {
                    $results = $search->search($query, $data['duracion'] ?? 'any', $data['pagina'] ?? null, $language);
                    if ($request->boolean('ninos')) {
                        $results['items'] = array_values(array_filter($results['items'], fn ($item) => $item['made_for_kids']));
                    }
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $ids = collect($results['items'] ?? [])->pluck('id');

        return view('panel.contents.youtube-search', $this->formData() + [
            'enabled' => YouTubeSearch::enabled(),
            'query' => $query,
            'duration' => $data['duracion'] ?? 'any',
            'kidsOnly' => $request->boolean('ninos'),
            'language' => $language,
            'languages' => YouTubeSearch::languages(),
            'results' => $results,
            'error' => $error,
            'added' => $ids->isEmpty() ? [] : $this->family()->contents()->whereIn('youtube_id', $ids)->pluck('youtube_id')->all(),
        ]);
    }

    /** Comprobación previa (AJAX) de un enlace de YouTube. */
    public function previewYouTube(Request $request, YouTube $youtube)
    {
        $id = YouTube::parseId((string) $request->input('url'));
        if (! $id) {
            return response()->json(['ok' => false, 'error' => __('Este enlace no parece de un vídeo de YouTube. Copia la dirección del vídeo (por ejemplo https://www.youtube.com/watch?v=…).')], 422);
        }

        $info = $youtube->lookup($id);

        return response()->json([
            'ok' => $info['ok'],
            'checked' => $info['checked'],
            'id' => $id,
            'title' => $info['title'],
            'author' => $info['author'],
            'thumbnail' => 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg',
            'error' => $info['error'],
        ], $info['ok'] ? 200 : 422);
    }

    public function storeYouTube(Request $request, YouTube $youtube, YouTubeSearch $search)
    {
        $request->validate(['url' => ['required', 'string', 'max:500']], ['url.required' => __('Pega el enlace del vídeo de YouTube.')]);

        $id = YouTube::parseId($request->input('url'));
        if (! $id) {
            throw ValidationException::withMessages(['url' => __('Este enlace no parece de un vídeo de YouTube.')]);
        }

        if ($this->family()->contents()->where('youtube_id', $id)->exists()) {
            throw ValidationException::withMessages(['url' => __('Este vídeo ya está en tu videoteca.')]);
        }

        $info = $youtube->lookup($id);
        if (! $info['ok']) {
            throw ValidationException::withMessages(['url' => $info['error']]);
        }

        $data = $this->validatedMetadata($request, titleRequired: false);
        $title = ($data['title'] ?? null) ?: $info['title'];
        if (! $title) {
            throw ValidationException::withMessages(['title' => __('No hemos podido leer el título del vídeo. Escríbelo tú.')]);
        }

        $content = $this->family()->contents()->create([
            'type' => Content::TYPE_YOUTUBE,
            'youtube_id' => $id,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'list_id' => $data['list_id'] ?? null,
            'status' => Content::STATUS_READY,
            'status_message' => $info['checked'] ? null : __('No se pudo comprobar con YouTube al añadirlo.'),
            // Con la API configurada; si no, la duración se completa más tarde (nokoplay:youtube-durations).
            'duration' => $search->durations([$id])[$id] ?? null,
        ]);
        $content->profiles()->sync($data['profile_ids'] ?? []);

        if ($request->hasFile('cover')) {
            $content->update(['cover_path' => $this->library->storeCover($this->family(), $request->file('cover'))]);
        }

        $message = __('«:title» añadido a la videoteca.', ['title' => $content->title]);

        // Desde el buscador se añaden vídeos sin salir de los resultados.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $content->id, 'youtube_id' => $id, 'title' => $content->title, 'message' => $message], 201);
        }
        if ($request->boolean('from_search')) {
            return back()->with('status', $message);
        }

        return redirect($this->listUrl($content->list_id))->with('status', $message);
    }

    public function createUpload(string $kind)
    {
        return view('panel.contents.upload', $this->formData() + [
            'kind' => $kind,
            'maxBytes' => $this->library->maxBytes($kind),
            'extensions' => $this->library->extensions($kind),
            'chunkBytes' => config('nokoplay.upload_chunk_mb') * 1024 * 1024,
            'storageUsed' => $this->family()->storageUsed(),
            'storageQuota' => $this->library->quotaBytes(),
        ]);
    }

    public function edit(int $content)
    {
        return view('panel.contents.edit', $this->formData() + [
            'content' => $this->findContent($content)->load('profiles'),
        ]);
    }

    public function update(Request $request, int $content)
    {
        $content = $this->findContent($content);
        $data = $this->validatedMetadata($request, titleRequired: true);

        $content->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'list_id' => $data['list_id'] ?? null,
        ]);

        if ($request->hasFile('cover')) {
            $old = $content->cover_path;
            $content->cover_path = $this->library->storeCover($this->family(), $request->file('cover'));
            if ($old) {
                Storage::disk('local')->delete($old);
            }
        } elseif ($request->boolean('remove_cover') && $content->cover_path) {
            Storage::disk('local')->delete($content->cover_path);
            $content->cover_path = null;
        }

        $content->save();
        $content->profiles()->sync($data['profile_ids'] ?? []);

        return redirect()->route('contents.edit', $content)->with('status', __('Cambios guardados.'));
    }

    public function destroy(int $content)
    {
        $content = $this->findContent($content);
        $content->delete();

        // De vuelta a su lista; si era el último contenido sin lista, a la biblioteca.
        $url = $content->list_id || $this->family()->contents()->whereNull('list_id')->exists()
            ? $this->listUrl($content->list_id)
            : route('contents.index');

        return redirect($url)->with('status', __('«:title» eliminado. Desaparecerá de los televisores en cuanto actualicen el catálogo.', ['title' => $content->title]));
    }

    /** Sube o baja un contenido dentro de su lista (el vecino más cercano de la misma lista). */
    public function move(Request $request, int $content)
    {
        $content = $this->findContent($content);
        $list = $this->family()->contents()->ordered()->get(['id', 'list_id', 'position'])->values();
        $index = $list->search(fn (Content $c) => $c->id === $content->id);
        $up = $request->input('direction') === 'up';
        $swap = null;
        for ($i = $up ? $index - 1 : $index + 1; $i >= 0 && $i < $list->count(); $i += $up ? -1 : 1) {
            if ($list[$i]->list_id === $list[$index]->list_id) {
                $swap = $i;
                break;
            }
        }

        if ($swap !== null) {
            $items = $list->all();
            [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];
            foreach ($items as $position => $item) {
                if ($item->position !== $position + 1) {
                    Content::whereKey($item->id)->update(['position' => $position + 1]);
                }
            }
        }

        return back();
    }

    /** Ocultar temporalmente / volver a mostrar (en YouTube, volviendo a comprobar el vídeo). */
    public function visibility(Request $request, int $content, YouTube $youtube)
    {
        $content = $this->findContent($content);

        if ($request->input('action') === 'hide') {
            $content->update(['status' => Content::STATUS_UNAVAILABLE, 'status_message' => Content::MESSAGE_HIDDEN]);

            return back()->with('status', __('«:title» oculto en todos los televisores.', ['title' => $content->title]));
        }

        if ($content->isYouTube()) {
            $info = $youtube->lookup($content->youtube_id);
            if (! $info['ok']) {
                $content->update(['status' => Content::STATUS_UNAVAILABLE, 'status_message' => $info['error']]);

                return back()->withErrors(['visibility' => $info['error']]);
            }
            $content->update(['status' => Content::STATUS_READY, 'status_message' => null]);
        } elseif ($content->status === Content::STATUS_UNAVAILABLE) {
            $content->update(['status' => Content::STATUS_READY, 'status_message' => null]);
        }

        return back()->with('status', __('«:title» vuelve a estar disponible.', ['title' => $content->title]));
    }

    public function cover(int $content)
    {
        $content = $this->findContent($content);
        abort_unless($content->cover_path, 404);

        return $this->library->respond($content->cover_path, null, 'private, max-age=86400');
    }

    public function file(int $content)
    {
        $content = $this->findContent($content);
        abort_unless($content->file_path, 404);

        return $this->library->respond($content->file_path, $content->mime);
    }

    /** Lo que se enseña de una lista en la biblioteca: portada, recuento y quién la ve. */
    private function summary($items, $profiles): array
    {
        return [
            'first' => $items->first(),
            'count' => $items->count(),
            'audios' => $items->where('type', Content::TYPE_AUDIO)->count(),
            'profiles' => $profiles->values(),
        ];
    }

    /** Pantalla de gestión de una lista (o de los contenidos sin lista). */
    private function listUrl(?int $listId): string
    {
        return $listId ? route('lists.show', $listId) : route('lists.unlisted');
    }

    private function findContent(int $id): Content
    {
        return $this->family()->contents()->findOrFail($id);
    }

    private function formData(): array
    {
        $family = $this->family();

        return [
            'lists' => $family->ownLists,
            'profiles' => $family->profiles,
        ];
    }
}
