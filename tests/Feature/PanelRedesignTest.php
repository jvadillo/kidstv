<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_list_names_and_content_without_leaking_other_families(): void
    {
        $user = $this->adult();
        $family = $user->family;
        $list = $family->lists()->create(['name' => 'Cuentos', 'languages' => ['es']]);
        $family->lists()->create(['name' => 'Canciones', 'languages' => ['en']]);
        $this->youtubeContent($family, ['list_id' => $list->id, 'title' => 'El bosque mágico']);
        $this->youtubeContent($family, ['title' => 'Audio suelto', 'type' => 'audio']);
        $this->adult()->family->lists()->create(['name' => 'Bosque ajeno', 'languages' => ['es']]);
        $this->actingAsAdult($user);

        $this->get('/panel/contenidos?q=bosque')->assertOk()->assertSee('Cuentos')->assertDontSee('Canciones')->assertDontSee('Bosque ajeno')->assertDontSee('data-sortable-lists', false);
        $this->get('/panel/contenidos?q=CUENTOS')->assertOk()->assertSee('<strong>Cuentos</strong>', false)->assertDontSee('Canciones');
        $this->get('/panel/contenidos?q=Audio')->assertOk()->assertSee('Sin lista')->assertDontSee('Cuentos');
        $this->get('/panel/contenidos?q=inexistente')->assertOk()->assertSee('No hay listas ni contenidos');
        $this->get('/panel/contenidos')->assertOk()->assertSee('data-sortable-lists', false);
    }

    public function test_empty_and_duplicate_profiles_include_linked_public_content_in_the_count(): void
    {
        $user = $this->adult();
        $first = $this->profile($user->family, 'Nora');
        $this->profile($user->family, 'Nora');
        $publisher = $this->adult(['is_admin' => true]);
        $source = $publisher->family->lists()->create(['name' => 'Serie', 'is_public' => true, 'languages' => ['es']]);
        $this->youtubeContent($publisher->family, ['list_id' => $source->id]);
        $linked = $user->family->lists()->create(['name' => 'Serie', 'source_id' => $source->id]);
        $linked->profiles()->attach($first);
        $this->actingAsAdult($user)->get('/panel/perfiles')->assertOk()
            ->assertSee('1 contenido')->assertSee('sin contenidos')->assertSee('Asignar contenidos')->assertSee('Hay varios perfiles llamados Nora');
        $this->get('/panel')->assertOk()->assertSee('1 contenido')->assertSee('Sin contenidos todavía');
    }

    public function test_dashboard_guides_through_the_next_missing_step_until_the_family_is_ready(): void
    {
        $user = $this->adult();
        $family = $user->family;
        $this->actingAsAdult($user);

        $this->get('/panel')->assertOk()->assertSee('Vamos a preparar la tele')->assertSee('0 de 4')
            ->assertSee('Crear perfil')->assertSee(route('onboarding.profile'), false)->assertDontSee('Probar en este navegador');

        $profile = $this->profile($family, 'Nora');
        $this->get('/panel')->assertOk()->assertSee('Casi lista')->assertSee('1 de 4')->assertSee('Ver series')->assertSee(route('lists.public'), false);

        $publisher = $this->adult(['is_admin' => true]);
        $source = $publisher->family->lists()->create(['name' => 'Serie', 'is_public' => true, 'languages' => ['es']]);
        $this->youtubeContent($publisher->family, ['list_id' => $source->id]);
        $family->lists()->create(['name' => 'Serie', 'source_id' => $source->id])->profiles()->attach($profile);
        $this->get('/panel')->assertOk()->assertSee('2 de 4')->assertSee('Conecta tu televisión')->assertSee('Probar en este navegador');

        $this->device($family);
        $this->get('/panel')->assertOk()->assertSee('3 de 4')->assertSee('Crear PIN');

        $family->setPin('1234');
        $this->get('/panel')->assertOk()->assertDontSee('Casi lista')->assertDontSee('gs-steps', false)->assertSee('Acciones rápidas');
    }

    public function test_admin_navigation_has_five_tabs_and_account_keeps_existing_preferences(): void
    {
        $user = $this->adult(['is_admin' => true, 'name' => 'Jon']);
        $this->actingAsAdult($user);
        $this->get('/panel')->assertOk()->assertSee('Hola, Jon')->assertSee('Modo TV')->assertSee('--tabs: 5', false)->assertDontSee('>Admin<', false);
        $this->get('/panel/cuenta')->assertOk()->assertSee('role="switch"', false)->assertSee('Administración')->assertDontSee('Cambiar contraseña');
        $this->put('/panel/cuenta', ['name' => 'Jon', 'family_name' => 'Casa', 'autoplay_next' => '1', 'youtube_controls' => 'auto', 'daily_limit_mode' => 'off'])->assertRedirect();
        $this->assertTrue($user->family->fresh()->autoplay_next);
        $this->put('/panel/cuenta', ['name' => 'Jon', 'family_name' => 'Casa', 'autoplay_next' => '0', 'youtube_controls' => 'auto', 'daily_limit_mode' => 'off'])->assertRedirect();
        $this->assertFalse($user->family->fresh()->autoplay_next);
        $this->actingAsAdult($this->adult())->get('/panel/cuenta')->assertOk()->assertDontSee('href="'.route('admin.dashboard').'"', false);
    }

    public function test_drag_requests_reuse_existing_ordering_and_enforce_ownership(): void
    {
        $user = $this->adult();
        $a = $user->family->lists()->create(['name' => 'A', 'position' => 0]);
        $b = $user->family->lists()->create(['name' => 'B', 'position' => 1]);
        $other = $this->adult()->family->lists()->create(['name' => 'Ajena']);
        $this->actingAsAdult($user)->postJson(route('lists.move', $b), ['direction' => 'up'])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame([$b->id, $a->id], $user->family->lists()->pluck('id')->all());
        $this->postJson(route('lists.move', $other), ['direction' => 'up'])->assertNotFound();
    }
}
