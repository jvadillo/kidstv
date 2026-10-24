<?php

namespace Tests;

use App\Models\Content;
use App\Models\Device;
use App\Models\Family;
use App\Models\Profile;
use App\Models\User;
use App\Services\AdultSessions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['kidstv.email_dns_check' => false]);
    }

    protected function adult(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /** Actúa como adulto con una sesión persistente válida (como tras verificar el correo). */
    protected function actingAsAdult(User $user, bool $confirmed = false): static
    {
        $session = $user->authSessions()->create([
            'token_hash' => hash('sha256', 'test-'.$user->id.'-'.uniqid()),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $data = [AdultSessions::SESSION_KEY => $session->id];
        if ($confirmed) {
            $data[AdultSessions::CONFIRMED_KEY] = now()->timestamp;
        }

        return $this->actingAs($user)->withSession($data);
    }

    protected function profile(Family $family, string $name = 'Ane'): Profile
    {
        return $family->profiles()->create(['name' => $name, 'avatar' => 'estrella']);
    }

    protected function youtubeContent(Family $family, array $attributes = []): Content
    {
        return $family->contents()->create($attributes + [
            'type' => Content::TYPE_YOUTUBE,
            'title' => 'Vídeo de prueba',
            'youtube_id' => 'aqz-KE-bpKQ',
            'status' => Content::STATUS_READY,
        ]);
    }

    /** @return array{0: Device, 1: string} */
    protected function device(Family $family, string $name = 'Tele'): array
    {
        return Device::issue($family, $name, 'webos', 'Mozilla/5.0 (Web0S; Linux/SmartTV)');
    }

    protected function tv(string $token): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
