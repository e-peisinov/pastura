<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipos de pastura con sus alturas de referencia y su fórmula de kg MS/ha (P16).
        Schema::create('tipos_pastura', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            // Las dos alturas van juntas o ninguna; ingreso > salida.
            $table->decimal('altura_ingreso_cm', 5, 1)->nullable();
            $table->decimal('altura_salida_cm', 5, 1)->nullable();
            // kg MS/ha = max(0, A × altura + B). A y B juntos o ninguno.
            $table->decimal('biomasa_a', 8, 3)->nullable();
            $table->decimal('biomasa_b', 8, 1)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_pastura');
    }
};
