<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DevicePairing;
use App\Models\Family;
use App\Services\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): array
    {
        $adult = $this->adult();
        $list = $adult->family->lists()->create(['name' => 'Series', 'is_public' => true, 'languages' => ['es'], 'age_min' => 3, 'age_max' => 6]);
        $this->youtubeContent($adult->family, ['list_id' => $list->id]);

        return ['name' => 'Nora', 'age' => '3-6', 'lists' => [$list->id]];
    }

    private function provisional(array $draft): string
    {
        $p = $this->postJson('/api/tv/pairings')->assertCreated()->json();
        $token = $this->withHeader('X-Pairing-Secret', $p['secret'])->postJson('/api/tv/setup/'.$p['id'], $draft)->assertCreated()->json('token');
        $this->withHeader('X-Pairing-Secret', '');

        return $token;
    }

    public function test_tv_can_prepare_and_play_without_an_adult_but_cannot_unlock(): void
    {
        $draft = $this->draft();
        $token = $this->provisional($draft);
        $session = $this->tv($token)->getJson('/api/tv/session')->assertOk()->assertJsonPath('family.provisional', true)->assertJsonPath('family.has_pin', false)->json();
        $catalog = $this->getJson('/api/tv/profiles/'.$session['profiles'][0]['id'].'/catalog')->assertOk()->json();
        $this->assertNotEmpty($catalog['items']);
        $this->getJson('/api/tv/contents/'.$catalog['items'][0]['id'].'/play?profile='.$session['profiles'][0]['id'])->assertOk();
        $this->postJson('/api/tv/adult/unlock', ['pin' => '1234'])->assertStatus(409);
        $this->getJson('/api/tv/adult/catalog')->assertForbidden();
    }

    public function test_setup_requires_the_secret_and_valid_public_lists(): void
    {
        $draft = $this->draft();
        $p = $this->postJson('/api/tv/pairings')->json();
        $this->postJson('/api/tv/setup/'.$p['id'], $draft)->assertNotFound();
        $this->withHeader('X-Pairing-Secret', $p['secret'])->postJson('/api/tv/setup/'.$p['id'], array_replace($draft, ['lists' => [99999]]))->assertUnprocessable();
        $this->assertSame(0, Family::whereNotNull('provisional_until')->count());
        $this->postJson('/api/tv/setup/'.$p['id'], $draft)->assertCreated();
        $this->postJson('/api/tv/setup/'.$p['id'], $draft)->assertGone();
        $this->getJson('/api/tv/pairings/'.$p['id'])->assertOk()->assertJsonPath('status', 'paired');
        $this->assertSame(1, Family::whereNotNull('provisional_until')->count());
    }

    public function test_provisional_tv_can_be_saved_and_get_a_pin_without_duplicate_profiles(): void
    {
        $draft = $this->draft();
        $token = $this->provisional($draft);
        $p = $this->tv($token)->postJson('/api/tv/setup-link')->assertCreated()->json();
        $this->post('/empezar/tv', ['code' => $p['code'], 'mode' => 'prepare'])->assertRedirect('/empezar/cuenta');
        $adult = $this->adult();
        $this->actingAsAdult($adult, true);
        $this->get('/empezar/tv/confirmar')->assertOk()->assertSee('Nora');
        $this->post('/empezar/tv/confirmar', ['choice' => 'add', 'pin' => '1234', 'pin_confirmation' => '1234'])->assertRedirect('/panel');
        $this->assertTrue($adult->family->fresh()->checkPin('1234'));
        $this->assertSame(1, $adult->family->profiles()->count());
        $this->assertSame(0, Family::whereNotNull('provisional_until')->count());
        $this->tv($token)->getJson('/api/tv/session')->assertOk()->assertJsonPath('family.provisional', false);
        $this->post('/empezar/tv/confirmar', ['choice' => 'add'])->assertSessionHasErrors('code');
        $this->assertSame(1, $adult->family->profiles()->count());
    }

    public function test_existing_family_can_choose_its_profiles_and_keeps_its_pin(): void
    {
        $token = $this->provisional($this->draft());
        $p = $this->tv($token)->postJson('/api/tv/setup-link')->json();
        $adult = $this->adult();
        $this->profile($adult->family, 'Leo');
        $adult->family->setPin('5678');
        $this->actingAsAdult($adult, true)->post('/empezar/tv', ['code' => $p['code'], 'mode' => 'existing'])->assertRedirect('/empezar/tv/confirmar');
        $this->post('/empezar/tv/confirmar', ['choice' => 'existing', 'pin' => '1234'])->assertRedirect('/panel');
        $this->assertSame(['Leo'], $adult->family->profiles()->pluck('name')->all());
        $this->assertTrue($adult->family->fresh()->checkPin('5678'));
    }

    public function test_new_adult_verifies_email_before_saving_the_tv(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $token = $this->provisional($this->draft());
        $p = $this->tv($token)->postJson('/api/tv/setup-link')->json();
        $this->post('/empezar/tv', ['code' => $p['code'], 'mode' => 'prepare'])->assertRedirect('/empezar/cuenta');
        $this->get('/empezar/cuenta')->assertOk();
        $this->post('/entrar', [
            'email' => 'nueva@example.com', 'onboarding' => '1', 'terms' => '1',
            'form_token' => \Illuminate\Support\Facades\Crypt::encryptString((string) now()->subSeconds(5)->timestamp),
        ])->assertRedirect('/empezar/codigo');
        $this->get('/empezar/codigo')->assertOk();
        $mail = \Illuminate\Support\Facades\Mail::sent(\App\Mail\VerificationCodeMail::class)->first();
        $this->post('/entrar/codigo', ['code' => $mail->code])->assertRedirect('/empezar/tv/confirmar');
        $this->get('/empezar/tv/confirmar')->assertOk();
        $this->post('/empezar/tv/confirmar', ['choice' => 'add', 'pin' => '1234', 'pin_confirmation' => '0000'])->assertSessionHasErrors('pin');
        $this->post('/empezar/tv/confirmar', ['choice' => 'add', 'pin' => '1234', 'pin_confirmation' => '1234'])->assertRedirect('/panel');
        $this->tv($token)->postJson('/api/tv/adult/unlock', ['pin' => '1234'])->assertOk();
    }

    public function test_saved_tv_cannot_be_claimed_by_another_family(): void
    {
        $owner = $this->adult();
        [$device, $token] = $this->device($owner->family);
        $p = $this->tv($token)->postJson('/api/tv/setup-link')->json();
        $other = $this->adult();
        $this->profile($other->family);
        $this->actingAsAdult($other, true)->post('/empezar/tv', ['code' => $p['code'], 'mode' => 'existing']);
        $this->post('/empezar/tv/confirmar', ['choice' => 'existing', 'pin' => '1234', 'pin_confirmation' => '1234'])->assertSessionHasErrors('code');
        $this->assertSame($owner->family_id, $device->fresh()->family_id);
    }

    public function test_mobile_handoff_preserves_draft_extends_code_and_links_once(): void
    {
        $draft = $this->draft();
        $p = $this->postJson('/api/tv/pairings', ['draft' => $draft])->json();
        $this->post('/empezar/tv', ['code' => $p['code'], 'mode' => 'prepare'])->assertRedirect('/empezar');
        $this->assertSame('Nora', session(Onboarding::DRAFT_KEY)['name']);
        $this->travel(15)->minutes();
        $this->withHeader('X-Pairing-Secret', $p['secret'])->getJson('/api/tv/pairings/'.$p['id'])->assertOk()->assertJsonPath('status', 'pending');
        $adult = $this->adult();
        $this->actingAsAdult($adult, true)->post('/empezar/tele', ['next' => 'connect'])->assertRedirect('/empezar/tv/confirmar');
        $this->post('/empezar/tv/confirmar', ['choice' => 'add', 'pin' => '1234', 'pin_confirmation' => '1234'])->assertRedirect('/panel');
        $this->getJson('/api/tv/pairings/'.$p['id'])->assertOk()->assertJsonPath('status', 'paired');
        $this->getJson('/api/tv/pairings/'.$p['id'])->assertGone();
    }

    public function test_expired_code_keeps_draft_and_only_inactive_provisional_families_are_pruned(): void
    {
        $token = $this->provisional($this->draft());
        $device = Device::where('token_hash', hash('sha256', $token))->firstOrFail();
        $p = $this->tv($token)->postJson('/api/tv/setup-link')->json();
        $this->post('/empezar/tv', ['code' => $p['code'], 'mode' => 'prepare']);
        $this->travel(61)->minutes();
        $this->actingAsAdult($this->adult(), true)->get('/empezar/tv/confirmar')->assertRedirect('/empezar/tv');
        $this->assertSame('Nora', session(Onboarding::DRAFT_KEY)['name']);
        $this->travel(29)->days();
        $this->tv($token)->getJson('/api/tv/session')->assertOk();
        $this->travel(2)->days();
        $this->artisan('nokoplay:prune')->assertSuccessful();
        $this->assertNotNull($device->fresh());
        $this->travel(31)->days();
        $this->artisan('nokoplay:prune')->assertSuccessful();
        $this->assertNull($device->fresh());
    }
}
