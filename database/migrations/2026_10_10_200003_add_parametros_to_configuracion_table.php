<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parámetros del seguimiento (P17): cuándo un potrero queda sin medir
        // y cuántos puntos tiene que tener una recorrida para no revisarla.
        Schema::table('configuracion', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_sin_medir')->default(7)->after('color_acento');
            $table->unsignedSmallInteger('puntos_minimos')->default(20)->after('dias_sin_medir');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion', function (Blueprint $table) {
            $table->dropColumn(['dias_sin_medir', 'puntos_minimos']);
        });
    }
};
