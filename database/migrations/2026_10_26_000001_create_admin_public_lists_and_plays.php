<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Administradores de la plataforma y cuentas bloqueadas.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->timestamp('blocked_at')->nullable();
        });

        // Listas públicas: las crea un administrador y otras familias las añaden a su
        // videoteca como una lista enlazada (source_id) que muestra los vídeos del original.
        // Sin clave foránea a propósito: en SQLite añadirla obliga a reconstruir la tabla,
        // y eso vaciaría la lista de los contenidos. El borrado en cascada lo hace el modelo.
        Schema::table('lists', function (Blueprint $table) {
            $table->string('description', 200)->nullable();
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->unique(['family_id', 'source_id']);
        });

        // Perfiles que ven cada lista pública enlazada.
        Schema::create('list_profile', function (Blueprint $table) {
            $table->foreignId('list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->primary(['list_id', 'profile_id']);
        });

        // Tiempo de visionado que informan los televisores (estadísticas de uso). A propósito,
        // sin perfil: no se registra qué ve cada niño, solo cuánto se usa cada tele y contenido.
        Schema::create('plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_id')->nullable()->constrained()->nullOnDelete();
            $table->string('content_type', 10);
            $table->string('client_key', 40);
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamps();

            $table->unique(['device_id', 'client_key']);
            $table->index(['family_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plays');
        Schema::dropIfExists('list_profile');
        Schema::table('lists', function (Blueprint $table) {
            $table->dropUnique(['family_id', 'source_id']);
            $table->dropIndex(['source_id']);
            $table->dropColumn(['description', 'is_public', 'source_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'blocked_at']);
        });
    }
};
