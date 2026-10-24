<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function index()
    {
        return view('panel.profiles.index', [
            'profiles' => $this->family()->profiles()->withCount('contents')->get(),
        ]);
    }

    public function create()
    {
        return view('panel.profiles.form', ['profile' => new Profile(['avatar' => 'estrella']), 'avatars' => config('kidstv.avatars')]);
    }

    public function store(Request $request)
    {
        $family = $this->family();
        abort_if($family->profiles()->count() >= 12, 422, __('Se ha alcanzado el máximo de perfiles.'));

        $profile = $family->profiles()->create($this->validated($request) + [
            'position' => (int) $family->profiles()->max('position') + 1,
        ]);

        if ($request->boolean('from_welcome')) {
            return redirect()->route('welcome')->with('status', __('¡Perfil de :name creado!', ['name' => $profile->name]));
        }

        return redirect()->route('profiles.edit', $profile)->with('status', __('Perfil creado. Ahora elige qué puede ver.'));
    }

    public function edit(int $profile)
    {
        $profile = $this->findProfile($profile);
        $family = $this->family();

        return view('panel.profiles.edit', [
            'profile' => $profile,
            'avatars' => config('kidstv.avatars'),
            'contents' => $family->contents()->with('category')->ordered()->get(),
            'assigned' => $profile->contents()->pluck('contents.id')->all(),
        ]);
    }

    public function update(Request $request, int $profile)
    {
        $this->findProfile($profile)->update($this->validated($request));

        return back()->with('status', __('Perfil actualizado.'));
    }

    public function assign(Request $request, int $profile)
    {
        $profile = $this->findProfile($profile);
        $data = $request->validate([
            'content_ids' => ['array'],
            'content_ids.*' => ['integer', Rule::exists('contents', 'id')->where('family_id', $this->family()->id)],
        ]);

        $profile->contents()->sync($data['content_ids'] ?? []);

        return back()->with('status', __('Contenidos de :name guardados.', ['name' => $profile->name]));
    }

    public function destroy(int $profile)
    {
        $profile = $this->findProfile($profile);
        $profile->delete();

        return redirect()->route('profiles.index')->with('status', __('Perfil de :name eliminado. Sus contenidos siguen en la biblioteca.', ['name' => $profile->name]));
    }

    private function findProfile(int $id): Profile
    {
        return $this->family()->profiles()->findOrFail($id);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'avatar' => ['required', Rule::in(config('kidstv.avatars'))],
        ], [
            'name.required' => __('Escribe un apodo o nombre para el perfil.'),
        ]);
    }
}
