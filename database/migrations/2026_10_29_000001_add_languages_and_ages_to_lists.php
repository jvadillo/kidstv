<?php

use App\Services\YouTubeSearch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idiomas de cada lista (uno o varios, códigos como los del buscador de YouTube) y,
        // en las públicas, la edad recomendada. Las listas enlazadas usan los de su original.
        Schema::table('lists', function (Blueprint $table) {
            $table->json('languages')->nullable();
            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
        });

        // Las listas que ya existían pasan a estar en el idioma por defecto de la plataforma.
        DB::table('lists')->whereNull('source_id')->update(['languages' => json_encode([YouTubeSearch::defaultLanguage()])]);
    }

    public function down(): void
    {
        Schema::table('lists', function (Blueprint $table) {
            $table->dropColumn(['languages', 'age_min', 'age_max']);
        });
    }
};
