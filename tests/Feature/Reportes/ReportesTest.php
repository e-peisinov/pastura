<?php

namespace Tests\Feature\Reportes;

use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\TipoPastura;
use App\Models\User;
use App\Support\Reportes\Reportes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Los números de Reportes (P18, P21) para un período, con cuentas a mano.
 * Solo cuentan las recorridas activas con algún punto activo (regla 1).
 *
 * Septiembre (01 al 30) contra el período anterior del mismo largo (02 al
 * 31 de agosto). Alfalfa: kg MS/ha = 100 × altura.
 */
class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private User $juan;

    private User $ana;

    private Campo $esperanza;

    private Campo $sanJose;

    private Potrero $lote4;

    private Potrero $bajo;

    private Potrero $norte;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 15:00:00');
        $this->juan = User::factory()->create(['name' => 'Juan Pérez']);
        $this->ana = User::factory()->create(['name' => 'Ana Gómez']);

        $alfalfa = TipoPastura::factory()->create(['nombre' => 'Alfalfa', 'biomasa_a' => 100, 'biomasa_b' => 0]);
        $sinFormula = TipoPastura::factory()->sinFormula()->create(['nombre' => 'Sorgo']);

        $this->esperanza = Campo::factory()->create(['nombre' => 'La Esperanza']);
        $this->sanJose = Campo::factory()->create(['nombre' => 'San José']);
        // ≈ 103,79 ha cada uno.
        $this->lote4 = Potrero::factory()->conContorno()->for($this->esperanza)->create(['nombre' => 'Lote 4', 'tipo_pastura_id' => $alfalfa->id]);
        $this->bajo = Potrero::factory()->for($this->esperanza)->create(['nombre' => 'El Bajo', 'tipo_pastura_id' => $alfalfa->id]);
        Potrero::factory()->for($this->esperanza)->inactivo()->create(['nombre' => 'Viejo']);
        $this->norte = Potrero::factory()->conContorno(-64.30)->for($this->sanJose)->create(['nombre' => 'Norte', 'tipo_pastura_id' => $sinFormula->id]);

        $this->recorrida($this->lote4, $this->juan, '2026-09-05 08:00', [10, 12]);   // 11 cm
        $this->recorrida($this->lote4, $this->juan, '2026-09-15 08:00', [16, 18]);   // 17 cm
        $this->recorrida($this->lote4, $this->juan, '2026-09-20 08:00', [30], activa: false);
        $this->recorrida($this->lote4, $this->ana, '2026-09-25 08:00', [20]);        // 20 cm
        $this->recorrida($this->bajo, $this->juan, '2026-09-10 08:00', [8]);
        $this->recorrida($this->norte, $this->ana, '2026-09-12 08:00', [25]);
        $this->recorrida($this->norte, $this->ana, '2026-09-13 08:00', [40], puntosActivos: false);
        $this->recorrida($this->lote4, $this->juan, '2026-08-20 08:00', [9]);        // período anterior
        $this->recorrida($this->bajo, $this->juan, '2026-10-02 08:00', [12]);        // después
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  list<float>  $alturas */
    private function recorrida(Potrero $potrero, User $quien, string $fecha, array $alturas, bool $activa = true, bool $puntosActivos = true): void
    {
        $recorrida = Recorrida::factory()->for($potrero)->for($quien)->create(['iniciada_en' => $fecha, 'activo' => $activa]);

        foreach ($alturas as $altura) {
            Medicion::factory()->for($recorrida)->create(['altura_cm' => $altura, 'tomada_en' => $fecha, 'activo' => $puntosActivos]);
        }
    }

    private function septiembre(): Reportes
    {
        return new Reportes('2026-09-01', '2026-09-30');
    }

    public function test_resumen_y_comparacion_con_el_periodo_anterior(): void
    {
        $ha = $this->lote4->superficieHa();
        $reportes = $this->septiembre();

        $this->assertSame(['2026-08-02', '2026-08-31'], [$reportes->anterior()->desde, $reportes->anterior()->hasta]);

        $resumen = $reportes->resumen();
        $this->assertSame(5, $resumen['recorridas']);
        $this->assertSame(7, $resumen['puntos']);
        $this->assertSame(3, $resumen['potreros']);
        // Lote 4 y Norte; El Bajo no tiene contorno.
        $this->assertEqualsWithDelta($ha + $this->norte->superficieHa(), $resumen['hectareas'], 0.01);

        $comparacion = $reportes->comparacion();
        $this->assertSame(1, $comparacion['recorridas']['anterior']);
        // (5 − 1) / 1 = 400 %.
        $this->assertSame(400.0, $comparacion['recorridas']['variacion']);
        $this->assertEqualsWithDelta($ha, $comparacion['hectareas']['anterior'], 0.01);
    }

    public function test_sin_nada_antes_la_variacion_es_nula(): void
    {
        $comparacion = (new Reportes('2026-08-01', '2026-08-10'))->comparacion();

        $this->assertSame(0, $comparacion['recorridas']['actual']);
        $this->assertNull($comparacion['recorridas']['variacion']);
    }

    /** 11 → 17 cm en 10 días = 0,6 cm/día y 60 kg MS/ha/día; 17 → 20 en 10 días = 0,3 y 30. */
    public function test_evolucion_de_un_potrero_con_su_tasa_de_crecimiento(): void
    {
        $evolucion = $this->septiembre()->evolucion($this->lote4->id);

        $this->assertSame(['05/09', '15/09', '25/09'], array_column($evolucion, 'etiqueta'));
        $this->assertEqualsWithDelta([11, 17, 20], array_column($evolucion, 'altura'), 0.001);
        $this->assertEqualsWithDelta([1100, 1700, 2000], array_column($evolucion, 'kg'), 0.001);
        $this->assertNull($evolucion[0]['tasa_cm']);
        $this->assertEqualsWithDelta(0.6, $evolucion[1]['tasa_cm'], 0.0001);
        $this->assertEqualsWithDelta(60, $evolucion[1]['tasa_kg'], 0.0001);
        $this->assertEqualsWithDelta(0.3, $evolucion[2]['tasa_cm'], 0.0001);
    }

    public function test_potreros_de_un_campo_por_su_ultima_altura(): void
    {
        $potreros = $this->septiembre()->potrerosDelCampo($this->esperanza->id);

        $this->assertSame(['Lote 4', 'El Bajo'], array_column($potreros['filas'], 'nombre'));
        $this->assertEqualsWithDelta([20, 8], array_column($potreros['filas'], 'altura'), 0.001);
        $this->assertSame('2026-09-25', $potreros['filas'][0]['fecha']);
        $this->assertSame(0, $potreros['sin_datos']);
    }

    /** La Esperanza: solo Lote 4 tiene superficie y fórmula: 2000 kg MS/ha × 103,79 ha. San José no tiene fórmula. */
    public function test_disponibilidad_forrajera_por_campo(): void
    {
        $disponibilidad = $this->septiembre()->disponibilidad();

        $this->assertSame(['La Esperanza'], array_column($disponibilidad, 'nombre'));
        $this->assertEqualsWithDelta(2000 * $this->lote4->superficieHa(), $disponibilidad[0]['kg'], 1);
        $this->assertSame(1, $disponibilidad[0]['con_dato']);
        $this->assertSame(2, $disponibilidad[0]['potreros']);
    }

    public function test_actividad_por_recorredor(): void
    {
        $this->assertSame([
            ['nombre' => 'Juan Pérez', 'recorridas' => 3, 'puntos' => 5],
            ['nombre' => 'Ana Gómez', 'recorridas' => 2, 'puntos' => 2],
        ], $this->septiembre()->porRecorredor());
    }

    public function test_el_periodo_y_la_eleccion_viajan_en_la_url(): void
    {
        $reportes = Reportes::desdeParametros(['desde' => '2026-09-01', 'hasta' => 'cualquiera', 'campo' => (string) $this->esperanza->id, 'potrero' => 'x']);

        $this->assertSame('2026-09-01', $reportes->desde);
        $this->assertSame('2026-10-10', $reportes->hasta);
        $this->assertSame($this->esperanza->id, $reportes->campoId);
        $this->assertNull($reportes->potreroId);

        // Sin fechas, los últimos tres meses desde un día 1.
        $porOmision = Reportes::desdeParametros([]);
        $this->assertSame(['2026-08-01', '2026-10-10'], [$porOmision->desde, $porOmision->hasta]);
    }
}
