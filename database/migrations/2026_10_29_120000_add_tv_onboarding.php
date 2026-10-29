<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->timestamp('provisional_until')->nullable()->index();
        });
        Schema::table('device_pairings', function (Blueprint $table) {
            $table->json('draft')->nullable();
            $table->foreignId('setup_device_id')->nullable()->constrained('devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('device_pairings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('setup_device_id');
            $table->dropColumn('draft');
        });
        Schema::table('families', fn (Blueprint $table) => $table->dropColumn('provisional_until'));
    }
};
