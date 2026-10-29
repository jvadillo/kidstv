<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Etapa de edad del niño (un tramo de VideoList::AGE_RANGES, nunca la fecha de nacimiento):
        // sirve para proponerle listas públicas de su edad.
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('age_range', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('age_range');
        });
    }
};
