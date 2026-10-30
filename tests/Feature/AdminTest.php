<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Play;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_is_invisible_to_regular_adults(): void
    {
        $user = $this->adult();
        $other = $this->adult();

        $this->get('/admin')->assertRedirect('/entrar');
        $this->actingAsAdult($user);
        foreach (['/admin', '/admin/usuarios', '/admin/usuarios/'.$other->id, '/admin/listas'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post('/admin/usuarios/'.$other->id.'/bloqueo')->assertNotFound();
        $this->delete('/admin/usuarios/'.$other->id, ['confirmation' => $other->email])->assertNotFound();
        $this->get('/panel')->assertDontSee('/admin');
        $this->assertNull($other->fresh()->blocked_at);
    }

    public function test_admin_sees_platform_usage_and_user_details(): void
    {
        $admin = $this->adult(['is_admin' => true]);
        $user = $this->adult(['email' => 'familia@example.com']);
        $content = $this->youtubeContent($user->family);
        [$device] = $this->device($user->family);
        Play::create(['family_id' => $user->family_id, 'device_id' => $device->id, 'content_id' => $content->id,
            'content_type' => Content::TYPE_YOUTUBE, 'client_key' => 'abcdefgh1', 'seconds' => 1500]);
        Play::create(['family_id' => $user->family_id, 'device_id' => $device->id, 'content_id' => $content->id,
            'content_type' => Content::TYPE_YOUTUBE, 'client_key' => 'abcdefgh2', 'seconds' => 2400]);

        $this->actingAsAdult($admin);
        $this->get('/panel/cuenta')->assertSee(route('admin.dashboard'), false);
        $this->get('/admin')->assertOk()
            ->assertSee('Minutos vistos hoy')
            ->assertSee('1 h 05 min')
            ->assertSee('Minutos vistos por día');

        $this->get('/admin/usuarios?q=familia')->assertOk()->assertSee('familia@example.com')->assertDontSee($admin->email);
        $this->get('/admin/usuarios?orden=uso')->assertOk();
        $this->get('/admin/usuarios/'.$user->id)->assertOk()
            ->assertSee('familia@example.com')
            ->assertSee('Tele')
            ->assertSee('1 h 05 min');
    }

    public function test_admin_blocks_unblocks_promotes_and_deletes_accounts(): void
    {
        $admin = $this->adult(['is_admin' => true]);
        $user = $this->adult();
        $profile = $this->profile($user->family);
        [, $token] = $this->device($user->family);
        $user->authSessions()->create(['token_hash' => hash('sha256', 'x'), 'expires_at' => now()->addDay()]);

        $this->actingAsAdult($admin);
        $this->post('/admin/usuarios/'.$user->id.'/bloqueo')->assertSessionHas('status');
        $this->assertTrue($user->fresh()->isBlocked());
        $this->assertSame(0, $user->authSessions()->count());

        // Sus teles siguen vinculadas pero sin servicio.
        $this->tv($token)->getJson('/api/tv/session')->assertStatus(403)->assertJson(['error' => 'account_blocked']);
        $this->tv($token)->getJson('/api/tv/profiles/'.$profile->id.'/catalog')->assertStatus(403);

        $this->delete('/admin/usuarios/'.$user->id.'/bloqueo')->assertSessionHas('status');
        $this->tv($token)->getJson('/api/tv/session')->assertOk();

        $this->put('/admin/usuarios/'.$user->id.'/administrador', ['is_admin' => 1]);
        $this->assertTrue($user->fresh()->isAdmin());
        $this->put('/admin/usuarios/'.$user->id.'/administrador', ['is_admin' => 0]);
        $this->assertFalse($user->fresh()->isAdmin());

        // Nunca sobre la propia cuenta.
        $this->post('/admin/usuarios/'.$admin->id.'/bloqueo')->assertStatus(422);
        $this->put('/admin/usuarios/'.$admin->id.'/administrador', ['is_admin' => 0])->assertStatus(422);

        $this->delete('/admin/usuarios/'.$user->id, ['confirmation' => 'otra@cosa.com'])->assertSessionHasErrors('confirmation');
        $this->delete('/admin/usuarios/'.$user->id, ['confirmation' => $user->email])->assertRedirect('/admin/usuarios');
        $this->assertNull(User::find($user->id));
        $this->tv($token)->getJson('/api/tv/session')->assertStatus(401);
    }

    public function test_blocked_accounts_cannot_use_the_panel(): void
    {
        $user = $this->adult(['blocked_at' => now()]);

        $this->actingAsAdult($user)->get('/panel')->assertRedirect('/entrar');
    }

    public function test_admin_command_grants_and_revokes(): void
    {
        $user = $this->adult(['email' => 'jon@example.com']);

        $this->artisan('nokoplay:admin', ['email' => 'JON@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());
        $this->artisan('nokoplay:admin', ['email' => 'jon@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->isAdmin());
        $this->artisan('nokoplay:admin', ['email' => 'nadie@example.com'])->assertFailed();
    }
}
