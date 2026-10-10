<?php

namespace Tests\Feature\Tablero;

use App\Enums\MotivoRevision;
use App\Models\Configuracion;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Support\Tablero\FilaRevision;
use App\Support\Tablero\RevisionRecorridas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Recorridas para revisar (P18): activas, de los últimos 30 días, con menos
 * puntos activos que el mínimo de Configuración, con algún punto activo sin
 * GPS o con algún punto activo fuera del contorno de su potrero.
 */
class RevisionRecorridasTest extends TestCase
{
    use RefreshDatabase;

    private Potrero $conContorno;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 15:00:00');
        Configuracion::factory()->create(['puntos_minimos' => 3]);
        // Cuadrado de lon −64,35 a −64,34 y lat −33,12 a −33,13; los puntos del factory caen adentro.
        $this->conContorno = Potrero::factory()->conContorno()->create(['nombre' => 'Lote 4']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  list<array<string, mixed>>  $puntos */
    private function recorrida(string $observaciones, array $puntos, array $mas = []): Recorrida
    {
        $recorrida = Recorrida::factory()->for($mas['potrero'] ?? $this->conContorno)->create([
            'observaciones' => $observaciones,
            'iniciada_en' => $mas['fecha'] ?? '2026-10-09 09:00',
            'activo' => $mas['activo'] ?? true,
        ]);

        foreach ($puntos as $punto) {
            Medicion::factory()->for($recorrida)->create($punto);
        }

        return $recorrida;
    }

    /** @return array<string, list<MotivoRevision>> */
    private function motivos(): array
    {
        return RevisionRecorridas::filas()
            ->mapWithKeys(fn (FilaRevision $f) => [$f->recorrida->observaciones => $f->motivos])
            ->all();
    }

    public function test_cada_motivo_y_la_que_esta_bien_no_aparece(): void
    {
        $this->recorrida('Bien', [[], [], []]);
        $this->recorrida('Pocos', [[], [], ['activo' => false]]);
        $this->recorrida('Sin GPS', [[], [], ['latitud' => null, 'longitud' => null]]);
        $this->recorrida('Afuera', [[], [], ['latitud' => -33.10, 'longitud' => -64.345]]);

        // Misma fecha: la cargada última primero.
        $this->assertSame([
            'Afuera' => [MotivoRevision::FueraDelContorno],
            'Sin GPS' => [MotivoRevision::SinGps],
            'Pocos' => [MotivoRevision::PocosPuntos],
        ], $this->motivos());
    }

    public function test_los_puntos_descartados_no_cuentan_para_ningun_motivo(): void
    {
        $this->recorrida('Descartados malos', [[], [], [],
            ['activo' => false, 'latitud' => null, 'longitud' => null],
            ['activo' => false, 'latitud' => -33.10, 'longitud' => -64.345],
        ]);

        $this->assertSame([], $this->motivos());
    }

    public function test_varios_motivos_y_sin_contorno_no_hay_fuera(): void
    {
        $this->recorrida('Todo mal', [['latitud' => null, 'longitud' => null], ['latitud' => -33.10, 'longitud' => -64.345]]);
        $this->recorrida('Sin contorno', [[], [], ['latitud' => -33.10, 'longitud' => -64.345]], ['potrero' => Potrero::factory()->create()]);

        $this->assertSame([
            'Todo mal' => [MotivoRevision::PocosPuntos, MotivoRevision::SinGps, MotivoRevision::FueraDelContorno],
        ], $this->motivos());
    }

    public function test_solo_activas_de_los_ultimos_30_dias_la_mas_nueva_primero(): void
    {
        $this->recorrida('Anulada', [[]], ['activo' => false]);
        $this->recorrida('Vieja', [[]], ['fecha' => '2026-09-10 23:59']);
        $this->recorrida('Día 30', [[]], ['fecha' => '2026-09-11 00:00']);
        $this->recorrida('Hoy', [[]], ['fecha' => '2026-10-10 08:00']);

        $this->assertSame(['Hoy', 'Día 30'], array_keys($this->motivos()));
    }
}
