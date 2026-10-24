<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Services\MediaLibrary;
use App\Services\YouTube;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContentController extends Controller
{
    use ValidatesContentMetadata;

    public function __construct(private MediaLibrary $library) {}

    public function index(Request $request)
    {
        $family = $this->family();
        $filters = $request->only(['type', 'category', 'profile', 'status']);

        $contents = $family->contents()
            ->with(['category', 'profiles'])
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['category'] ?? null, fn ($q, $category) => $category === 'none'
                ? $q->whereNull('category_id')
                : $q->where('category_id', $category))
            ->when($filters['profile'] ?? null, fn ($q, $profile) => $q->whereHas('profiles', fn ($p) => $p->whereKey($profile)))
            ->ordered()
            ->get();

        return view('panel.contents.index', [
            'contents' => $contents,
            'filters' => $filters,
            'filtered' => array_filter($filters) !== [],
            'categories' => $family->categories,
            'profiles' => $family->profiles,
        ]);
    }

    public function createYouTube()
    {
        return view('panel.contents.youtube', $this->formData());
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

    public function storeYouTube(Request $request, YouTube $youtube)
    {
        $request->validate(['url' => ['required', 'string', 'max:500']], ['url.required' => __('Pega el enlace del vídeo de YouTube.')]);

        $id = YouTube::parseId($request->input('url'));
        if (! $id) {
            throw ValidationException::withMessages(['url' => __('Este enlace no parece de un vídeo de YouTube.')]);
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
            'category_id' => $data['category_id'] ?? null,
            'status' => Content::STATUS_READY,
            'status_message' => $info['checked'] ? null : __('No se pudo comprobar con YouTube al añadirlo.'),
        ]);
        $content->profiles()->sync($data['profile_ids'] ?? []);

        if ($request->hasFile('cover')) {
            $content->update(['cover_path' => $this->library->storeCover($this->family(), $request->file('cover'))]);
        }

        return redirect()->route($request->boolean('from_welcome') ? 'welcome' : 'contents.index')
            ->with('status', __('«:title» añadido a la videoteca.', ['title' => $content->title]));
    }

    public function createUpload(string $kind)
    {
        return view('panel.contents.upload', $this->formData() + [
            'kind' => $kind,
            'maxBytes' => $this->library->maxBytes($kind),
            'extensions' => $this->library->extensions($kind),
            'chunkBytes' => config('kidstv.upload_chunk_mb') * 1024 * 1024,
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
            'category_id' => $data['category_id'] ?? null,
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

        return redirect()->route('contents.index')->with('status', __('«:title» eliminado. Desaparecerá de los televisores en cuanto actualicen el catálogo.', ['title' => $content->title]));
    }

    public function move(Request $request, int $content)
    {
        $content = $this->findContent($content);
        $list = $this->family()->contents()->ordered()->get(['id', 'position'])->values();
        $index = $list->search(fn (Content $c) => $c->id === $content->id);
        $swap = $request->input('direction') === 'up' ? $index - 1 : $index + 1;

        if ($swap >= 0 && $swap < $list->count()) {
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

    private function findContent(int $id): Content
    {
        return $this->family()->contents()->findOrFail($id);
    }

    private function formData(): array
    {
        $family = $this->family();

        return [
            'categories' => $family->categories,
            'profiles' => $family->profiles,
        ];
    }
}
