<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\VideoList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function publicList(Family $family, string $name, array $languages, int $min, int $max): VideoList
    {
        return $family->lists()->create(['name' => $name, 'is_public' => true, 'languages' => $languages, 'age_min' => $min, 'age_max' => $max]);
    }

    public function test_new_list_menu_offers_a_public_list_or_a_private_one(): void
    {
        $user = $this->adult();
        $this->actingAsAdult($user);

        $this->get('/panel/contenidos')->assertOk()
            ->assertSee('Añadir lista pública')->assertSee('/panel/listas/publicas', false)
            ->assertSee('Crear lista privada')->assertSee('/panel/listas/nueva', false);

        // Lo mínimo: nombre e idioma (el de la plataforma, ya marcado).
        $this->get('/panel/listas/nueva')->assertOk()
            ->assertSee('Nombre de la lista')
            ->assertSee('name="languages[]" value="es" checked', false)
            ->assertSee('Más idiomas');

        $response = $this->post('/panel/listas', ['name' => 'Canciones', 'languages' => ['es']]);
        $list = $user->family->lists()->where('name', 'Canciones')->firstOrFail();
        // Se entra en la lista, lista para añadirle contenidos.
        $response->assertRedirect('/panel/listas/'.$list->id);
        $this->get('/panel/listas/'.$list->id)->assertOk()->assertSee('Añadir contenido')->assertSee('Español');
    }

    public function test_new_lists_go_first_whether_private_public_or_added(): void
    {
        $admin = $this->adult(['is_admin' => true]);
        $admin->family->lists()->create(['name' => 'Antigua', 'languages' => ['es'], 'position' => 0]);
        $this->actingAsAdult($admin);

        $this->post('/panel/listas', ['name' => 'Privada', 'languages' => ['es']]);
        $this->post('/admin/listas', ['name' => 'Pública', 'languages' => ['en'], 'age_min' => 3, 'age_max' => 6]);
        $this->assertSame(['Pública', 'Privada', 'Antigua'], $admin->family->lists()->pluck('name')->all());

        $user = $this->adult();
        $user->family->lists()->create(['name' => 'Mía', 'languages' => ['es']]);
        $public = $admin->family->lists()->where('name', 'Pública')->first();
        $this->actingAsAdult($user)->post('/panel/listas/publicas/'.$public->id);
        $this->assertSame(['Pública', 'Mía'], $user->family->lists()->pluck('name')->all());
        $this->get('/panel/contenidos')->assertSeeInOrder(['Pública', 'Inglés', 'Mía']);
    }

    public function test_library_filters_by_profile_when_there_are_several(): void
    {
        // Con un solo perfil no hay nada que filtrar.
        $single = $this->adult();
        $this->profile($single->family, 'Ane');
        $single->family->lists()->create(['name' => 'Cuentos', 'languages' => ['es']]);
        $this->actingAsAdult($single)->get('/panel/contenidos')->assertOk()->assertDontSee('Filtrar por perfil');

        $user = $this->adult();
        $family = $user->family;
        $ane = $this->profile($family, 'Ane');
        $iker = $this->profile($family, 'Iker');
        $this->actingAsAdult($user);
        $cuentos = $family->lists()->create(['name' => 'Cuentos', 'languages' => ['es']]);
        $canciones = $family->lists()->create(['name' => 'Canciones', 'languages' => ['es']]);
        $this->youtubeContent($family, ['title' => 'Caperucita', 'list_id' => $cuentos->id])->profiles()->attach($ane);
        $this->youtubeContent($family, ['title' => 'Nana', 'list_id' => $canciones->id, 'youtube_id' => 'bbbbbbbbbbb'])->profiles()->attach($iker);
        $this->youtubeContent($family, ['title' => 'Suelto', 'youtube_id' => 'ccccccccccc'])->profiles()->attach($iker);

        $this->get('/panel/contenidos')->assertOk()
            ->assertSee('Filtrar por perfil')
            ->assertSeeInOrder(['Todos', 'Ane', 'Iker'])
            ->assertSee('Cuentos')->assertSee('Canciones')
            ->assertSee('Subir la lista', false);

        $this->get('/panel/contenidos?perfil='.$ane->id)->assertOk()
            ->assertSee('Lo que ve Ane')
            ->assertSee('Cuentos')->assertDontSee('Canciones')->assertDontSee('Sin lista')
            // Con filtro no se ordena: las vecinas pueden estar ocultas.
            ->assertDontSee('Subir la lista', false);

        $this->get('/panel/contenidos?perfil='.$iker->id)->assertOk()
            ->assertSee('Canciones')->assertSee('Sin lista')->assertDontSee('Cuentos');

        // Un perfil de otra familia no filtra nada.
        $other = $this->profile($this->adult()->family, 'Otro');
        $this->get('/panel/contenidos?perfil='.$other->id)->assertOk()->assertSee('Cuentos')->assertSee('Canciones');
    }

    public function test_admin_filters_library_by_public_and_private_lists(): void
    {
        $owner = $this->adult(['is_admin' => true]);
        $other = $this->publicList($owner->family, 'De otro admin', ['en'], 0, 3);

        $admin = $this->adult(['is_admin' => true]);
        $family = $admin->family;
        $this->publicList($family, 'Publicada', ['es'], 3, 6);
        $family->lists()->create(['name' => 'Privada', 'languages' => ['es']]);
        $family->lists()->create(['name' => 'Enlazada', 'source_id' => $other->id]);
        $this->youtubeContent($family, ['title' => 'Suelto']);
        $this->actingAsAdult($admin);

        $this->get('/panel/contenidos')->assertOk()
            ->assertSeeInOrder(['Todas', 'Públicas', 'Privadas'])
            ->assertSee('Publicada')->assertSee('Privada')->assertSee('De otro admin')->assertSee('Sin lista');
        $this->get('/panel/contenidos?tipo=publicas')->assertOk()
            ->assertSee('Publicada')->assertSee('De otro admin')->assertDontSee('<strong>Privada</strong>', false)->assertDontSee('Sin lista');
        $this->get('/panel/contenidos?tipo=privadas')->assertOk()
            ->assertSee('<strong>Privada</strong>', false)->assertSee('Sin lista')->assertDontSee('De otro admin')->assertDontSee('<strong>Publicada</strong>', false);

        // Una familia normal no tiene este filtro.
        $user = $this->adult();
        $user->family->lists()->create(['name' => 'Privada', 'languages' => ['es']]);
        $this->actingAsAdult($user)->get('/panel/contenidos?tipo=publicas')->assertOk()
            ->assertDontSee('Tipo de lista')->assertSee('Privada');
    }

    public function test_public_lists_are_filtered_by_language_and_age(): void
    {
        $admin = $this->adult(['is_admin' => true]);
        $this->publicList($admin->family, 'Bebés', ['es'], 0, 2);
        $this->publicList($admin->family, 'Songs', ['en'], 3, 6);
        $this->publicList($admin->family, 'Kantak', ['es', 'eu'], 6, 9);
        $this->actingAsAdult($this->adult());

        $this->get('/panel/listas/publicas')->assertOk()
            ->assertSee('Filtrar por idioma')->assertSee('Filtrar por edad recomendada')
            ->assertSee('Bebés')->assertSee('Songs')->assertSee('Kantak')
            ->assertSee('Español, Euskera')->assertSee('6–9 años');

        $this->get('/panel/listas/publicas?idioma=es')->assertOk()
            ->assertSee('Bebés')->assertSee('Kantak')->assertDontSee('Songs');
        // Salen las listas cuya edad se solapa con el tramo.
        $this->get('/panel/listas/publicas?edad=6-8')->assertOk()
            ->assertSee('Songs')->assertSee('Kantak')->assertDontSee('Bebés');
        $this->get('/panel/listas/publicas?idioma=en&edad=0-2')->assertOk()
            ->assertSee('Ninguna lista coincide con estos filtros')->assertDontSee('Songs');
        // Un filtro desconocido se ignora.
        $this->get('/panel/listas/publicas?idioma=xx&edad=99')->assertOk()->assertSee('Bebés')->assertSee('Songs');
    }
}
