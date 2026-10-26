<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\VideoList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicListTest extends TestCase
{
    use RefreshDatabase;

    /** Familia administradora con una lista pública de dos vídeos (uno oculto). */
    private function publicList(): VideoList
    {
        $admin = $this->adult(['is_admin' => true]);
        $list = $admin->family->lists()->create(['name' => 'Canciones en inglés', 'is_public' => true, 'description' => 'Para aprender jugando']);
        $this->youtubeContent($admin->family, ['title' => 'Baby Shark', 'youtube_id' => 'aaaaaaaaaaa', 'list_id' => $list->id, 'duration' => 125]);
        $this->youtubeContent($admin->family, ['title' => 'Oculto', 'youtube_id' => 'bbbbbbbbbbb', 'list_id' => $list->id,
            'status' => Content::STATUS_UNAVAILABLE, 'status_message' => Content::MESSAGE_HIDDEN]);

        return $list;
    }

    public function test_family_adds_a_public_list_and_sees_it_on_the_tv_for_the_chosen_profiles(): void
    {
        $list = $this->publicList();
        $user = $this->adult();
        $ane = $this->profile($user->family, 'Ane');
        $iker = $this->profile($user->family, 'Iker');
        $this->actingAsAdult($user);

        $this->get('/panel/listas')->assertOk()->assertSee('Listas públicas');
        $this->get('/panel/listas/publicas')->assertOk()->assertSee('Canciones en inglés')->assertSee('Para aprender jugando')->assertSee('1 vídeo');
        $this->get('/panel/listas/publicas/'.$list->id)->assertOk()->assertSee('Baby Shark')->assertDontSee('Oculto');

        $this->post('/panel/listas/publicas/'.$list->id, ['profile_ids' => [$ane->id]])->assertRedirect('/panel/listas');
        $link = $user->family->lists()->where('source_id', $list->id)->firstOrFail();
        $this->get('/panel/listas')->assertSee('Canciones en inglés')->assertSee('Lista pública');

        [, $token] = $this->device($user->family);
        $catalog = $this->tv($token)->getJson('/api/tv/profiles/'.$ane->id.'/catalog')->assertOk();
        $catalog->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'Baby Shark')
            ->assertJsonPath('items.0.list_id', $link->id)
            ->assertJsonPath('items.0.duration', 125)
            ->assertJsonPath('lists.0.name', 'Canciones en inglés');
        $this->tv($token)->getJson('/api/tv/profiles/'.$iker->id.'/catalog')->assertOk()->assertJsonCount(0, 'items');

        $video = Content::where('youtube_id', 'aaaaaaaaaaa')->first();
        $this->tv($token)->getJson('/api/tv/contents/'.$video->id.'/play?profile='.$ane->id)->assertOk()->assertJsonPath('youtube_id', 'aaaaaaaaaaa');
        $this->tv($token)->getJson('/api/tv/contents/'.$video->id.'/play?profile='.$iker->id)->assertNotFound();

        // Quien ve la lista se elige después desde «Listas».
        $this->put('/panel/listas/'.$link->id.'/perfiles', ['profile_ids' => [$ane->id, $iker->id]])->assertSessionHas('status');
        $this->tv($token)->getJson('/api/tv/profiles/'.$iker->id.'/catalog')->assertJsonCount(1, 'items');
    }

    public function test_changes_by_the_admin_reach_every_family_and_families_cannot_edit_it(): void
    {
        $list = $this->publicList();
        $admin = $list->family->users()->first();
        $user = $this->adult();
        $ane = $this->profile($user->family);
        $link = $user->family->lists()->create(['name' => $list->name, 'source_id' => $list->id]);
        $link->profiles()->attach($ane);
        [, $token] = $this->device($user->family);
        $titles = fn () => collect($this->tv($token)->getJson('/api/tv/profiles/'.$ane->id.'/catalog')->json('items'))->pluck('title')->all();

        $this->assertSame(['Baby Shark'], $titles());
        $this->youtubeContent($admin->family, ['title' => 'Nuevo', 'youtube_id' => 'ccccccccccc', 'list_id' => $list->id]);
        Content::where('title', 'Baby Shark')->first()->update(['title' => 'Baby Shark (versión larga)']);
        $this->assertEqualsCanonicalizing(['Baby Shark (versión larga)', 'Nuevo'], $titles());

        // La familia no puede renombrarla ni meter sus propios vídeos.
        $own = $this->youtubeContent($user->family, ['youtube_id' => 'ddddddddddd']);
        $this->actingAsAdult($user);
        $this->put('/panel/listas/'.$link->id, ['name' => 'Mía'])->assertStatus(422);
        $this->put('/panel/contenidos/'.$own->id, ['title' => 'X', 'list_id' => $link->id])->assertSessionHasErrors('list_id');
        $this->put('/panel/contenidos/'.$own->id, ['title' => 'X', 'list_id' => $list->id])->assertSessionHasErrors('list_id');

        // Ni editar los vídeos del administrador desde su panel.
        $shared = Content::where('youtube_id', 'ccccccccccc')->first();
        $this->get('/panel/contenidos/'.$shared->id)->assertNotFound();

        // Quitarla de la videoteca no toca la original.
        $this->delete('/panel/listas/'.$link->id)->assertSessionHas('status');
        $this->assertSame([], $titles());
        $this->assertTrue($list->fresh()->is_public);
        $this->assertSame(2, $list->contents()->ready()->count());
    }

    public function test_private_lists_and_unpublished_lists_are_not_available(): void
    {
        $list = $this->publicList();
        $private = $list->family->lists()->create(['name' => 'Privada']);
        $user = $this->adult();
        $ane = $this->profile($user->family);
        $this->actingAsAdult($user);

        $this->get('/panel/listas/publicas/'.$private->id)->assertNotFound();
        $this->post('/panel/listas/publicas/'.$private->id, ['profile_ids' => [$ane->id]])->assertNotFound();
        // Tampoco con perfiles de otra familia.
        $this->post('/panel/listas/publicas/'.$list->id, ['profile_ids' => [$this->profile($list->family)->id]])->assertSessionHasErrors('profile_ids.0');

        $this->post('/panel/listas/publicas/'.$list->id, ['profile_ids' => [$ane->id]]);
        $video = Content::where('youtube_id', 'aaaaaaaaaaa')->first();
        [$device, $token] = $this->device($user->family);
        $this->tv($token)->getJson('/api/tv/contents/'.$video->id.'/play?profile='.$ane->id)->assertOk();

        // Al dejar de publicarla desaparece de las videotecas que la usaban.
        $admin = $list->family->users()->first();
        $this->actingAsAdult($admin)->delete('/admin/listas/'.$list->id.'/publicar')->assertSessionHas('status');
        $this->assertFalse($list->fresh()->is_public);
        $this->assertSame(0, $user->family->lists()->whereNotNull('source_id')->count());
        $this->tv($token)->getJson('/api/tv/contents/'.$video->id.'/play?profile='.$ane->id)->assertNotFound();
        $this->tv($token)->getJson('/api/tv/profiles/'.$ane->id.'/catalog')->assertJsonCount(0, 'items');
    }

    public function test_deleting_the_owner_account_removes_the_links_of_other_families(): void
    {
        $list = $this->publicList();
        $user = $this->adult();
        $user->family->lists()->create(['name' => $list->name, 'source_id' => $list->id]);

        $owner = $list->family->users()->first();
        $this->actingAsAdult($owner, confirmed: true)->delete('/panel/cuenta', ['confirmation' => 'ELIMINAR'])->assertRedirect('/');

        $this->assertSame(0, $user->family->lists()->count());
    }

    public function test_admin_creates_and_publishes_lists(): void
    {
        $admin = $this->adult(['is_admin' => true]);
        $existing = $admin->family->lists()->create(['name' => 'Dibujos']);
        $this->actingAsAdult($admin);

        $this->post('/admin/listas', ['name' => 'Ciencia', 'description' => 'Experimentos'])->assertSessionHas('status');
        $this->assertTrue($admin->family->lists()->where('name', 'Ciencia')->first()->is_public);
        $this->post('/admin/listas/'.$existing->id.'/publicar')->assertSessionHas('status');
        $this->assertTrue($existing->fresh()->is_public);

        $this->get('/admin/listas')->assertOk()->assertSee('Ciencia')->assertSee('Dibujos')->assertSee('Gestionar vídeos');
        $this->get('/panel/listas')->assertSee('Publicada');
        // Sus propias listas públicas ya están en su videoteca: no se ofrecen para añadir.
        $this->get('/panel/listas/publicas')->assertOk()->assertDontSee('Ciencia');
    }
}
