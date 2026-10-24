<?php

namespace App\Http\Controllers\Panel;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait ValidatesContentMetadata
{
    /** Valida título, descripción, categoría, portada y perfiles, siempre dentro de la familia. */
    protected function validatedMetadata(Request $request, bool $titleRequired): array
    {
        $familyId = $this->family()->id;

        return $request->validate([
            'title' => [$titleRequired ? 'required' : 'nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('family_id', $familyId)],
            'profile_ids' => ['array'],
            'profile_ids.*' => ['integer', Rule::exists('profiles', 'id')->where('family_id', $familyId)],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(config('kidstv.max_cover_mb') * 1024)],
        ], [
            'title.required' => __('Escribe un título.'),
            'cover.image' => __('La portada tiene que ser una imagen (JPG, PNG o WebP).'),
            'cover.mimes' => __('La portada tiene que ser una imagen JPG, PNG o WebP.'),
            'cover.max' => __('La portada no puede ocupar más de :max MB.', ['max' => config('kidstv.max_cover_mb')]),
        ]);
    }
}
