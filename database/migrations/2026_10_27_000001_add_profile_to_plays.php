<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perfil infantil que estaba viendo (límite diario por niño y pantalla «Actividad»).
        // Sin clave foránea: en SQLite añadirla reconstruye la tabla. Al borrar un perfil,
        // el modelo deja sus reproducciones sin perfil.
        Schema::table('plays', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->index(['profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('plays', function (Blueprint $table) {
            $table->dropIndex(['profile_id', 'created_at']);
            $table->dropColumn('profile_id');
        });
    }
};
