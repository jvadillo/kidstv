<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Límite diario de tele: se guardan los minutos aunque esté desactivado, para recuperarlos al reactivarlo.
        Schema::table('families', function (Blueprint $table) {
            $table->boolean('daily_limit_enabled')->default(false);
            $table->unsignedSmallInteger('daily_limit_minutes')->default(60);
        });

        // Lo que se ve desde la Zona de adultos no cuenta para el límite diario.
        Schema::table('plays', function (Blueprint $table) {
            $table->boolean('adult')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('plays', function (Blueprint $table) {
            $table->dropColumn('adult');
        });
        Schema::table('families', function (Blueprint $table) {
            $table->dropColumn(['daily_limit_enabled', 'daily_limit_minutes']);
        });
    }
};
