<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Services\YouTubeSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YouTubeSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.youtube.key' => 'clave-de-prueba']);
    }

    private function fakeApi(): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/search*' => Http::response([
                'nextPageToken' => 'CAwQAA',
                'items' => [
                    ['id' => ['videoId' => 'aaaaaaaaaaa'], 'snippet' => ['liveBroadcastContent' => 'none']],
                    ['id' => ['videoId' => 'bbbbbbbbbbb'], 'snippet' => ['liveBroadcastContent' => 'live']],
                    ['id' => ['videoId' => 'ccccccccccc'], 'snippet' => ['liveBroadcastContent' => 'none']],
                    ['id' => ['videoId' => 'ddddddddddd'], 'snippet' => ['liveBroadcastContent' => 'none']],
                ],
            ]),
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [
                $this->video('ccccccccccc', 'Canción de los dinosaurios', 'PT3M5S', 2_500_000, kids: true),
                $this->video('aaaaaaaaaaa', 'Dinosaurios para niños', 'PT1H2M3S', 15_300),
                $this->video('ddddddddddd', 'No insertable', 'PT2M', 10, embeddable: false),
            ]]),
            'www.youtube.com/oembed*' => Http::response(['title' => 'Dinosaurios para niños', 'author_name' => 'Canal']),
        ]);
    }

    private function video(string $id, string $title, string $duration, int $views, bool $kids = false, bool $embeddable = true): array
    {
        return [
            'id' => $id,
            'snippet' => [
                'title' => $title,
                'channelTitle' => 'Canal infantil',
                'publishedAt' => '2025-03-01T10:00:00Z',
                'thumbnails' => ['medium' => ['url' => "https://i.ytimg.com/vi/{$id}/mqdefault.jpg"]],
            ],
            'contentDetails' => ['duration' => $duration],
            'statistics' => ['viewCount' => (string) $views],
            'status' => ['embeddable' => $embeddable, 'privacyStatus' => 'public', 'madeForKids' => $kids],
        ];
    }

    public function test_search_shows_safe_embeddable_results_with_thumbnail_title_and_duration(): void
    {
        $this->fakeApi();
        $user = $this->adult();
        $this->youtubeContent($user->family, ['youtube_id' => 'ccccccccccc', 'title' => 'Ya añadido']);

        $response = $this->actingAsAdult($user)->get('/panel/contenidos/buscar-youtube?q=dinosaurios&duracion=short')->assertOk();

        $response->assertSee('Dinosaurios para niños')
            ->assertSee('Canción de los dinosaurios')
            ->assertSee('https://i.ytimg.com/vi/aaaaaaaaaaa/mqdefault.jpg', false)
            ->assertSee('1:02:03')
            ->assertSee('3:05')
            ->assertSee('15 mil visualizaciones')
            ->assertSee('2,5 M visualizaciones')
            ->assertSee('Para niños')
            ->assertSee('En tu videoteca')
            ->assertSee('pagina=CAwQAA', false)
            ->assertDontSee('No insertable')
            ->assertDontSee('bbbbbbbbbbb');

        // Orden de relevancia de la búsqueda, no el de la respuesta de detalles.
        $this->assertLessThan(strpos($response->getContent(), 'Canción de los dinosaurios'), strpos($response->getContent(), 'Dinosaurios para niños'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/search')
            && $r['safeSearch'] === 'strict'
            && $r['videoEmbeddable'] === 'true'
            && $r['videoDuration'] === 'short'
            && $r['type'] === 'video');
    }

    public function test_results_are_cached_and_kids_filter_applies(): void
    {
        $this->fakeApi();
        $this->actingAsAdult($this->adult());

        $this->get('/panel/contenidos/buscar-youtube?q=dinosaurios')->assertOk();
        $this->get('/panel/contenidos/buscar-youtube?q=Dinosaurios&ninos=1')
            ->assertOk()
            ->assertSee('Canción de los dinosaurios')
            ->assertDontSee('Dinosaurios para niños');

        Http::assertSentCount(2); // una búsqueda + detalles; la segunda sale de la caché
    }

    public function test_adding_from_results_works_without_leaving_and_rejects_duplicates(): void
    {
        $this->fakeApi();
        $user = $this->adult();
        $family = $user->family;
        $ane = $this->profile($family, 'Ane');
        $category = $family->categories()->create(['name' => 'Aprender']);
        $this->actingAsAdult($user);

        $this->postJson('/panel/contenidos/youtube', [
            'url' => 'aaaaaaaaaaa', 'from_search' => 1, 'profile_ids' => [$ane->id], 'category_id' => $category->id,
        ])->assertCreated()->assertJson(['ok' => true, 'title' => 'Dinosaurios para niños']);

        $content = Content::where('youtube_id', 'aaaaaaaaaaa')->firstOrFail();
        $this->assertSame($category->id, $content->category_id);
        $this->assertSame([$ane->id], $content->profiles()->pluck('profiles.id')->all());

        $this->postJson('/panel/contenidos/youtube', ['url' => 'aaaaaaaaaaa', 'from_search' => 1])
            ->assertStatus(422)->assertJsonValidationErrors(['url' => 'Este vídeo ya está en tu videoteca.']);

        // Sin JavaScript: vuelve a los resultados con el aviso.
        $this->from('/panel/contenidos/buscar-youtube?q=dinosaurios')
            ->post('/panel/contenidos/youtube', ['url' => 'ccccccccccc', 'from_search' => 1])
            ->assertRedirect('/panel/contenidos/buscar-youtube?q=dinosaurios')
            ->assertSessionHas('status');
    }

    public function test_quota_and_configuration_errors_are_explained(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['errors' => [['reason' => 'quotaExceeded']]]], 403)]);
        $this->actingAsAdult($this->adult());

        $this->get('/panel/contenidos/buscar-youtube?q=peppa')
            ->assertOk()
            ->assertSee('Se ha alcanzado el límite diario de búsquedas en YouTube');
    }

    public function test_without_api_key_the_search_explains_and_entry_points_use_paste(): void
    {
        config(['services.youtube.key' => null]);
        Http::fake();
        $this->actingAsAdult($this->adult());

        $this->get('/panel/contenidos/buscar-youtube?q=peppa')->assertOk()->assertSee('El buscador no está disponible todavía.');
        $this->get('/panel')->assertSee(route('contents.youtube.create'), false)->assertDontSee(route('contents.youtube.search'), false);
        Http::assertNothingSent();
    }

    public function test_search_is_rate_limited_per_adult(): void
    {
        $this->fakeApi();
        $this->actingAsAdult($this->adult());

        for ($i = 0; $i < 40; $i++) {
            $this->get('/panel/contenidos/buscar-youtube?q=busqueda'.$i);
        }

        $this->get('/panel/contenidos/buscar-youtube?q=otra')->assertSee('Has hecho muchas búsquedas seguidas');
    }

    public function test_search_requires_an_adult_session_and_is_not_in_the_tv_api(): void
    {
        $this->get('/panel/contenidos/buscar-youtube?q=x')->assertRedirect('/entrar');
        [, $token] = $this->device($this->adult()->family);
        $this->tv($token)->getJson('/api/tv/search?q=x')->assertNotFound();
    }

    public function test_iso_durations_are_parsed(): void
    {
        $this->assertSame(185, YouTubeSearch::parseDuration('PT3M5S'));
        $this->assertSame(3723, YouTubeSearch::parseDuration('PT1H2M3S'));
        $this->assertSame(45, YouTubeSearch::parseDuration('PT45S'));
        $this->assertNull(YouTubeSearch::parseDuration('P0D'));
        $this->assertNull(YouTubeSearch::parseDuration('basura'));
        $this->assertSame('1:02:03', YouTubeSearch::formatDuration(3723));
    }
}
