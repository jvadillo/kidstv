<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('platform', 20)->default('web');
            $table->string('token_hash', 64)->unique();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedTinyInteger('pin_failures')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_pairings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 8)->index();
            $table->string('secret_hash', 64);
            $table->string('platform', 20)->default('web');
            $table->string('user_agent', 500)->nullable();
            $table->foreignId('device_id')->nullable()->constrained()->cascadeOnDelete();
            // Token del televisor cifrado hasta que el propio televisor lo recoja (una sola vez).
            $table->text('device_token')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_pairings');
        Schema::dropIfExists('devices');
    }
};
