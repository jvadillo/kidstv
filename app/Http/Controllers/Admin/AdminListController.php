<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\ValidatesListFields;
use App\Models\VideoList;
use App\Services\UsageStats;
use App\Services\YouTubeSearch;
use Illuminate\Http\Request;

/**
 * Listas públicas. Son listas de la familia del administrador marcadas como públicas:
 * sus vídeos se gestionan desde la biblioteca de siempre y cualquier cambio llega solo a
 * las familias que las han añadido.
 */
class AdminListController extends Controller
{
    use ValidatesListFields;

    public function index(UsageStats $stats)
    {
        $public = VideoList::public()
            ->with('family.users:id,family_id,email')
            ->withCount(['links', 'contents' => fn ($q) => $q->ready()])
            ->orderBy('name')
            ->get();

        return view('admin.lists.index', [
            'public' => $public,
            'usage' => $stats->byList(now()->subDays(30), $public->pluck('id')->all()),
            'private' => $this->family()->ownLists()->where('is_public', false)->withCount('contents')->get(),
            'familyId' => $this->family()->id,
            'defaultLanguage' => YouTubeSearch::defaultLanguage(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedList($request, public: true);
        // Como cualquier lista nueva, sale la primera en la biblioteca del administrador.
        $this->family()->prependList($data + ['is_public' => true]);

        return back()->with('status', __('Lista pública «:name» creada. Añádele vídeos desde la biblioteca eligiendo esta lista.', ['name' => $data['name']]));
    }

    public function update(Request $request, int $list)
    {
        $list = VideoList::public()->findOrFail($list);
        $list->update($this->validatedList($request, $list, public: true));

        return back()->with('status', __('Lista «:name» actualizada.', ['name' => $list->name]));
    }

    /** Publicar una lista propia que ya existe, indicando para qué edades es. */
    public function publish(Request $request, int $list)
    {
        $list = $this->family()->ownLists()->findOrFail($list);
        $list->update($this->validatedAges($request) + ['is_public' => true]);

        return back()->with('status', __('«:name» ya es una lista pública.', ['name' => $list->name]));
    }

    /** Dejar de publicar: desaparece de las videotecas que la habían añadido. */
    public function unpublish(int $list)
    {
        $list = VideoList::public()->findOrFail($list);
        $removed = $list->links()->count();
        $list->links()->get()->each->delete();
        $list->update(['is_public' => false]);

        return back()->with('status', trans_choice('«:name» ya no es pública. Se ha quitado de :count videoteca.|«:name» ya no es pública. Se ha quitado de :count videotecas.', $removed, ['name' => $list->name]));
    }
}
