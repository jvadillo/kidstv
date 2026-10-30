<?php

namespace Tests\Feature;

use App\Models\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private const MP4_HEADER = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // Sin ffmpeg en el entorno de pruebas: el procesado marca el archivo como listo.
        config(['nokoplay.ffprobe' => '/nonexistent', 'nokoplay.ffmpeg' => '/nonexistent']);
    }

    private function upload(string $kind, string $name, string $bytes, array $fields): array
    {
        $start = $this->postJson('/panel/subidas', ['kind' => $kind, 'name' => $name, 'size' => strlen($bytes)])
            ->assertCreated()
            ->json();

        $chunkSize = 7;
        for ($offset = 0; $offset < strlen($bytes); $offset += $chunkSize) {
            $chunk = substr($bytes, $offset, $chunkSize);
            $this->call('POST', $start['chunk_url'], [], [], [], [
                'HTTP_X_UPLOAD_OFFSET' => (string) $offset,
                'CONTENT_LENGTH' => (string) strlen($chunk),
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_ACCEPT' => 'application/json',
            ], $chunk)->assertOk()->assertJson(['received' => $offset + strlen($chunk)]);
        }

        return $this->postJson($start['complete_url'], $fields)->json() + ['start' => $start];
    }

    public function test_adult_adds_youtube_mp4_and_mp3_and_assigns_them_per_profile(): void
    {
        Http::fake(['www.youtube.com/oembed*' => Http::response(['title' => 'Canción de los colores', 'author_name' => 'Canal'])]);

        $user = $this->adult();
        $family = $user->family;
        $ane = $this->profile($family, 'Ane');
        $iker = $this->profile($family, 'Iker');
        $this->actingAsAdult($user);

        $this->post('/panel/contenidos/youtube', [
            'url' => 'https://youtu.be/aqz-KE-bpKQ?si=abc',
            'profile_ids' => [$ane->id],
        ])->assertRedirect('/panel/listas/sin-lista');

        $youtube = Content::where('type', 'youtube')->firstOrFail();
        $this->assertSame('aqz-KE-bpKQ', $youtube->youtube_id);
        $this->assertSame('Canción de los colores', $youtube->title);
        $this->assertSame(Content::STATUS_READY, $youtube->status);

        $video = $this->upload('video', 'paseo.mp4', self::MP4_HEADER.str_repeat('v', 40), [
            'title' => 'Paseo por el monte', 'profile_ids' => [$iker->id],
        ]);
        $this->assertArrayHasKey('redirect', $video);

        $audio = $this->upload('audio', 'nana.mp3', 'ID3'.str_repeat("\x00", 7).str_repeat('a', 30), [
            'title' => 'Nana', 'profile_ids' => [$ane->id, $iker->id],
        ]);
        $this->assertArrayHasKey('redirect', $audio);

        $mp4 = Content::where('type', 'video')->firstOrFail();
        $mp3 = Content::where('type', 'audio')->firstOrFail();
        $this->assertSame(Content::STATUS_READY, $mp4->status);
        $this->assertSame(Content::STATUS_READY, $mp3->status);
        Storage::disk('local')->assertExists($mp4->file_path);
        $this->assertStringStartsWith('families/'.$family->id.'/media/', $mp4->file_path);

        $this->assertEqualsCanonicalizing([$youtube->id, $mp3->id], $ane->contents()->pluck('contents.id')->all());
        $this->assertEqualsCanonicalizing([$mp4->id, $mp3->id], $iker->contents()->pluck('contents.id')->all());

        $this->get('/panel/contenidos')->assertOk()->assertSee('Sin lista')->assertSee('2 vídeos · 1 audio');
        $this->get('/panel/listas/sin-lista')->assertOk()->assertSee('Paseo por el monte')->assertSee('Nana');
        $this->get('/panel/contenidos/'.$mp4->id.'/archivo')->assertOk();
    }

    public function test_youtube_errors_are_understandable(): void
    {
        $user = $this->adult();
        $this->actingAsAdult($user);

        $this->post('/panel/contenidos/youtube', ['url' => 'https://vimeo.com/123'])
            ->assertSessionHasErrors(['url' => 'Este enlace no parece de un vídeo de YouTube.']);

        Http::fake(['www.youtube.com/oembed*' => Http::response('Unauthorized', 401)]);
        $this->post('/panel/contenidos/youtube', ['url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ'])
            ->assertSessionHasErrors(['url' => 'El propietario de este vídeo no permite reproducirlo fuera de YouTube.']);

        $this->postJson('/panel/contenidos/youtube/comprobar', ['url' => 'https://www.youtube.com/shorts/aqz-KE-bpKQ'])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'id' => 'aqz-KE-bpKQ']);

        $this->assertSame(0, Content::count());
    }

    public function test_youtube_url_formats_are_recognized(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
            'https://m.youtube.com/watch?feature=share&v=aqz-KE-bpKQ',
            'https://youtu.be/aqz-KE-bpKQ',
            'https://www.youtube.com/shorts/aqz-KE-bpKQ',
            'https://www.youtube.com/embed/aqz-KE-bpKQ',
            'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ',
            'youtube.com/live/aqz-KE-bpKQ?feature=share',
            'aqz-KE-bpKQ',
        ] as $url) {
            $this->assertSame('aqz-KE-bpKQ', \App\Services\YouTube::parseId($url), $url);
        }
        $this->assertNull(\App\Services\YouTube::parseId('https://example.com/watch?v=aqz-KE-bpKQ'));
    }

    public function test_uploads_validate_format_size_and_content(): void
    {
        $user = $this->adult();
        $this->actingAsAdult($user);

        $this->postJson('/panel/subidas', ['kind' => 'video', 'name' => 'peli.avi', 'size' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('file');

        $this->postJson('/panel/subidas', ['kind' => 'audio', 'name' => 'nana.mp3', 'size' => 61 * 1024 * 1024])
            ->assertStatus(422)->assertJsonValidationErrors('file');

        config(['nokoplay.family_quota_mb' => 1]);
        $this->postJson('/panel/subidas', ['kind' => 'video', 'name' => 'grande.mp4', 'size' => 2 * 1024 * 1024])
            ->assertStatus(422)->assertJsonFragment(['file' => ['No queda espacio en tu videoteca (límite de 1,0 MB). Elimina algún archivo para subir más.']]);
        config(['nokoplay.family_quota_mb' => 3072]);

        // Un archivo .mp4 que en realidad no es un vídeo se rechaza al completar.
        $result = $this->upload('video', 'falso.mp4', 'esto no es un video en absoluto', ['title' => 'Falso']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertSame(0, Content::count());
    }

    public function test_chunks_must_arrive_in_order_and_retries_are_idempotent(): void
    {
        $user = $this->adult();
        $this->actingAsAdult($user);
        $start = $this->postJson('/panel/subidas', ['kind' => 'video', 'name' => 'a.mp4', 'size' => 20])->json();

        $send = fn (int $offset, string $chunk) => $this->call('POST', $start['chunk_url'], [], [], [], [
            'HTTP_X_UPLOAD_OFFSET' => (string) $offset,
            'CONTENT_LENGTH' => (string) strlen($chunk),
            'HTTP_ACCEPT' => 'application/json',
        ], $chunk);

        $send(10, str_repeat('b', 10))->assertStatus(409)->assertJson(['received' => 0]);
        $send(0, str_repeat('a', 10))->assertOk()->assertJson(['received' => 10]);
        $send(0, str_repeat('a', 10))->assertOk()->assertJson(['received' => 10]);
        $send(10, str_repeat('b', 10))->assertOk()->assertJson(['received' => 20]);
    }

    public function test_deleting_content_removes_files_and_it_disappears_from_tv(): void
    {
        $user = $this->adult();
        $family = $user->family;
        $profile = $this->profile($family);
        Storage::disk('local')->put('families/'.$family->id.'/media/x.mp4', 'video');
        $content = $family->contents()->create([
            'type' => 'video', 'title' => 'Para borrar', 'status' => 'ready',
            'file_path' => 'families/'.$family->id.'/media/x.mp4', 'mime' => 'video/mp4', 'file_size' => 5,
        ]);
        $content->profiles()->attach($profile);
        [, $token] = $this->device($family);

        $this->tv($token)->getJson('/api/tv/profiles/'.$profile->id.'/catalog')->assertJsonCount(1, 'items');

        $this->actingAsAdult($user)->delete('/panel/contenidos/'.$content->id)->assertRedirect('/panel/contenidos');

        Storage::disk('local')->assertMissing('families/'.$family->id.'/media/x.mp4');
        $this->tv($token)->getJson('/api/tv/profiles/'.$profile->id.'/catalog')->assertJsonCount(0, 'items');
        $this->tv($token)->getJson('/api/tv/contents/'.$content->id.'/play?profile='.$profile->id)->assertNotFound();
    }

    public function test_hidden_content_is_not_shown_on_tv_and_order_can_change(): void
    {
        $user = $this->adult();
        $family = $user->family;
        $profile = $this->profile($family);
        $a = $this->youtubeContent($family, ['title' => 'A']);
        $b = $this->youtubeContent($family, ['title' => 'B']);
        $profile->contents()->attach([$a->id, $b->id]);
        [, $token] = $this->device($family);
        $this->actingAsAdult($user);

        // Más reciente primero; subir A lo pone delante.
        $titles = fn () => collect($this->tv($token)->getJson('/api/tv/profiles/'.$profile->id.'/catalog')->json('items'))->pluck('title')->all();
        $this->assertSame(['B', 'A'], $titles());
        $this->post('/panel/contenidos/'.$a->id.'/mover', ['direction' => 'up']);
        $this->assertSame(['A', 'B'], $titles());

        $this->post('/panel/contenidos/'.$a->id.'/visibilidad', ['action' => 'hide']);
        $this->assertSame(['B'], $titles());
        $this->get('/panel/contenidos/'.$a->id)->assertSee('Oculto');
    }

    public function test_library_shows_lists_in_order_and_new_lists_go_on_top(): void
    {
        $user = $this->adult();
        $family = $user->family;
        $ane = $this->profile($family, 'Ane');
        $this->actingAsAdult($user);

        $this->post('/panel/listas', ['name' => 'Cuentos', 'languages' => ['es']]);
        $cuentos = $family->lists()->where('name', 'Cuentos')->first();
        $this->youtubeContent($family, ['title' => 'Caperucita', 'list_id' => $cuentos->id])->profiles()->attach($ane);
        $this->youtubeContent($family, ['title' => 'Pinocho', 'list_id' => $cuentos->id]);
        $this->youtubeContent($family, ['title' => 'Suelto']);
        // Al crear una lista se entra en ella para llenarla.
        $this->post('/panel/listas', ['name' => 'Canciones', 'languages' => ['en', 'es']])->assertSessionHas('status')
            ->assertRedirect('/panel/listas/'.$family->lists()->where('name', 'Canciones')->value('id'));

        $this->assertSame(['Canciones', 'Cuentos'], $family->lists()->pluck('name')->all());

        // Una tarjeta por lista, en el orden de la tele, con su recuento y quién la ve; al final, «Sin lista».
        $this->get('/panel/contenidos')->assertOk()
            ->assertSeeInOrder(['Tus listas', 'Canciones', 'Vacía', 'Español, Inglés', 'Cuentos', '2 vídeos', 'Español', 'La ven: Ane', 'Sin lista', '1 vídeo'])
            ->assertSee('list-thumb-stack', false)
            ->assertDontSee('Caperucita');

        $this->post('/panel/listas/'.$cuentos->id.'/mover', ['direction' => 'up']);
        $this->assertSame(['Cuentos', 'Canciones'], $family->lists()->pluck('name')->all());
    }

    public function test_each_list_is_managed_on_its_own_screen(): void
    {
        $user = $this->adult();
        $family = $user->family;
        $cuentos = $family->lists()->create(['name' => 'Cuentos']);
        $vacia = $family->lists()->create(['name' => 'Canciones']);
        $caperucita = $this->youtubeContent($family, ['title' => 'Caperucita', 'list_id' => $cuentos->id]);
        $this->youtubeContent($family, ['title' => 'Pinocho', 'list_id' => $cuentos->id]);
        $this->youtubeContent($family, ['title' => 'Suelto']);
        $this->actingAsAdult($user);

        $this->get('/panel/listas/'.$cuentos->id)->assertOk()
            ->assertSeeInOrder(['Pinocho', 'Caperucita'])
            ->assertDontSee('Suelto')
            ->assertSee('lista='.$cuentos->id, false);
        $this->get('/panel/listas/'.$vacia->id)->assertOk()->assertSee('Añadir contenido')->assertSee('lista='.$vacia->id, false);
        $this->get('/panel/listas/sin-lista')->assertOk()->assertSee('Suelto')->assertDontSee('Caperucita');

        // Borrar un contenido desde la lista vuelve a la lista.
        $this->delete('/panel/contenidos/'.$caperucita->id)->assertRedirect('/panel/listas/'.$cuentos->id);
        $this->get('/panel/contenidos/'.$caperucita->id)->assertNotFound();

        $this->put('/panel/listas/'.$cuentos->id, ['name' => 'Cuentos de siempre', 'languages' => ['eu']])->assertSessionHas('status');
        $this->assertSame(['eu'], $cuentos->fresh()->languages);
        $this->delete('/panel/listas/'.$cuentos->id)->assertRedirect('/panel/contenidos');
        $this->assertSame(['Canciones'], $family->lists()->pluck('name')->all());
        $this->get('/panel/listas/'.$cuentos->id)->assertNotFound();
    }

    public function test_contents_move_within_their_list(): void
    {
        $user = $this->adult();
        $family = $user->family;
        $list = $family->lists()->create(['name' => 'Canciones']);
        // Orden global (más reciente primero): C, B, A; B no está en la lista.
        $a = $this->youtubeContent($family, ['title' => 'A', 'list_id' => $list->id]);
        $this->youtubeContent($family, ['title' => 'B']);
        $this->youtubeContent($family, ['title' => 'C', 'list_id' => $list->id]);
        $this->actingAsAdult($user);

        $titles = fn () => $family->contents()->ordered()->pluck('title')->all();
        // Subir A la cambia por C, su vecina en la lista, no por B.
        $this->post('/panel/contenidos/'.$a->id.'/mover', ['direction' => 'up']);
        $this->assertSame(['A', 'B', 'C'], $titles());

        // C sube saltándose B, que está en otra lista.
        $c = $family->contents()->where('title', 'C')->first();
        $this->post('/panel/contenidos/'.$c->id.'/mover', ['direction' => 'up']);
        $this->assertSame(['C', 'B', 'A'], $titles());
    }

    public function test_lists_and_profiles_are_managed(): void
    {
        $user = $this->adult();
        $this->actingAsAdult($user);

        $this->post('/panel/perfiles', ['name' => 'Ane', 'avatar' => 'luna'])->assertRedirect();
        $this->post('/panel/perfiles', ['name' => '', 'avatar' => 'invalido'])->assertSessionHasErrors(['name', 'avatar']);
        $this->post('/panel/listas', ['name' => 'Canciones', 'languages' => ['es']])->assertSessionHas('status');
        $this->post('/panel/listas', ['name' => 'Canciones', 'languages' => ['es']])->assertSessionHasErrors('name');
        // El idioma es obligatorio y tiene que ser uno de los ofrecidos.
        $this->post('/panel/listas', ['name' => 'Cuentos'])->assertSessionHasErrors('languages');
        $this->post('/panel/listas', ['name' => 'Cuentos', 'languages' => ['xx']])->assertSessionHasErrors('languages.0');

        $this->assertSame(['Ane'], $user->family->profiles()->pluck('name')->all());
        $this->assertSame(['Canciones'], $user->family->lists()->pluck('name')->all());
    }
}
