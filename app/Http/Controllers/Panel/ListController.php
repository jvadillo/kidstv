<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\VideoList;
use App\Services\MediaLibrary;
use App\Services\YouTubeSearch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Listas de vídeos de la familia (cada una es una fila en la tele) y listas públicas
 * de los administradores, que se añaden tal cual y se mantienen al día solas.
 */
class ListController extends Controller
{
    use ValidatesListFields;

    /** Las listas se ven y se ordenan en la biblioteca. */
    public function index()
    {
        return redirect()->route('contents.index');
    }

    /** Gestión de una lista: sus contenidos o, si es pública, quién la ve. */
    public function show(int $list)
    {
        $list = $this->findList($list);

        if ($list->isLinked()) {
            return view('panel.lists.linked', [
                'list' => $list->load('profiles:id'),
                'contents' => $list->isAvailable() ? $list->source->contents()->ready()->ordered()->get() : collect(),
                'profiles' => $this->family()->profiles,
            ]);
        }

        return view('panel.lists.show', [
            'list' => $list,
            'contents' => $list->contents()->with('profiles')->ordered()->get(),
        ]);
    }

    /** Los contenidos sin lista, que en la tele salen al final, en la fila «Otros». */
    public function unlisted()
    {
        return view('panel.lists.show', [
            'list' => null,
            'contents' => $this->family()->contents()->whereNull('list_id')->with('profiles')->ordered()->get(),
        ]);
    }

    /** Nueva lista privada: solo lo imprescindible, y luego se le añaden contenidos. */
    public function create()
    {
        return view('panel.lists.create', ['defaultLanguage' => YouTubeSearch::defaultLanguage()]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedList($request);
        $list = $this->family()->prependList($data);

        return redirect()->route('lists.show', $list)->with('status', __('Lista «:name» creada. Ahora añádele vídeos o audios.', ['name' => $data['name']]));
    }

    public function update(Request $request, int $list)
    {
        $list = $this->findList($list);
        abort_if($list->isLinked(), 422, __('Las listas públicas no se pueden renombrar.'));
        $list->update($this->validatedList($request, $list));

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

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function destroy(int $list)
    {
        $list = $this->findList($list);
        $list->delete();

        if ($list->isLinked()) {
            return redirect()->route('contents.index')->with('status', __('Lista «:name» quitada de tu videoteca.', ['name' => $list->displayName()]));
        }

        return redirect()->route('contents.index')->with('status', __('Lista eliminada. Sus contenidos quedan como «Sin lista».'));
    }

    /** Qué perfiles ven una lista pública añadida. */
    public function profiles(Request $request, int $list)
    {
        $list = $this->findList($list);
        abort_unless($list->isLinked(), 404);
        $list->profiles()->sync($this->validatedProfiles($request));

        return back()->with('status', __('Perfiles de «:name» guardados.', ['name' => $list->displayName()]));
    }

    /** Catálogo de listas públicas, con filtros por idioma y por tramo de edad recomendada. */
    public function explore(Request $request)
    {
        $linked = $this->family()->lists()->whereNotNull('source_id')->pluck('source_id');
        $all = $this->availablePublicLists()
            ->withCount(['contents' => fn ($q) => $q->ready()])
            ->with(['contents' => fn ($q) => $q->ready()->ordered()])
            ->orderBy('name')
            ->get();

        // En el filtro de idioma solo salen los idiomas que tiene alguna lista.
        $languages = collect(VideoList::languageOptions())->only($all->flatMap->languageCodes()->unique()->all());
        $language = $languages->has((string) $request->query('idioma')) ? $request->query('idioma') : null;
        $age = array_key_exists((string) $request->query('edad'), VideoList::AGE_RANGES) ? $request->query('edad') : null;

        return view('panel.lists.public', [
            'lists' => $all
                ->when($language, fn ($lists) => $lists->filter(fn (VideoList $list) => in_array($language, $list->languageCodes(), true)))
                ->when($age, fn ($lists) => $lists->filter(fn (VideoList $list) => $list->suitsAges(...VideoList::AGE_RANGES[$age])))
                ->values(),
            'total' => $all->count(),
            'languages' => $languages,
            'filters' => ['idioma' => $language, 'edad' => $age],
            'linked' => $linked->all(),
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

        // Como las listas nuevas, la recién añadida sale la primera.
        $link = $family->lists()->where('source_id', $source->id)->first()
            ?? $family->prependList(['source_id' => $source->id, 'name' => mb_substr($source->name, 0, 40)]);
        $link->profiles()->sync($profileIds);

        return redirect()->route('lists.show', $link)->with('status', $profileIds
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
}
