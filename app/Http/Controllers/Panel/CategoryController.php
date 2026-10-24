<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('panel.categories.index', [
            'categories' => $this->family()->categories()->withCount('contents')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $family = $this->family();
        $data = $this->validated($request);
        $family->categories()->create($data + ['position' => (int) $family->categories()->max('position') + 1]);

        return back()->with('status', __('Categoría «:name» creada.', ['name' => $data['name']]));
    }

    public function update(Request $request, int $category)
    {
        $category = $this->findCategory($category);
        $category->update($this->validated($request, $category));

        return back()->with('status', __('Categoría actualizada.'));
    }

    public function move(Request $request, int $category)
    {
        $category = $this->findCategory($category);
        $list = $this->family()->categories()->get()->values();
        $index = $list->search(fn (Category $c) => $c->id === $category->id);
        $swap = $request->input('direction') === 'up' ? $index - 1 : $index + 1;

        if ($swap >= 0 && $swap < $list->count()) {
            $items = $list->all();
            [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];
            foreach ($items as $position => $item) {
                $item->update(['position' => $position]);
            }
        }

        return back();
    }

    public function destroy(int $category)
    {
        $category = $this->findCategory($category);
        $category->delete();

        return back()->with('status', __('Categoría eliminada. Sus contenidos quedan como «Sin categoría».'));
    }

    private function findCategory(int $id): Category
    {
        return $this->family()->categories()->findOrFail($id);
    }

    private function validated(Request $request, ?Category $ignore = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('categories', 'name')->where('family_id', $this->family()->id)->ignore($ignore?->id),
            ],
        ], [
            'name.required' => __('Escribe un nombre para la categoría.'),
            'name.unique' => __('Ya tienes una categoría con ese nombre.'),
        ]);
    }
}
