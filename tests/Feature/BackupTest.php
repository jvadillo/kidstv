<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

// Sin RefreshDatabase: SQLite no permite VACUUM dentro de la transacción que abre ese trait.
class BackupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory(storage_path('backups'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('backups'));
        parent::tearDown();
    }

    public function test_two_backups_in_the_same_second_do_not_collide_and_old_ones_are_pruned(): void
    {
        $this->freezeSecond();

        $this->artisan('nokoplay:backup')->assertSuccessful();
        $this->artisan('nokoplay:backup')->assertSuccessful();
        $this->assertCount(2, glob(storage_path('backups/nokoplay-*.sqlite')));

        $this->artisan('nokoplay:backup', ['--keep' => 1])->assertSuccessful();
        $this->assertCount(1, glob(storage_path('backups/nokoplay-*.sqlite')));
    }

    public function test_backup_is_scheduled_once_a_day_in_utc(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'nokoplay:backup'));

        $this->assertSame('40 1 * * *', $event->expression);
        $this->assertSame('UTC', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }
}
