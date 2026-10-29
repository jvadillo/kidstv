<?php

namespace App\Http\Controllers\Panel;

use App\Models\VideoList;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait ValidatesListFields
{
    /**
     * Nombre e idiomas (al menos uno) de una lista propia y, si es pública, también su
     * descripción y la edad recomendada. El nombre no se repite dentro de la familia dueña.
     */
    protected function validatedList(Request $request, ?VideoList $ignore = null, bool $public = false): array
    {
        $rules = [
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('lists', 'name')->where('family_id', $ignore->family_id ?? $this->family()->id)->whereNull('source_id')->ignore($ignore?->id),
            ],
            'languages' => ['required', 'array'],
            'languages.*' => ['string', 'distinct', Rule::in(array_keys(VideoList::languageOptions()))],
        ];
        if ($public) {
            $rules += ['description' => ['nullable', 'string', 'max:200']] + $this->ageRules();
        }

        $data = $request->validate($rules, $this->listMessages());
        // Siempre en el orden de la lista de idiomas, para que se muestren igual en todas partes.
        $data['languages'] = array_values(array_intersect(array_keys(VideoList::languageOptions()), $data['languages']));

        return $data;
    }

    /** Edad recomendada de una lista pública. */
    protected function validatedAges(Request $request): array
    {
        return $request->validate($this->ageRules(), $this->listMessages());
    }

    private function ageRules(): array
    {
        $between = 'between:'.VideoList::AGE_MIN.','.VideoList::AGE_MAX;

        return [
            'age_min' => ['required', 'integer', $between],
            'age_max' => ['required', 'integer', $between, 'gte:age_min'],
        ];
    }

    private function listMessages(): array
    {
        return [
            'name.required' => __('Escribe un nombre para la lista.'),
            'name.unique' => __('Ya hay una lista con ese nombre.'),
            'languages.required' => __('Elige al menos un idioma.'),
            'languages.*.in' => __('Elige un idioma de la lista.'),
            'languages.*.distinct' => __('Elige un idioma de la lista.'),
            'age_min.required' => __('Indica la edad recomendada.'),
            'age_max.required' => __('Indica la edad recomendada.'),
            'age_min.between' => __('La edad tiene que estar entre :min y :max años.'),
            'age_max.between' => __('La edad tiene que estar entre :min y :max años.'),
            'age_max.gte' => __('La edad «hasta» no puede ser menor que la edad «desde».'),
        ];
    }
}
