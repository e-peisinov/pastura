<?php

namespace Tests\Feature\Modelos;

use App\Models\Potrero;
use App\Models\TipoPastura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tipo de pastura (P16): alturas de referencia. Los kg MS/ha ya no son del
 * tipo: salen de las curvas de biomasa de cada potrero (P23).
 */
class TipoPasturaTest extends TestCase
{
    use RefreshDatabase;

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
