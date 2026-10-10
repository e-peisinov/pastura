<?php

namespace Tests\Feature\Modelos;

use App\Models\Potrero;
use App\Models\TipoPastura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tipo de pastura (P16): alturas de referencia y fórmula de kg MS/ha
 * `max(0, A × altura + B)`.
 */
class TipoPasturaTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_kg_ms_ha_salen_de_la_formula_y_nunca_son_negativos(): void
    {
        $alfalfa = TipoPastura::factory()->make(['biomasa_a' => 25.5, 'biomasa_b' => -100]);

        // 25,5 × 12 − 100 = 206
        $this->assertEqualsWithDelta(206.0, $alfalfa->kgMsHa(12.0), 0.001);
        // 25,5 × 20,4 − 100 = 420,2
        $this->assertEqualsWithDelta(420.2, $alfalfa->kgMsHa(20.4), 0.001);
        // 25,5 × 3 − 100 = −23,5 → 0
        $this->assertSame(0.0, $alfalfa->kgMsHa(3.0));
    }

    public function test_sin_formula_no_hay_kg_ms_ha(): void
    {
        $sinFormula = TipoPastura::factory()->sinFormula()->make();

        $this->assertFalse($sinFormula->tieneFormula());
        $this->assertNull($sinFormula->kgMsHa(15.0));
        $this->assertTrue(TipoPastura::factory()->make(['biomasa_a' => 30, 'biomasa_b' => 0])->tieneFormula());
    }

    public function test_tiene_alturas_solo_con_las_dos(): void
    {
        $this->assertTrue(TipoPastura::factory()->make(['altura_ingreso_cm' => 20, 'altura_salida_cm' => 7])->tieneAlturas());
        $this->assertFalse(TipoPastura::factory()->sinAlturas()->make()->tieneAlturas());
    }

    public function test_tiene_historia_si_algun_potrero_lo_usa(): void
    {
        $usado = TipoPastura::factory()->create();
        $libre = TipoPastura::factory()->create();
        Potrero::factory()->create(['tipo_pastura_id' => $usado->id]);

        $this->assertTrue($usado->tieneHistoria());
        $this->assertFalse($libre->tieneHistoria());
        $this->assertSame($usado->id, Potrero::query()->sole()->tipoPastura->id);
    }
}
