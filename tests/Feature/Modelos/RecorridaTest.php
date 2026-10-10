<?php

namespace Tests\Feature\Modelos;

use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\TipoPastura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que se muestra de una recorrida se calcula de sus mediciones activas
 * (dominio.md, regla 1; regla dura 8).
 */
class RecorridaTest extends TestCase
{
    use RefreshDatabase;

    /** Los kg que guardó el celular (`$kgs`) no cuentan: el panel los calcula con la fórmula del tipo (P16). */
    private function recorridaCon(array $alturas, ?TipoPastura $tipo = null, array $kgs = []): Recorrida
    {
        $recorrida = Recorrida::factory()
            ->for(Potrero::factory()->create(['tipo_pastura_id' => $tipo?->id]))
            ->create();

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
        $tipo = TipoPastura::factory()->create(['biomasa_a' => 100, 'biomasa_b' => 900]);
        $recorrida = $this->recorridaCon([10, 12.5, 14], $tipo, [1, 2, 3]);

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
        $tipo = TipoPastura::factory()->create(['biomasa_a' => 25, 'biomasa_b' => -100]);
        $recorrida = $this->recorridaCon([12, 20, 2], $tipo, [9999, 9999, 9999]);

        $this->assertEqualsWithDelta(200.0, Recorrida::query()->conResumen()->findOrFail($recorrida->id)->kg_ms_ha_medio, 0.005);
        $this->assertEqualsWithDelta(200.0, $recorrida->fresh()->kg_ms_ha_medio, 0.005);
    }

    /** P16: la fórmula vigente recalcula también las recorridas anteriores. */
    public function test_cambiar_la_formula_recalcula_la_historia(): void
    {
        $tipo = TipoPastura::factory()->create(['biomasa_a' => 100, 'biomasa_b' => 0]);
        $recorrida = $this->recorridaCon([10, 20], $tipo);
        $this->assertEqualsWithDelta(1500.0, $recorrida->fresh()->kg_ms_ha_medio, 0.005);

        // 80 × 10 + 500 = 1300; 80 × 20 + 500 = 2100 → (1300 + 2100) / 2 = 1700.
        $tipo->update(['biomasa_a' => 80, 'biomasa_b' => 500]);
        $this->assertEqualsWithDelta(1700.0, Recorrida::query()->conResumen()->findOrFail($recorrida->id)->kg_ms_ha_medio, 0.005);
        $this->assertEqualsWithDelta(1700.0, $recorrida->fresh()->kg_ms_ha_medio, 0.005);
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

    /** Sin tipo de pastura o sin fórmula el promedio de biomasa queda vacío, no en cero, aunque el celular haya mandado kg. */
    public function test_sin_formula_el_promedio_es_nulo(): void
    {
        $sinTipo = $this->recorridaCon([10, 12], null, [1500, 1600]);
        $sinFormula = $this->recorridaCon([10, 12], TipoPastura::factory()->sinFormula()->create(), [1500, 1600]);

        foreach ([$sinTipo, $sinFormula] as $recorrida) {
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
