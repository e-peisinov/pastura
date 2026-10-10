<?php

namespace Tests\Feature\Recorridas;

use App\Enums\Nivel;
use App\Enums\Rol;
use App\Livewire\Recorridas\RecorridaDetalle;
use App\Livewire\Recorridas\Recorridas;
use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El listado de recorridas: columnas con el resumen calculado (regla 8),
 * filtros, orden, pertenencia (dominio.md, regla 3) y el CSV del listado.
 */
class RecorridasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $juan;

    private User $ana;

    private Potrero $lote4;

    private Potrero $bajo;

    private Potrero $sanJose;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-10 12:00:00');

        $this->admin = User::factory()->admin()->create(['name' => 'Administración']);
        $this->juan = User::factory()->create(['name' => 'Juan Pérez', 'rol' => Rol::Recorredor]);
        $this->ana = User::factory()->create(['name' => 'Ana Gómez', 'rol' => Rol::Recorredor]);

        $esperanza = Campo::factory()->create(['nombre' => 'La Esperanza']);
        $this->lote4 = Potrero::factory()->for($esperanza)->create(['nombre' => 'Lote 4']);
        $this->bajo = Potrero::factory()->for($esperanza)->create(['nombre' => 'El Bajo']);
        $this->sanJose = Potrero::factory()->for(Campo::factory()->create(['nombre' => 'San José']))->create(['nombre' => 'Potrero Norte']);
    }

    /** @param  list<float>  $alturas */
    private function recorrida(Potrero $potrero, User $usuario, string $fecha, array $alturas = [10], array $estado = []): Recorrida
    {
        $recorrida = Recorrida::factory()->for($potrero)->for($usuario)->create(['iniciada_en' => $fecha, ...$estado]);

        foreach ($alturas as $i => $altura) {
            Medicion::factory()->for($recorrida)->create(['altura_cm' => $altura, 'kg_ms_ha' => (int) round(100 * $altura + 900), 'tomada_en' => $fecha]);
        }

        return $recorrida;
    }

    private function filas($componente): array
    {
        preg_match_all('/<tr wire:key="fila-(\d+)"/', $componente->html(), $m);

        return array_map('intval', $m[1]);
    }

    // ----- Listado -----------------------------------------------------------

    public function test_el_listado_muestra_el_resumen_de_cada_recorrida(): void
    {
        // 10 + 12,5 + 14 = 36,5 / 3 = 12,2 cm; kg: (1900 + 2150 + 2300) / 3 = 2117.
        $this->recorrida($this->lote4, $this->juan, '2026-10-05 08:30:00', [10, 12.5, 14]);

        $this->actingAs($this->admin)->get(route('recorridas'))
            ->assertOk()
            ->assertSeeInOrder(['05/10/2026 08:30', 'La Esperanza · Lote 4', 'Juan Pérez', '3', '12,2 cm', '2.117', 'Válida']);
    }

    public function test_sin_recorridas_muestra_el_mensaje_vacio(): void
    {
        $this->actingAs($this->admin)->get(route('recorridas'))
            ->assertOk()
            ->assertSee(__('recorridas.sin_registros'));
    }

    public function test_el_recorredor_ve_solo_las_suyas_y_no_tiene_filtro_de_recorredor(): void
    {
        $suya = $this->recorrida($this->lote4, $this->juan, '2026-10-05 08:00:00');
        $ajena = $this->recorrida($this->bajo, $this->ana, '2026-10-06 08:00:00');

        $componente = Livewire::actingAs($this->juan)->test(Recorridas::class);
        $this->assertSame([$suya->id], $this->filas($componente));
        $componente->assertDontSeeHtml("entangle('filtroRecorredor')");

        // El filtro de recorredor, aunque venga en la URL, no le abre las ajenas.
        $componente->set('filtroRecorredor', $this->ana->id);
        $this->assertSame([$suya->id], $this->filas($componente));

        // Tampoco la ficha.
        $this->expectException(ModelNotFoundException::class);
        $componente->call('verDetalle', $ajena->id);
    }

    public function test_el_administrador_ve_todas_y_filtra_por_recorredor(): void
    {
        $deJuan = $this->recorrida($this->lote4, $this->juan, '2026-10-05 08:00:00');
        $deAna = $this->recorrida($this->bajo, $this->ana, '2026-10-06 08:00:00');

        $componente = Livewire::actingAs($this->admin)->test(Recorridas::class)
            ->assertSeeHtml("entangle('filtroRecorredor')");
        $this->assertSame([$deAna->id, $deJuan->id], $this->filas($componente));

        $componente->set('filtroRecorredor', $this->ana->id);
        $this->assertSame([$deAna->id], $this->filas($componente));
    }

    public function test_el_potrero_depende_del_campo(): void
    {
        $enLote4 = $this->recorrida($this->lote4, $this->juan, '2026-10-05 08:00:00');
        $enBajo = $this->recorrida($this->bajo, $this->juan, '2026-10-06 08:00:00');
        $enSanJose = $this->recorrida($this->sanJose, $this->juan, '2026-10-07 08:00:00');

        $componente = Livewire::actingAs($this->admin)->test(Recorridas::class)
            ->set('filtroCampo', $this->lote4->campo_id);
        $this->assertSame([$enBajo->id, $enLote4->id], $this->filas($componente));

        $componente->set('filtroPotrero', $this->lote4->id);
        $this->assertSame([$enLote4->id], $this->filas($componente));

        // Cambiar de campo limpia un potrero que no es de ese campo.
        $componente->set('filtroCampo', $this->sanJose->campo_id)->assertSet('filtroPotrero', null);
        $this->assertSame([$enSanJose->id], $this->filas($componente));
    }

    public function test_el_periodo_filtra_por_fecha_y_tiene_atajos(): void
    {
        $vieja = $this->recorrida($this->lote4, $this->juan, '2026-08-20 08:00:00');
        $deSeptiembre = $this->recorrida($this->lote4, $this->juan, '2026-09-15 08:00:00');
        $deOctubre = $this->recorrida($this->lote4, $this->juan, '2026-10-02 23:30:00');

        $componente = Livewire::actingAs($this->admin)->test(Recorridas::class);
        $this->assertSame([$deOctubre->id, $deSeptiembre->id, $vieja->id], $this->filas($componente));

        // Hasta incluye el día entero.
        $componente->set('desde', '2026-09-01')->set('hasta', '2026-10-02');
        $this->assertSame([$deOctubre->id, $deSeptiembre->id], $this->filas($componente));

        // Hoy es 10/10: los últimos 30 días arrancan el 11/09.
        $componente->call('ultimos30Dias')->assertSet('desde', '2026-09-11')->assertSet('hasta', '2026-10-10');
        $this->assertSame([$deOctubre->id, $deSeptiembre->id], $this->filas($componente));

        $componente->call('esteMes')->assertSet('desde', '2026-10-01')->assertSet('hasta', '2026-10-10');
        $this->assertSame([$deOctubre->id], $this->filas($componente));

        $componente->call('todo')->assertSet('desde', null)->assertSet('hasta', null);
        $this->assertSame([$deOctubre->id, $deSeptiembre->id, $vieja->id], $this->filas($componente));
    }

    public function test_fechas_invalidas_quedan_vacias_y_cruzadas_manda_la_que_se_toco(): void
    {
        Livewire::actingAs($this->admin)->test(Recorridas::class)
            ->set('desde', 'ayer')->assertSet('desde', null)
            ->set('desde', '2026-10-05')
            ->set('hasta', '2026-10-01')->assertSet('desde', '2026-10-01')->assertSet('hasta', '2026-10-01')
            ->set('desde', '2026-10-08')->assertSet('hasta', '2026-10-08');

        Livewire::withQueryParams(['desde' => '31/12/2026', 'estado' => 'cualquiera'])
            ->actingAs($this->admin)->test(Recorridas::class)
            ->assertSet('desde', null)
            ->assertSet('filtroEstado', 'activas');
    }

    public function test_el_estado_separa_validas_y_anuladas(): void
    {
        $valida = $this->recorrida($this->lote4, $this->juan, '2026-10-05 08:00:00');
        $anulada = $this->recorrida($this->lote4, $this->juan, '2026-10-06 08:00:00', estado: ['activo' => false]);

        $componente = Livewire::actingAs($this->admin)->test(Recorridas::class);
        $this->assertSame([$valida->id], $this->filas($componente));

        $componente->set('filtroEstado', 'anuladas')->assertSee('Anulada');
        $this->assertSame([$anulada->id], $this->filas($componente));

        $componente->set('filtroEstado', 'todas');
        $this->assertSame([$anulada->id, $valida->id], $this->filas($componente));

        $componente->call('limpiarFiltros')->assertSet('filtroEstado', 'activas');
    }

    public function test_ordena_por_fecha_y_por_altura(): void
    {
        $baja = $this->recorrida($this->lote4, $this->juan, '2026-10-01 08:00:00', [6, 8]);       // 7
        $alta = $this->recorrida($this->lote4, $this->juan, '2026-10-02 08:00:00', [20, 22]);     // 21
        $media = $this->recorrida($this->lote4, $this->juan, '2026-10-03 08:00:00', [12, 14]);    // 13

        $componente = Livewire::actingAs($this->admin)->test(Recorridas::class);
        $this->assertSame([$media->id, $alta->id, $baja->id], $this->filas($componente), 'Abre por fecha descendente');

        $componente->call('ordenarPor', 'altura');
        $this->assertSame([$baja->id, $media->id, $alta->id], $this->filas($componente));

        $componente->call('ordenarPor', 'altura');
        $this->assertSame([$alta->id, $media->id, $baja->id], $this->filas($componente));

        $componente->call('ordenarPor', 'fecha');
        $this->assertSame([$baja->id, $alta->id, $media->id], $this->filas($componente));
    }

    public function test_los_puntos_descartados_no_cuentan_en_el_listado(): void
    {
        $recorrida = $this->recorrida($this->lote4, $this->juan, '2026-10-05 08:00:00', [10, 12]);
        Medicion::factory()->for($recorrida)->create(['altura_cm' => 40, 'activo' => false]);

        $this->actingAs($this->admin)->get(route('recorridas'))
            ->assertSeeInOrder(['Juan Pérez', '2', '11,0 cm'])
            ->assertDontSee('20,7 cm');
    }

    // ----- Ficha -------------------------------------------------------------

    public function test_la_ficha_se_abre_como_hijo_y_cierra_con_su_evento(): void
    {
        $recorrida = $this->recorrida($this->lote4, $this->juan, '2026-10-05 08:00:00');

        Livewire::actingAs($this->admin)->test(Recorridas::class)
            ->call('verDetalle', $recorrida->id)
            ->assertSet('detalleId', $recorrida->id)
            ->assertSeeLivewire(RecorridaDetalle::class)
            ->dispatch('cerrar-recorrida-detalle')
            ->assertSet('detalleId', null)
            ->assertDontSeeLivewire(RecorridaDetalle::class);
    }

    // ----- Acceso y CSV ------------------------------------------------------

    public function test_acceso_por_permiso(): void
    {
        $sinAcceso = User::factory()->conNivel('recorridas', Nivel::SinAcceso)->create(['rol' => Rol::Recorredor]);
        $this->actingAs($sinAcceso)->get(route('recorridas'))->assertForbidden();

        $lector = User::factory()->conNivel('recorridas', Nivel::Ver)->create(['rol' => Rol::Recorredor]);
        $this->actingAs($lector)->get(route('recorridas'))->assertOk()->assertDontSee(__('recorridas.exportar'));
        Livewire::actingAs($lector)->test(Recorridas::class)->call('exportar')->assertForbidden();
    }

    public function test_exporta_el_listado_filtrado(): void
    {
        $this->recorrida($this->lote4, $this->juan, '2026-09-15 08:00:00', [10, 11]);
        $this->recorrida($this->bajo, $this->ana, '2026-10-02 08:00:00', [20]);

        Livewire::actingAs($this->admin)->test(Recorridas::class)
            ->call('exportar')
            ->assertFileDownloaded('recorridas.csv')
            ->set('desde', '2026-09-01')->set('hasta', '2026-09-30')
            ->call('exportar')
            ->assertFileDownloaded('recorridas-2026-09-01-al-2026-09-30.csv');

        // El recorredor exporta solo lo suyo: sus dos puntos más la cabecera.
        $componente = Livewire::actingAs($this->juan)->test(Recorridas::class)->call('exportar');
        $csv = base64_decode($componente->effects['download']['content']);
        $lineas = explode("\r\n", trim($csv));

        $this->assertCount(3, $lineas);
        $this->assertStringNotContainsString('Ana Gómez', $csv);
    }
}
