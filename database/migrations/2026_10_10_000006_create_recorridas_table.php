<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una salida de un recorredor a un potrero (glosario). La sube la app
        // de campo; el uuid lo genera el celular y hace idempotente la subida (P4).
        Schema::create('recorridas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('potrero_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('iniciada_en');
            $table->string('dispositivo', 150)->nullable();
            $table->text('observaciones')->nullable();
            // Anulada = false: no cuenta en ningún promedio.
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['potrero_id', 'iniciada_en']);
        });

        Schema::create('mediciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorrida_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->dateTime('tomada_en');
            $table->decimal('altura_cm', 5, 1);
            $table->decimal('distancia_cm', 5, 1)->nullable();
            $table->unsignedInteger('kg_ms_ha')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('precision_m', 6, 1)->nullable();
            $table->decimal('inclinacion_grados', 4, 1)->nullable();
            $table->unsignedTinyInteger('imagenes')->default(0);
            $table->decimal('dispersion_cm', 5, 2)->nullable();
            // Copia de la calibración con la que midió la app (a, c, r2, grad).
            $table->json('calibracion')->nullable();
            // Descartada = false: un error de lectura que no entra en el promedio.
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['recorrida_id', 'tomada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediciones');
        Schema::dropIfExists('recorridas');
    }
};
