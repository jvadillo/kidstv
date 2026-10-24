<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Flujo de aceptación 10: dos familias no pueden ver ni tocar los datos de la otra. */
class FamilyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_adults_cannot_read_or_modify_another_familys_data(): void
    {
        Storage::fake('local');
        $alice = $this->adult();
        $bob = $this->adult();
        $bobFamily = $bob->family;

        $profile = $this->profile($bobFamily, 'Hija de Bob');
        $category = $bobFamily->categories()->create(['name' => 'Privada']);
        Storage::disk('local')->put('families/'.$bobFamily->id.'/media/b.mp4', 'secreto');
        $content = $bobFamily->contents()->create([
            'type' => 'video', 'title' => 'Vídeo privado de Bob', 'status' => 'ready', 'mime' => 'video/mp4',
            'file_path' => 'families/'.$bobFamily->id.'/media/b.mp4', 'file_size' => 7,
        ]);
        [$device] = $this->device($bobFamily, 'Tele de Bob');
        $session = $bob->authSessions()->create(['token_hash' => str_repeat('b', 64), 'expires_at' => now()->addDay()]);

        $this->actingAsAdult($alice, confirmed: true);

        $this->get('/panel/perfiles/'.$profile->id)->assertNotFound();
        $this->put('/panel/perfiles/'.$profile->id, ['name' => 'X', 'avatar' => 'sol'])->assertNotFound();
        $this->put('/panel/perfiles/'.$profile->id.'/contenidos', ['content_ids' => []])->assertNotFound();
        $this->delete('/panel/perfiles/'.$profile->id)->assertNotFound();

        $this->get('/panel/contenidos/'.$content->id)->assertNotFound();
        $this->get('/panel/contenidos/'.$content->id.'/archivo')->assertNotFound();
        $this->get('/panel/contenidos/'.$content->id.'/portada')->assertNotFound();
        $this->put('/panel/contenidos/'.$content->id, ['title' => 'Hackeado'])->assertNotFound();
        $this->post('/panel/contenidos/'.$content->id.'/visibilidad', ['action' => 'hide'])->assertNotFound();
        $this->delete('/panel/contenidos/'.$content->id)->assertNotFound();

        $this->put('/panel/categorias/'.$category->id, ['name' => 'X'])->assertNotFound();
        $this->delete('/panel/categorias/'.$category->id)->assertNotFound();

        $this->put('/panel/televisores/'.$device->id, ['name' => 'X'])->assertNotFound();
        $this->delete('/panel/televisores/'.$device->id)->assertNotFound();
        $this->delete('/panel/cuenta/sesiones/'.$session->id)->assertNotFound();

        // Tampoco puede asignar contenidos ni perfiles ajenos a los suyos.
        $own = $this->profile($alice->family, 'Hijo de Alice');
        $this->put('/panel/perfiles/'.$own->id.'/contenidos', ['content_ids' => [$content->id]])->assertSessionHasErrors('content_ids.0');
        $this->post('/panel/contenidos/youtube', ['url' => 'aqz-KE-bpKQ', 'title' => 'x', 'profile_ids' => [$profile->id], 'category_id' => $category->id])
            ->assertSessionHasErrors(['profile_ids.0', 'category_id']);

        $this->get('/panel/contenidos')->assertDontSee('Vídeo privado de Bob');
        $this->get('/panel')->assertDontSee('Tele de Bob');
        $export = $this->get('/panel/cuenta/exportar')->streamedContent();
        $this->assertStringNotContainsString('Vídeo privado de Bob', $export);

        $this->assertSame('Vídeo privado de Bob', $content->fresh()->title);
        $this->assertModelExists($profile);
        $this->assertModelExists($device);
        $this->assertModelExists($session);
    }

    public function test_a_tv_cannot_reach_another_familys_profiles_content_or_files(): void
    {
        Storage::fake('local');
        $aliceFamily = $this->adult()->family;
        $bobFamily = $this->adult()->family;

        $bobProfile = $this->profile($bobFamily);
        Storage::disk('local')->put('families/'.$bobFamily->id.'/media/b.mp4', 'secreto');
        $bobContent = $bobFamily->contents()->create([
            'type' => 'video', 'title' => 'Privado', 'status' => 'ready', 'mime' => 'video/mp4',
            'file_path' => 'families/'.$bobFamily->id.'/media/b.mp4',
        ]);
        $bobContent->profiles()->attach($bobProfile);
        $bobFamily->setPin('9999');

        [$aliceDevice, $aliceToken] = $this->device($aliceFamily);
        [, $bobToken] = $this->device($bobFamily);

        $this->tv($aliceToken)->getJson('/api/tv/profiles/'.$bobProfile->id.'/catalog')->assertNotFound();
        $this->tv($aliceToken)->getJson('/api/tv/contents/'.$bobContent->id.'/play?profile='.$bobProfile->id)->assertNotFound();
        $this->tv($aliceToken)->postJson('/api/tv/contents/'.$bobContent->id.'/problem', ['code' => 150])->assertNotFound();
        $this->assertSame([], $this->tv($aliceToken)->getJson('/api/tv/session')->json('profiles'));
        // El PIN de otra familia no abre la zona de adultos.
        $this->tv($aliceToken)->postJson('/api/tv/adult/unlock', ['pin' => '9999'])->assertStatus(409);

        // Una URL firmada válida para Bob no sirve con el identificador de la tele de Alice.
        $bobUrl = $this->tv($bobToken)->getJson('/api/tv/contents/'.$bobContent->id.'/play?profile='.$bobProfile->id)->json('url');
        $this->get($bobUrl)->assertOk();
        $tampered = preg_replace('/([?&])d=\d+/', '$1d='.$aliceDevice->id, $bobUrl);
        $this->get($tampered)->assertForbidden();
        $forged = \Illuminate\Support\Facades\URL::temporarySignedRoute('media.show', now()->addHour(), ['content' => $bobContent->id, 'variant' => 'file', 'd' => $aliceDevice->id]);
        $this->get($forged)->assertNotFound();
    }
}
