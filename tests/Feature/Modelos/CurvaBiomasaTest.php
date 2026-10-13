<?php

namespace Tests\Feature\Modelos;

use App\Models\Configuracion;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\Potrero;
use App\Models\Recorrida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Curva de biomasa (P23): las muestras de un potrero en un estado
 * fenológico, su recta y cuál es la vigente.
 */
class CurvaBiomasaTest extends TestCase
{
    use RefreshDatabase;

    /** (5, 8 g), (10, 14 g), (15, 17 g), (20, 25 g) con 0,10 m² → A = 108, B = 250, R² = 0,972. */
    private function curvaDeCuatroMuestras(array $atributos = []): CurvaBiomasa
    {
        $curva = CurvaBiomasa::factory()->create($atributos);
        foreach ([[5, 8], [10, 14], [15, 17], [20, 25]] as [$altura, $peso]) {
            $curva->muestras()->create(['altura_cm' => $altura, 'peso_seco_g' => $peso]);
        }

        return $curva->fresh();
    }

    public function test_la_recta_sale_de_las_muestras_con_el_area_del_disco(): void
    {
        $recta = $this->curvaDeCuatroMuestras()->recta();

        $this->assertEqualsWithDelta(108.0, $recta->a, 0.0001);
        $this->assertEqualsWithDelta(250.0, $recta->b, 0.0001);
        $this->assertEqualsWithDelta(0.972, $recta->r2, 0.0001);
        $this->assertSame(4, $recta->muestras);
    }

    /** Con 0,20 m² cada muestra vale la mitad de kilos: A = 54, B = 125. */
    public function test_el_area_del_disco_sale_de_configuracion(): void
    {
        $curva = $this->curvaDeCuatroMuestras();
        Configuracion::factory()->create(['area_disco_m2' => 0.20]);

        $recta = $curva->recta();

        $this->assertEqualsWithDelta(54.0, $recta->a, 0.0001);
        $this->assertEqualsWithDelta(125.0, $recta->b, 0.0001);
    }

    public function test_el_area_del_disco_arranca_en_un_decimo_de_metro(): void
    {
        $this->assertSame(0.1, Configuracion::instancia()->area_disco_m2);
        $this->assertSame(0.1, Configuracion::factory()->create()->fresh()->area_disco_m2);
    }

    public function test_los_kg_de_cada_muestra(): void
    {
        $curva = $this->curvaDeCuatroMuestras();

        $this->assertSame([800.0, 1400.0, 1700.0, 2500.0], $curva->muestras->map(fn ($m) => round($m->kgMsHa(0.10), 4))->all());
    }

    /** Guardar otra para el mismo potrero y estado deja reemplazada a la anterior; las de otro estado u otro potrero, no. */
    public function test_hay_una_sola_vigente_por_potrero_y_estado(): void
    {
        $potrero = Potrero::factory()->create();
        $rebrote = EstadoFenologico::factory()->create();
        $primavera = EstadoFenologico::factory()->create();

        $vieja = CurvaBiomasa::factory()->recta(100, 0)->create(['potrero_id' => $potrero->id, 'estado_fenologico_id' => $rebrote->id]);
        $otroEstado = CurvaBiomasa::factory()->recta(100, 0)->create(['potrero_id' => $potrero->id, 'estado_fenologico_id' => $primavera->id]);
        $otroPotrero = CurvaBiomasa::factory()->recta(100, 0)->create(['estado_fenologico_id' => $rebrote->id]);

        $nueva = CurvaBiomasa::factory()->recta(80, 500)->create(['potrero_id' => $potrero->id, 'estado_fenologico_id' => $rebrote->id]);

        $this->assertFalse($vieja->fresh()->activo);
        $this->assertTrue($nueva->fresh()->activo);
        $this->assertTrue($otroEstado->fresh()->activo);
        $this->assertTrue($otroPotrero->fresh()->activo);
        $this->assertTrue(CurvaBiomasa::vigentePara($potrero->id, $rebrote->id)->is($nueva));
        $this->assertNull(CurvaBiomasa::vigentePara($potrero->id, null));
    }

    /** Volver a hacer vigente la reemplazada deja reemplazada a la que estaba. */
    public function test_reactivar_una_reemplazada_reemplaza_a_la_vigente(): void
    {
        $vieja = CurvaBiomasa::factory()->recta(100, 0)->create();
        $nueva = CurvaBiomasa::factory()->recta(80, 500)->create([
            'potrero_id' => $vieja->potrero_id,
            'estado_fenologico_id' => $vieja->estado_fenologico_id,
        ]);

        $vieja->fresh()->update(['activo' => true]);

        $this->assertTrue($vieja->fresh()->activo);
        $this->assertFalse($nueva->fresh()->activo);
    }

    /** Pasar una curva a otro estado la hace vigente ahí y reemplaza a la que había. */
    public function test_cambiarle_el_estado_la_hace_vigente_en_el_nuevo(): void
    {
        $curva = CurvaBiomasa::factory()->recta(100, 0)->create();
        $deAlla = CurvaBiomasa::factory()->recta(80, 500)->create(['potrero_id' => $curva->potrero_id]);

        $curva->update(['estado_fenologico_id' => $deAlla->estado_fenologico_id]);

        $this->assertTrue($curva->fresh()->activo);
        $this->assertFalse($deAlla->fresh()->activo);
    }

    public function test_la_recorrida_encuentra_su_curva_vigente(): void
    {
        $curva = CurvaBiomasa::factory()->recta(100, 0)->create();
        $recorrida = Recorrida::factory()->create(['potrero_id' => $curva->potrero_id, 'estado_fenologico_id' => $curva->estado_fenologico_id]);
        $sinEstado = Recorrida::factory()->create(['potrero_id' => $curva->potrero_id, 'estado_fenologico_id' => null]);
        // Por omisión, la factory le pone el estado de la curva vigente de su potrero.
        $this->assertSame($curva->estado_fenologico_id, Recorrida::factory()->create(['potrero_id' => $curva->potrero_id])->estado_fenologico_id);

        $this->assertTrue($recorrida->curvaBiomasa()->is($curva));
        $this->assertEqualsWithDelta(1500.0, $recorrida->recta()->kgMsHa(15.0), 0.0001);
        $this->assertNull($sinEstado->curvaBiomasa());
        $this->assertNull($sinEstado->recta());
    }

    /** Un potrero (y su campo) con curvas tiene historia aunque nunca se haya medido: se desactiva, no se borra. */
    public function test_las_curvas_dan_historia_al_potrero_y_al_campo(): void
    {
        $curva = CurvaBiomasa::factory()->recta(100, 0)->create();
        $libre = Potrero::factory()->create();

        $this->assertTrue($curva->potrero->tieneHistoria());
        $this->assertTrue($curva->potrero->campo->tieneHistoria());
        $this->assertFalse($libre->tieneHistoria());
        $this->assertFalse($libre->campo->tieneHistoria());
    }

    /** Un estado tiene historia si lo usan curvas o recorridas: entonces se desactiva, no se borra. */
    public function test_historia_del_estado_fenologico(): void
    {
        $conCurva = EstadoFenologico::factory()->create();
        $conRecorrida = EstadoFenologico::factory()->create();
        $libre = EstadoFenologico::factory()->create();
        CurvaBiomasa::factory()->recta(100, 0)->create(['estado_fenologico_id' => $conCurva->id]);
        Recorrida::factory()->create(['estado_fenologico_id' => $conRecorrida->id]);

        $this->assertTrue($conCurva->tieneHistoria());
        $this->assertTrue($conRecorrida->tieneHistoria());
        $this->assertFalse($libre->tieneHistoria());
    }
}
