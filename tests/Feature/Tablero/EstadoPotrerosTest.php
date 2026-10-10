<?php

namespace Tests\Feature\Tablero;

use App\Enums\EstadoPotrero;
use App\Models\Campo;
use App\Models\Configuracion;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\TipoPastura;
use App\Support\Tablero\EstadoPotreros;
use App\Support\Tablero\FilaPotrero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * El estado de cada potrero activo según su última recorrida activa con
 * puntos (glosario, P18): listo (≥ altura de ingreso), en crecimiento,
 * bajo el remanente (< altura de salida), sin alturas de referencia o nunca
 * medido; y sin medir si la última tiene más días que los de Configuración.
 */
class EstadoPotrerosTest extends TestCase
{
    use RefreshDatabase;

    private Campo $campo;

    private TipoPastura $alfalfa;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 15:00:00');
        Configuracion::factory()->create(['dias_sin_medir' => 7]);
        $this->campo = Campo::factory()->create(['nombre' => 'La Esperanza']);
        $this->alfalfa = TipoPastura::factory()->create(['altura_ingreso_cm' => 20, 'altura_salida_cm' => 7, 'biomasa_a' => 100, 'biomasa_b' => 0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function potrero(string $nombre, ?TipoPastura $tipo = null, array $mas = []): Potrero
    {
        return Potrero::factory()->for($mas['campo'] ?? $this->campo)->create([
            'nombre' => $nombre,
            'tipo_pastura_id' => ($tipo ?? $this->alfalfa)?->id,
            ...array_diff_key($mas, ['campo' => true]),
        ]);
    }

    /** @param  list<float>  $alturas */
    private function recorrida(Potrero $potrero, string $fecha, array $alturas, bool $activa = true, bool $puntosActivos = true): Recorrida
    {
        $recorrida = Recorrida::factory()->for($potrero)->create(['iniciada_en' => $fecha, 'activo' => $activa]);

        foreach ($alturas as $altura) {
            Medicion::factory()->for($recorrida)->create(['altura_cm' => $altura, 'tomada_en' => $fecha, 'activo' => $puntosActivos]);
        }

        return $recorrida;
    }

    /** @return array<string, FilaPotrero> */
    private function filas(): array
    {
        return EstadoPotreros::filas()->keyBy(fn (FilaPotrero $f) => $f->potrero->nombre)->all();
    }

    public function test_clasifica_por_la_ultima_recorrida_activa_con_puntos(): void
    {
        $lote1 = $this->potrero('Lote 1');
        $this->recorrida($lote1, '2026-10-01 09:00', [5]);              // vieja, no cuenta
        $ultima = $this->recorrida($lote1, '2026-10-08 09:00', [21, 23]); // media 22
        $this->recorrida($lote1, '2026-10-09 09:00', [3], activa: false); // anulada, no cuenta
        $this->recorrida($lote1, '2026-10-09 10:00', [4], puntosActivos: false); // todos descartados, no cuenta

        $fila = $this->filas()['Lote 1'];

        $this->assertSame(EstadoPotrero::Listo, $fila->estado);
        $this->assertSame($ultima->id, $fila->ultima->id);
        $this->assertEqualsWithDelta(22.0, $fila->alturaCm(), 0.001);
        $this->assertEqualsWithDelta(2200.0, $fila->kgMsHa(), 0.001);
        $this->assertSame(2, $fila->dias);
        $this->assertFalse($fila->sinMedir);
    }

    public function test_los_bordes_de_las_alturas(): void
    {
        $this->recorrida($this->potrero('Justo ingreso'), '2026-10-09 09:00', [20]);
        $this->recorrida($this->potrero('Justo salida'), '2026-10-10 08:00', [7]);
        $this->recorrida($this->potrero('Debajo'), '2026-10-09 09:00', [6.9]);

        $filas = $this->filas();

        $this->assertSame(EstadoPotrero::Listo, $filas['Justo ingreso']->estado);
        $this->assertSame(EstadoPotrero::EnCrecimiento, $filas['Justo salida']->estado);
        $this->assertSame(0, $filas['Justo salida']->dias);
        $this->assertSame(EstadoPotrero::BajoRemanente, $filas['Debajo']->estado);
    }

    public function test_sin_medir_es_con_mas_dias_que_los_de_configuracion(): void
    {
        $this->recorrida($this->potrero('Siete días'), '2026-10-03 18:00', [10]);
        $this->recorrida($this->potrero('Ocho días'), '2026-10-02 07:00', [10]);
        $this->potrero('Nuevo');

        $filas = $this->filas();

        $this->assertSame(7, $filas['Siete días']->dias);
        $this->assertFalse($filas['Siete días']->sinMedir);
        $this->assertSame(8, $filas['Ocho días']->dias);
        $this->assertTrue($filas['Ocho días']->sinMedir);
        // El estado de altura se sigue mostrando: es el último dato que hay.
        $this->assertSame(EstadoPotrero::EnCrecimiento, $filas['Ocho días']->estado);

        $this->assertSame(EstadoPotrero::NuncaMedido, $filas['Nuevo']->estado);
        $this->assertNull($filas['Nuevo']->ultima);
        $this->assertNull($filas['Nuevo']->dias);
        $this->assertFalse($filas['Nuevo']->sinMedir);
    }

    public function test_sin_alturas_de_referencia(): void
    {
        $this->recorrida($this->potrero('Sorgo', TipoPastura::factory()->sinAlturas()->create()), '2026-10-09 09:00', [15]);
        $sinTipo = Potrero::factory()->for($this->campo)->create(['nombre' => 'Sin tipo', 'tipo_pastura_id' => null]);
        $this->recorrida($sinTipo, '2026-10-09 09:00', [15]);

        $filas = $this->filas();

        $this->assertSame(EstadoPotrero::SinAlturas, $filas['Sorgo']->estado);
        $this->assertSame(EstadoPotrero::SinAlturas, $filas['Sin tipo']->estado);
        $this->assertNull($filas['Sin tipo']->kgMsHa());
    }

    public function test_solo_potreros_activos_de_campos_activos(): void
    {
        $this->potrero('Activo');
        $this->potrero('Dado de baja', mas: ['activo' => false]);
        $this->potrero('De un campo de baja', mas: ['campo' => Campo::factory()->inactivo()->create()]);

        $this->assertSame(['Activo'], array_keys($this->filas()));
    }
}
