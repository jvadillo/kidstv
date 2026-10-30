<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_link_the_web_app_manifest(): void
    {
        $this->get('/entrar')->assertOk()
            ->assertSee('<link rel="manifest" href="'.url('manifest.webmanifest').'">', false)
            ->assertSee('apple-mobile-web-app-capable', false);

        $tv = file_get_contents(public_path('tv/index.html'));
        $this->assertStringContainsString('<link rel="manifest" href="manifest.webmanifest">', $tv);
    }

    public function test_manifests_are_installable_and_their_icons_exist(): void
    {
        foreach (['manifest.webmanifest' => '/', 'tv/manifest.webmanifest' => '/tv/'] as $file => $scope) {
            $manifest = json_decode(file_get_contents(public_path($file)), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame($scope, $manifest['scope']);
            $this->assertSame($scope, $manifest['start_url']);
            $this->assertContains($manifest['display'], ['standalone', 'fullscreen']);

            $sizes = [];
            foreach ($manifest['icons'] as $icon) {
                $path = public_path(ltrim($icon['src'], '/'));
                $this->assertFileExists($path);
                [$width, $height] = getimagesize($path);
                $this->assertSame($icon['sizes'], $width.'x'.$height);
                $sizes[$icon['purpose']][] = $icon['sizes'];
            }
            // Chrome exige al menos 192 y 512 px; Android usa además los «maskable».
            $this->assertEqualsCanonicalizing(['192x192', '512x512'], $sizes['any']);
            $this->assertEqualsCanonicalizing(['192x192', '512x512'], $sizes['maskable']);
        }

        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
    }

    public function test_panel_offers_install_and_tv_mode_can_stay_in_the_installed_app(): void
    {
        $this->actingAsAdult($this->adult());

        $this->get('/panel/cuenta')->assertOk()
            ->assertSee('data-pwa-install hidden', false)
            ->assertSee('data-pwa-install-button', false)
            ->assertSee('data-tv-launch', false);
    }
}
