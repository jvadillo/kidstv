<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\VideoList;
use App\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Listas de vídeos de la familia (cada una es una fila en la tele) y listas públicas
 * de los administradores, que se añaden tal cual y se mantienen al día solas.
 */
class ListController extends Controller
{
    public function index()
    {
        return view('panel.lists.index', [
            'lists' => $this->family()->lists()
                ->withCount('contents')
                ->with(['profiles:id', 'source' => fn ($q) => $q->withCount(['contents' => fn ($c) => $c->ready()])])
                ->get(),
            'profiles' => $this->family()->profiles,
            'publicCount' => $this->availablePublicLists()->count(),
        ]);
    }

    public function store(Request $request)
    {
        $family = $this->family();
        $data = $this->validated($request);
        $family->lists()->create($data + ['position' => (int) $family->lists()->max('position') + 1]);

        return back()->with('status', __('Lista «:name» creada.', ['name' => $data['name']]));
    }

    public function update(Request $request, int $list)
    {
        $list = $this->findList($list);
        abort_if($list->isLinked(), 422, __('Las listas públicas no se pueden renombrar.'));
        $list->update($this->validated($request, $list));

        return back()->with('status', __('Lista actualizada.'));
    }

    public function move(Request $request, int $list)
    {
        $list = $this->findList($list);
        $all = $this->family()->lists()->get()->values();
        $index = $all->search(fn (VideoList $c) => $c->id === $list->id);
        $swap = $request->input('direction') === 'up' ? $index - 1 : $index + 1;

        if ($swap >= 0 && $swap < $all->count()) {
            $items = $all->all();
            [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];
            foreach ($items as $position => $item) {
                $item->update(['position' => $position]);
            }
        }

        return back();
    }

    public function destroy(int $list)
    {
        $list = $this->findList($list);
        $list->delete();

        if ($list->isLinked()) {
            return back()->with('status', __('Lista «:name» quitada de tu videoteca.', ['name' => $list->displayName()]));
        }

        return back()->with('status', __('Lista eliminada. Sus contenidos quedan como «Sin lista».'));
    }

    /** Qué perfiles ven una lista pública añadida. */
    public function profiles(Request $request, int $list)
    {
        $list = $this->findList($list);
        abort_unless($list->isLinked(), 404);
        $list->profiles()->sync($this->validatedProfiles($request));

        return back()->with('status', __('Perfiles de «:name» guardados.', ['name' => $list->displayName()]));
    }

    /** Catálogo de listas públicas que la familia todavía no ha añadido. */
    public function explore()
    {
        $linked = $this->family()->lists()->whereNotNull('source_id')->pluck('source_id');

        return view('panel.lists.public', [
            'lists' => $this->availablePublicLists()
                ->withCount(['contents' => fn ($q) => $q->ready()])
                ->with(['contents' => fn ($q) => $q->ready()->ordered()])
                ->orderBy('name')
                ->get(),
            'linked' => $linked->all(),
            'profiles' => $this->family()->profiles,
        ]);
    }

    public function showPublic(int $list)
    {
        $list = $this->findPublic($list);

        return view('panel.lists.public-show', [
            'list' => $list,
            'contents' => $list->contents()->ready()->ordered()->get(),
            'link' => $this->family()->lists()->where('source_id', $list->id)->first(),
            'profiles' => $this->family()->profiles,
        ]);
    }

    public function subscribe(Request $request, int $list)
    {
        $source = $this->findPublic($list);
        $family = $this->family();
        $profileIds = $this->validatedProfiles($request);

        $link = $family->lists()->firstOrCreate(['source_id' => $source->id], [
            'name' => mb_substr($source->name, 0, 40),
            'position' => (int) $family->lists()->max('position') + 1,
        ]);
        $link->profiles()->sync($profileIds);

        return redirect()->route('lists.index')->with('status', $profileIds
            ? __('Lista «:name» añadida a tu videoteca.', ['name' => $source->name])
            : __('Lista «:name» añadida. Elige qué perfiles la ven para que aparezca en la tele.', ['name' => $source->name]));
    }

    /** Portada de un vídeo de una lista pública (los archivos son de la familia administradora). */
    public function publicCover(MediaLibrary $library, int $list, int $content)
    {
        $content = $this->findPublic($list)->contents()->ready()->findOrFail($content);
        abort_unless($content->cover_path, 404);

        return $library->respond($content->cover_path, null, 'private, max-age=86400');
    }

    private function findList(int $id): VideoList
    {
        return $this->family()->lists()->findOrFail($id);
    }

    /** Lista pública de otra familia (las propias ya están en la videoteca). */
    private function findPublic(int $id): VideoList
    {
        return $this->availablePublicLists()->findOrFail($id);
    }

    private function availablePublicLists()
    {
        return VideoList::public()->where('family_id', '!=', $this->family()->id);
    }

    private function validatedProfiles(Request $request): array
    {
        return $request->validate([
            'profile_ids' => ['array'],
            'profile_ids.*' => ['integer', Rule::exists('profiles', 'id')->where('family_id', $this->family()->id)],
        ])['profile_ids'] ?? [];
    }

    private function validated(Request $request, ?VideoList $ignore = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('lists', 'name')->where('family_id', $this->family()->id)->whereNull('source_id')->ignore($ignore?->id),
            ],
        ], [
            'name.required' => __('Escribe un nombre para la lista.'),
            'name.unique' => __('Ya tienes una lista con ese nombre.'),
        ]);
    }
}
