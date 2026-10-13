<?php

namespace Tests\Feature\Tablero;

use App\Enums\Rol;
use App\Livewire\Recorridas\RecorridaDetalle;
use App\Livewire\Tablero\PotrerosParaMedir;
use App\Livewire\Tablero\PotrerosPorEstado;
use App\Livewire\Tablero\RecorridasParaRevisar;
use App\Livewire\Tablero\UltimasRecorridas;
use App\Models\Campo;
use App\Models\Configuracion;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\CurvaBiomasa;
use App\Models\TipoPastura;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El tablero (P18): qué bloques ve cada rol, sus contadores, los filtros
 * dentro del bloque (D76) y la ficha de la recorrida encima (D78).
 */
class TableroTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $juan;

    private Campo $campo;

    private TipoPastura $alfalfa;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 15:00:00');
        Configuracion::factory()->create(['dias_sin_medir' => 7, 'puntos_minimos' => 3]);
        $this->admin = User::factory()->admin()->create(['name' => 'Marta Admin']);
        $this->juan = User::factory()->create(['name' => 'Juan Pérez', 'rol' => Rol::Recorredor]);
        $this->campo = Campo::factory()->create(['nombre' => 'La Esperanza']);
        $this->alfalfa = TipoPastura::factory()->create([
            'nombre' => 'Alfalfa', 'altura_ingreso_cm' => 20, 'altura_salida_cm' => 7,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Los de alfalfa tienen su curva vigente: kg = 100 × h (P23). */
    private function potrero(string $nombre, ?TipoPastura $tipo = null): Potrero
    {
        $potrero = Potrero::factory()->conContorno()->for($this->campo)->create(['nombre' => $nombre, 'tipo_pastura_id' => ($tipo ?? $this->alfalfa)->id]);

        if ($tipo === null) {
            CurvaBiomasa::factory()->recta(100, 0)->create(['potrero_id' => $potrero->id]);
        }

        return $potrero;
    }

    /** @param  list<float>  $alturas */
    private function recorrida(Potrero $potrero, string $fecha, array $alturas, ?User $quien = null): Recorrida
    {
        $recorrida = Recorrida::factory()->for($potrero)->for($quien ?? $this->juan)->create(['iniciada_en' => $fecha]);

        foreach ($alturas as $altura) {
            Medicion::factory()->for($recorrida)->create(['altura_cm' => $altura, 'tomada_en' => $fecha]);
        }

        return $recorrida;
    }

    public function test_cada_rol_ve_sus_bloques(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bienvenido, Marta Admin.')
            ->assertSeeInOrder(['data-bloque="estado"', 'data-bloque="para-medir"', 'data-bloque="recorridas"', 'data-bloque="revisar"'], false)
            ->assertSee('Últimas recorridas');

        $this->actingAs($this->juan)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['data-bloque="para-medir"', 'data-bloque="recorridas"'], false)
            ->assertDontSee('data-bloque="estado"', false)
            ->assertDontSee('data-bloque="revisar"', false)
            ->assertSee('Mis recorridas');
    }

    public function test_estado_cuenta_y_lista_los_listos_los_mas_altos_primero(): void
    {
        $this->recorrida($this->potrero('Lote 1'), '2026-10-09 09:00', [21]);
        $alto = $this->recorrida($this->potrero('Lote 2'), '2026-10-08 09:00', [30]);
        $this->recorrida($this->potrero('Lote 3'), '2026-10-09 09:00', [12]);
        $this->recorrida($this->potrero('Lote 4'), '2026-10-09 09:00', [5]);
        $this->recorrida($this->potrero('Sorgo', TipoPastura::factory()->sinAlturas()->create()), '2026-10-09 09:00', [15]);

        Livewire::actingAs($this->admin)->test(PotrerosPorEstado::class)
            ->assertSeeInOrder(['Listo para pastorear', '2', 'En crecimiento', '1', 'Bajo el remanente', '1'])
            ->assertSeeInOrder(['Listos para pastorear', 'La Esperanza · Lote 2', 'Hace 2 días', '30,0 cm', '3.000 kg MS/ha', 'La Esperanza · Lote 1', '21,0 cm'])
            ->assertDontSee('Lote 3')
            ->assertSee('1 potrero no tiene alturas de referencia')
            ->assertSeeHtml('href="'.route('configuracion').'"')
            ->call('filtrar', 'bajo_remanente')
            ->assertSet('filtro', 'bajo_remanente')
            ->assertSee('Lote 4')->assertDontSee('Lote 2')
            ->call('filtrar', 'bajo_remanente')
            ->assertSet('filtro', null)
            ->call('filtrar', 'sin_alturas')
            ->assertSet('filtro', null)
            ->call('verDetalle', $alto->id)
            ->assertSet('detalleId', $alto->id)
            ->assertSeeLivewire(RecorridaDetalle::class)
            ->dispatch('cerrar-recorrida-detalle')
            ->assertSet('detalleId', null);
    }

    public function test_para_medir_primero_los_nunca_medidos_y_despues_los_mas_viejos(): void
    {
        $this->recorrida($this->potrero('Al día'), '2026-10-09 09:00', [10]);
        $this->recorrida($this->potrero('Diez días'), '2026-09-30 09:00', [12]);
        $this->recorrida($this->potrero('Veinte días'), '2026-09-20 09:00', [8]);
        $this->potrero('Nuevo');

        Livewire::actingAs($this->juan)->test(PotrerosParaMedir::class)
            ->assertSeeInOrder(['Sin medir', '2', 'Nunca medidos', '1'])
            ->assertSeeInOrder(['Nuevo', 'Nunca medido', 'Veinte días', 'Hace 20 días · 8,0 cm', 'Diez días', 'Hace 10 días'])
            ->assertDontSee('Al día')
            ->call('filtrar', 'nunca_medidos')
            ->assertSee('Nuevo')->assertDontSee('Veinte días');
    }

    public function test_para_medir_sin_pendientes(): void
    {
        $this->recorrida($this->potrero('Al día'), '2026-10-09 09:00', [10]);

        Livewire::actingAs($this->admin)->test(PotrerosParaMedir::class)
            ->assertSee('Todos los potreros se midieron en los últimos 7 días.');
    }

    public function test_ultimas_recorridas_y_el_recorredor_solo_las_suyas(): void
    {
        $lote = $this->potrero('Lote 1');
        $deJuan = $this->recorrida($lote, '2026-10-10 08:00', [10, 12]);
        $this->recorrida($lote, '2026-10-05 08:00', [10]);
        $ajena = $this->recorrida($lote, '2026-10-09 08:00', [14], User::factory()->create(['name' => 'Ana Gómez', 'rol' => Rol::Recorredor]));
        $this->recorrida($lote, '2026-09-01 08:00', [9]);
        Recorrida::factory()->for($lote)->for($this->juan)->anulada()->create(['iniciada_en' => '2026-10-10 09:00']);

        Livewire::actingAs($this->admin)->test(UltimasRecorridas::class)
            ->assertSeeInOrder(['Hoy', '1', 'Últimos 7 días', '3'])
            ->assertSeeHtml('href="'.e(route('recorridas', ['desde' => '2026-10-04', 'hasta' => '2026-10-10'])).'"')
            ->assertSeeInOrder(['10/10/2026 08:00', 'Juan Pérez', '2 puntos', '11,0 cm', '09/10/2026', 'Ana Gómez']);

        Livewire::actingAs($this->juan)->test(UltimasRecorridas::class)
            ->assertSeeInOrder(['Hoy', '1', 'Últimos 7 días', '2'])
            ->assertDontSee('Ana Gómez')
            ->call('verDetalle', $deJuan->id)
            ->assertSet('detalleId', $deJuan->id);

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($this->juan)->test(UltimasRecorridas::class)->call('verDetalle', $ajena->id);
    }

    public function test_para_revisar_cuenta_por_motivo_y_filtra(): void
    {
        $lote = $this->potrero('Lote 1');
        $this->recorrida($lote, '2026-10-09 08:00', [10, 11, 12]); // bien
        $pocos = $this->recorrida($lote, '2026-10-08 08:00', [10]);
        $sinGps = $this->recorrida($lote, '2026-10-07 08:00', [10, 11, 12]);
        $sinGps->mediciones()->first()->update(['latitud' => null, 'longitud' => null]);

        Livewire::actingAs($this->admin)->test(RecorridasParaRevisar::class)
            ->assertSeeInOrder(['Pocos puntos', '1', 'Sin GPS', '1', 'Fuera del contorno', '0'])
            ->assertSeeInOrder(['08/10/2026', 'Pocos puntos', '07/10/2026', 'Sin GPS'])
            ->call('filtrar', 'sin_gps')
            ->assertSee('07/10/2026')->assertDontSee('08/10/2026')
            ->call('verDetalle', $pocos->id)
            ->assertSeeLivewire(RecorridaDetalle::class);
    }

    public function test_sin_nada_cargado_los_bloques_muestran_su_vacio(): void
    {
        Livewire::actingAs($this->admin)->test(PotrerosPorEstado::class)->assertSee('Ningún potrero llegó a su altura de ingreso.');
        Livewire::actingAs($this->admin)->test(UltimasRecorridas::class)->assertSee('Todavía no hay recorridas.');
        Livewire::actingAs($this->juan)->test(UltimasRecorridas::class)->assertSee('Todavía no hizo recorridas.');
        Livewire::actingAs($this->admin)->test(RecorridasParaRevisar::class)->assertSee('No hay recorridas para revisar en los últimos 30 días.');
    }
}
