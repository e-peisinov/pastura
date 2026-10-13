<?php

namespace Tests\Feature\Modelos;

use App\Models\Campo;
use App\Models\Configuracion;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que se muestra de una recorrida se calcula de sus mediciones activas
 * (dominio.md, regla 1; regla dura 8).
 */
class RecorridaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Los kg que guardó el celular (`$kgs`) no cuentan: el panel los calcula
     * con la curva vigente del potrero para el estado de la recorrida (P23).
     * `$recta` = [A, B] arma esa curva con muestras justo sobre la recta.
     */
    private function recorridaCon(array $alturas, ?array $recta = null, array $kgs = []): Recorrida
    {
        $potrero = Potrero::factory()->create();
        $estado = EstadoFenologico::factory()->create();

        if ($recta !== null) {
            CurvaBiomasa::factory()->recta(...$recta)->create(['potrero_id' => $potrero->id, 'estado_fenologico_id' => $estado->id]);
        }

        $recorrida = Recorrida::factory()->for($potrero)->create(['estado_fenologico_id' => $estado->id]);

        foreach ($alturas as $i => $altura) {
            Medicion::factory()->for($recorrida)->create([
                'altura_cm' => $altura,
                'kg_ms_ha' => $kgs[$i] ?? null,
                'tomada_en' => now()->subMinutes(10 - $i),
            ]);
        }

        return $recorrida;
    }

    /** 10 + 12,5 + 14 = 36,5 / 3 = 12,17 cm; kg = 100 × h + 900: (1900 + 2150 + 2300) / 3 = 2116,67. */
    public function test_el_resumen_promedia_las_mediciones(): void
    {
        $recorrida = $this->recorridaCon([10, 12.5, 14], [100, 900], [1, 2, 3]);

        $r = Recorrida::query()->conResumen()->findOrFail($recorrida->id);

        $this->assertSame(3, (int) $r->puntos);
        $this->assertEqualsWithDelta(12.17, $r->altura_media, 0.005);
        $this->assertEqualsWithDelta(2116.67, $r->kg_ms_ha_medio, 0.005);
    }

    /**
     * Cada punto se acota en cero antes de promediar: kg = 25 × h − 100 da
     * 200, 400 y 0 (no −50) → 200. Con la altura media (11,33) daría 183,3.
     */
    public function test_los_kg_de_cada_punto_se_acotan_en_cero_antes_de_promediar(): void
    {
        $recorrida = $this->recorridaCon([12, 20, 2], [25, -100], [9999, 9999, 9999]);

        $this->assertEqualsWithDelta(200.0, Recorrida::query()->conResumen()->findOrFail($recorrida->id)->kg_ms_ha_medio, 0.005);
        $this->assertEqualsWithDelta(200.0, $recorrida->fresh()->kg_ms_ha_medio, 0.005);
    }

    /**
     * La recta sale de las muestras, que no caen justo sobre una recta:
     * (5, 8 g), (10, 14 g), (15, 17 g), (20, 25 g) con el disco de 0,10 m²
     * son 800, 1400, 1700 y 2500 kg → A = 108, B = 250 (RectaBiomasaTest).
     * Puntos a 12 y 1 cm: (1546 + 358) / 2 = 952, en SQL y en PHP.
     */
    public function test_la_subconsulta_y_el_accessor_ajustan_la_misma_recta(): void
    {
        $recorrida = $this->recorridaCon([12, 1]);
        $curva = CurvaBiomasa::factory()->create([
            'potrero_id' => $recorrida->potrero_id,
            'estado_fenologico_id' => $recorrida->estado_fenologico_id,
        ]);
        foreach ([[5, 8], [10, 14], [15, 17], [20, 25]] as [$altura, $peso]) {
            $curva->muestras()->create(['altura_cm' => $altura, 'peso_seco_g' => $peso]);
        }

        $this->assertEqualsWithDelta(952.0, Recorrida::query()->conResumen()->findOrFail($recorrida->id)->kg_ms_ha_medio, 0.005);
        $this->assertEqualsWithDelta(952.0, $recorrida->fresh()->kg_ms_ha_medio, 0.005);
    }

    /** P23: la curva vigente recalcula también las recorridas anteriores. */
    public function test_una_curva_nueva_recalcula_la_historia(): void
    {
        $recorrida = $this->recorridaCon([10, 20], [100, 0]);
        $this->assertEqualsWithDelta(1500.0, $recorrida->fresh()->kg_ms_ha_medio, 0.005);

        // La nueva reemplaza a la anterior: 80 × 10 + 500 = 1300; 80 × 20 + 500 = 2100 → 1700.
        CurvaBiomasa::factory()->recta(80, 500)->create([
            'potrero_id' => $recorrida->potrero_id,
            'estado_fenologico_id' => $recorrida->estado_fenologico_id,
        ]);
        $this->assertEqualsWithDelta(1700.0, Recorrida::query()->conResumen()->findOrFail($recorrida->id)->kg_ms_ha_medio, 0.005);
        $this->assertEqualsWithDelta(1700.0, $recorrida->fresh()->kg_ms_ha_medio, 0.005);
    }

    /** El área del disco entra en cada muestra: con 0,20 m² los mismos gramos son la mitad de kilos. */
    public function test_cambiar_el_area_del_disco_recalcula_los_kilos(): void
    {
        $recorrida = $this->recorridaCon([10, 20], [100, 0]);
        Configuracion::factory()->create(['area_disco_m2' => 0.20]);

        $this->assertEqualsWithDelta(750.0, Recorrida::query()->conResumen()->findOrFail($recorrida->id)->kg_ms_ha_medio, 0.005);
        $this->assertEqualsWithDelta(750.0, $recorrida->fresh()->kg_ms_ha_medio, 0.005);
    }

    /** Un punto descartado (40 cm, error de lectura) no entra: (10 + 14) / 2 = 12 cm. */
    public function test_los_puntos_descartados_no_cuentan(): void
    {
        $recorrida = $this->recorridaCon([10, 40, 14]);
        $recorrida->mediciones()->where('altura_cm', 40)->update(['activo' => false]);

        $r = Recorrida::query()->conResumen()->findOrFail($recorrida->id);

        $this->assertSame(2, (int) $r->puntos);
        $this->assertEqualsWithDelta(12.0, $r->altura_media, 0.005);
    }

    /**
     * Sin curva vigente para ese potrero y ese estado el promedio de biomasa
     * queda vacío, no en cero, aunque el celular haya mandado kg (P23: no hay
     * fórmula de respaldo).
     */
    public function test_sin_curva_vigente_el_promedio_es_nulo(): void
    {
        $sinCurva = $this->recorridaCon([10, 12], null, [1500, 1600]);

        $deOtroEstado = $this->recorridaCon([10, 12], null, [1500, 1600]);
        CurvaBiomasa::factory()->recta(100, 0)->create(['potrero_id' => $deOtroEstado->potrero_id]);

        $deOtroPotrero = $this->recorridaCon([10, 12], null, [1500, 1600]);
        CurvaBiomasa::factory()->recta(100, 0)->create(['estado_fenologico_id' => $deOtroPotrero->estado_fenologico_id]);

        $reemplazada = $this->recorridaCon([10, 12], [100, 0], [1500, 1600]);
        CurvaBiomasa::query()->where('potrero_id', $reemplazada->potrero_id)->update(['activo' => false]);

        $sinEstado = $this->recorridaCon([10, 12], [100, 0], [1500, 1600]);
        $sinEstado->update(['estado_fenologico_id' => null]);

        foreach ([$sinCurva, $deOtroEstado, $deOtroPotrero, $reemplazada, $sinEstado] as $recorrida) {
            $this->assertNull(Recorrida::query()->conResumen()->findOrFail($recorrida->id)->kg_ms_ha_medio);
            $this->assertNull($recorrida->fresh()->kg_ms_ha_medio);
        }
    }

    /** El accessor sin conResumen() calcula lo mismo (para la ficha de una sola recorrida). */
    public function test_sin_subconsulta_calcula_igual(): void
    {
        $recorrida = $this->recorridaCon([8, 9, 10]);

        $this->assertEqualsWithDelta(9.0, $recorrida->fresh()->altura_media, 0.005);
        $this->assertSame(3, $recorrida->fresh()->puntos);
    }

    /** La última recorrida de un potrero es la más reciente que no está anulada. */
    public function test_ultima_recorrida_del_potrero_ignora_las_anuladas(): void
    {
        $potrero = Potrero::factory()->create();
        $vieja = Recorrida::factory()->for($potrero)->create(['iniciada_en' => now()->subDays(10)]);
        Recorrida::factory()->for($potrero)->create(['iniciada_en' => now()->subDay(), 'activo' => false]);

        $this->assertTrue($potrero->ultimaRecorrida()->is($vieja));
    }

    /** Un campo tiene historia si alguno de sus potreros tiene recorridas. */
    public function test_historia_de_campo_y_potrero(): void
    {
        $campo = Campo::factory()->create();
        $conRecorrida = Potrero::factory()->for($campo)->create();
        $sinRecorrida = Potrero::factory()->for($campo)->create();
        $this->assertFalse($campo->tieneHistoria());

        Recorrida::factory()->for($conRecorrida)->create();

        $this->assertTrue($campo->fresh()->tieneHistoria());
        $this->assertTrue($conRecorrida->tieneHistoria());
        $this->assertFalse($sinRecorrida->tieneHistoria());
    }
}
