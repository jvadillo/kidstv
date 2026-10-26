<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('avatar', 20);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10);
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('status', 15);
            $table->string('status_message')->nullable();
            $table->string('file_path')->nullable();
            $table->string('mime', 60)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('duration')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('youtube_id', 20)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['family_id', 'status']);
        });

        Schema::create('content_profile', function (Blueprint $table) {
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->primary(['content_id', 'profile_id']);
        });

        // Subidas por fragmentos en curso; el fichero final se convierte en un contenido.
        Schema::create('uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('received')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
        Schema::dropIfExists('content_profile');
        Schema::dropIfExists('contents');
        Schema::dropIfExists('lists');
        Schema::dropIfExists('profiles');
    }
};
