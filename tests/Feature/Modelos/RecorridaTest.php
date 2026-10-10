<?php

namespace Tests\Feature\Modelos;

use App\Models\Campo;
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

    private function recorridaCon(array $alturas, array $kgs = []): Recorrida
    {
        $recorrida = Recorrida::factory()->create();

        foreach ($alturas as $i => $altura) {
            Medicion::factory()->for($recorrida)->create([
                'altura_cm' => $altura,
                'kg_ms_ha' => $kgs[$i] ?? null,
                'tomada_en' => now()->subMinutes(10 - $i),
            ]);
        }

        return $recorrida;
    }

    /** 10 + 12,5 + 14 = 36,5 / 3 = 12,17 cm; (1800 + 2100 + 2400) / 3 = 2100 kg. */
    public function test_el_resumen_promedia_las_mediciones(): void
    {
        $recorrida = $this->recorridaCon([10, 12.5, 14], [1800, 2100, 2400]);

        $r = Recorrida::query()->conResumen()->findOrFail($recorrida->id);

        $this->assertSame(3, (int) $r->puntos);
        $this->assertEqualsWithDelta(12.17, $r->altura_media, 0.005);
        $this->assertEqualsWithDelta(2100, $r->kg_ms_ha_medio, 0.5);
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

    /** Sin kg cargados el promedio de biomasa queda vacío, no en cero. */
    public function test_sin_biomasa_el_promedio_es_nulo(): void
    {
        $recorrida = $this->recorridaCon([10, 12]);

        $r = Recorrida::query()->conResumen()->findOrFail($recorrida->id);

        $this->assertNull($r->kg_ms_ha_medio);
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
