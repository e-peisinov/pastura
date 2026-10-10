<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('potreros', function (Blueprint $table) {
            $table->id();
            // Un potrero con recorridas no se borra: el campo tampoco (D20).
            $table->foreignId('campo_id')->constrained()->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('tipo_pastura', 100)->nullable();
            // Geometría GeoJSON (Polygon o MultiPolygon, lon/lat WGS84). La
            // superficie se calcula de acá, no se guarda (regla 8, P5).
            $table->json('contorno')->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['campo_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('potreros');
    }
};
