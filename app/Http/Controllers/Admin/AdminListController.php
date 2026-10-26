<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VideoList;
use App\Services\UsageStats;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Listas públicas. Son listas de la familia del administrador marcadas como públicas:
 * sus vídeos se gestionan desde la biblioteca de siempre y cualquier cambio llega solo a
 * las familias que las han añadido.
 */
class AdminListController extends Controller
{
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
        ]);
    }

    public function store(Request $request)
    {
        $family = $this->family();
        $data = $this->validated($request);
        $family->lists()->create($data + [
            'is_public' => true,
            'position' => (int) $family->lists()->max('position') + 1,
        ]);

        return back()->with('status', __('Lista pública «:name» creada. Añádele vídeos desde la biblioteca eligiendo esta lista.', ['name' => $data['name']]));
    }

    public function update(Request $request, int $list)
    {
        $list = VideoList::public()->findOrFail($list);
        $list->update($this->validated($request, $list));

        return back()->with('status', __('Lista «:name» actualizada.', ['name' => $list->name]));
    }

    /** Publicar una lista propia que ya existe. */
    public function publish(int $list)
    {
        $list = $this->family()->ownLists()->findOrFail($list);
        $list->update(['is_public' => true]);

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

    private function validated(Request $request, ?VideoList $ignore = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('lists', 'name')->where('family_id', $ignore->family_id ?? $this->family()->id)->whereNull('source_id')->ignore($ignore?->id),
            ],
            'description' => ['nullable', 'string', 'max:200'],
        ], [
            'name.required' => __('Escribe un nombre para la lista.'),
            'name.unique' => __('Ya hay una lista con ese nombre.'),
        ]);
    }
}
