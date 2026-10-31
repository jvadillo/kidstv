<?php

namespace App\Services;

use App\Models\Family;
use Illuminate\Support\Collection;

/**
 * Lista de primeros pasos del Inicio: qué falta para tener la tele lista, en el orden en que
 * conviene hacerlo. Todo se deduce de los datos de la familia, así que no hay nada que guardar
 * ni que "descartar": la lista desaparece cuando se completa.
 */
class GettingStarted
{
    /**
     * @param  Collection  $profiles  Perfiles con sus listas (como los carga el Inicio).
     * @return list<array{key: string, title: string, hint: string, cta: string, href: string, done: bool}>
     */
    public function steps(Family $family, Collection $profiles, int $deviceCount): array
    {
        $hasProfile = $profiles->isNotEmpty();
        $hasContent = $profiles->contains(fn ($profile) => $profile->assignedContentCount() > 0);

        return [
            [
                'key' => 'profile',
                'title' => __('Crea el perfil de tu peque'),
                'hint' => __('Solo su nombre y su edad. Así la tele le propone cosas adecuadas.'),
                'cta' => __('Crear perfil'),
                'href' => route('onboarding.profile'),
                'done' => $hasProfile,
            ],
            [
                'key' => 'series',
                'title' => __('Elige sus series'),
                'hint' => __('Hay listas listas para ver, sin subir nada. Añade las que le gusten con un toque.'),
                'cta' => __('Ver series'),
                'href' => route('lists.public'),
                'done' => $hasContent,
            ],
            [
                'key' => 'tv',
                'title' => __('Conecta tu televisión'),
                'hint' => __('Abre NokoPlay en la tele y escribe aquí el código que muestra. Menos de un minuto.'),
                'cta' => __('Conectar'),
                'href' => route('devices.pair'),
                'done' => $deviceCount > 0,
            ],
            [
                'key' => 'pin',
                'title' => __('Protege la Zona de adultos'),
                'hint' => __('Un PIN evita que los peques salgan del modo infantil de la tele.'),
                'cta' => __('Crear PIN'),
                'href' => route('account.pin'),
                'done' => $family->hasPin(),
            ],
        ];
    }

    /** @param  list<array>  $steps */
    public function next(array $steps): ?array
    {
        return collect($steps)->firstWhere('done', false);
    }
}
