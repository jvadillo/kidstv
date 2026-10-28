<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Límite diario: sin límite, tiempo compartido entre todos los niños (families.daily_limit_minutes)
        // o un límite para cada niño (profiles.daily_limit_minutes).
        Schema::table('families', function (Blueprint $table) {
            $table->string('daily_limit_mode', 12)->default('off');
        });
        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('daily_limit_minutes')->default(60);
        });

        // Hasta ahora cada niño tenía los mismos minutos por separado: se conserva como límite por niño.
        DB::table('profiles')->update([
            'daily_limit_minutes' => DB::raw('(SELECT daily_limit_minutes FROM families WHERE families.id = profiles.family_id)'),
        ]);
        DB::table('families')->where('daily_limit_enabled', true)->update(['daily_limit_mode' => 'per_profile']);

        Schema::table('families', function (Blueprint $table) {
            $table->dropColumn('daily_limit_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->boolean('daily_limit_enabled')->default(false);
        });
        DB::table('families')->where('daily_limit_mode', '!=', 'off')->update(['daily_limit_enabled' => true]);

        Schema::table('families', function (Blueprint $table) {
            $table->dropColumn('daily_limit_mode');
        });
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('daily_limit_minutes');
        });
    }
};
