<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P23: los kg MS/ha salen de las curvas de biomasa de cada potrero. La
     * fórmula por tipo de pastura (P16) no se usa más: sin curva no hay kilos.
     */
    public function up(): void
    {
        Schema::table('tipos_pastura', function (Blueprint $table) {
            $table->dropColumn(['biomasa_a', 'biomasa_b']);
        });
    }

    public function down(): void
    {
        Schema::table('tipos_pastura', function (Blueprint $table) {
            $table->decimal('biomasa_a', 8, 3)->nullable()->after('altura_salida_cm');
            $table->decimal('biomasa_b', 8, 1)->nullable()->after('biomasa_a');
        });
    }
};
