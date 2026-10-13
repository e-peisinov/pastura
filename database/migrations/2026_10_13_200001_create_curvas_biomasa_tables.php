<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El estado de la pastura cuando se mide (P23): recién pastoreada,
        // rebrote... Cada uno tiene su curva en cada potrero. Se administra
        // en Configuración; con curvas o recorridas se desactiva.
        Schema::create('estados_fenologicos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Curva de biomasa (P23): las muestras cortadas en un potrero en un
        // estado. A, B y R² se calculan de las muestras (regla 8). Una sola
        // vigente (activo) por potrero y estado; las otras quedan reemplazadas.
        Schema::create('curvas_biomasa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('potrero_id')->constrained()->restrictOnDelete();
            $table->foreignId('estado_fenologico_id')->constrained('estados_fenologicos')->restrictOnDelete();
            // El día de los cortes.
            $table->date('fecha');
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['potrero_id', 'estado_fenologico_id', 'activo']);
        });

        // Una muestra: la altura del disco y el peso seco (estufa) del pasto
        // cortado justo debajo. Los kg MS/ha salen del área del disco.
        Schema::create('muestras_curva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curva_biomasa_id')->constrained('curvas_biomasa')->cascadeOnDelete();
            $table->decimal('altura_cm', 5, 1);
            $table->decimal('peso_seco_g', 7, 2);
            $table->timestamps();
        });

        // El estado que eligió el recorredor al empezar (P23). Null en las
        // de antes y en las de una app vieja: sin estado no hay kilos.
        Schema::table('recorridas', function (Blueprint $table) {
            $table->foreignId('estado_fenologico_id')->nullable()->after('potrero_id')
                ->constrained('estados_fenologicos')->restrictOnDelete();
        });

        // Lo que corta una muestra: el área del disco (0,10 m², Mateo).
        Schema::table('configuracion', function (Blueprint $table) {
            $table->decimal('area_disco_m2', 6, 4)->default(0.1)->after('puntos_minimos');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion', function (Blueprint $table) {
            $table->dropColumn('area_disco_m2');
        });

        Schema::table('recorridas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estado_fenologico_id');
        });

        Schema::dropIfExists('muestras_curva');
        Schema::dropIfExists('curvas_biomasa');
        Schema::dropIfExists('estados_fenologicos');
    }
};
