<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Datos de ejemplo para desarrollo local: php artisan db:seed */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create(['email' => 'familia@example.com', 'name' => 'Familia de prueba']);
        $family = $user->family;

        foreach (config('kidstv.default_categories') as $i => $name) {
            $family->categories()->create(['name' => $name, 'position' => $i]);
        }

        $ane = $family->profiles()->create(['name' => 'Ane', 'avatar' => 'estrella', 'position' => 1]);
        $family->profiles()->create(['name' => 'Iker', 'avatar' => 'cohete', 'position' => 2]);

        $content = $family->contents()->create([
            'type' => Content::TYPE_YOUTUBE,
            'title' => 'Big Buck Bunny',
            'youtube_id' => 'aqz-KE-bpKQ',
            'status' => Content::STATUS_READY,
            'category_id' => $family->categories()->first()->id,
        ]);
        $content->profiles()->attach($ane);
    }
}
