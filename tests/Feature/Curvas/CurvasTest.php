<?php

namespace Tests\Feature\Curvas;

use App\Enums\Nivel;
use App\Livewire\Curvas\Curvas;
use App\Models\Auditoria;
use App\Models\Campo;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\Potrero;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de curvas de biomasa (P23): listado con su recta calculada,
 * filtros en la URL, ficha con el gráfico, hacer vigente y eliminar.
 */
class CurvasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Potrero $lote4;

    private EstadoFenologico $rebrote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->lote4 = Potrero::factory()->for(Campo::factory()->create(['nombre' => 'La Esperanza']))->create(['nombre' => 'Lote 4']);
        $this->rebrote = EstadoFenologico::factory()->create(['nombre' => 'Rebrote']);
    }

    private function curva(array $atributos = [], array $recta = [108, 250]): CurvaBiomasa
    {
        return CurvaBiomasa::factory()->recta(...$recta)->create($atributos + [
            'potrero_id' => $this->lote4->id,
            'estado_fenologico_id' => $this->rebrote->id,
        ]);
    }

    public function test_el_listado_muestra_la_recta_calculada_de_cada_curva(): void
    {
        $this->curva(['fecha' => '2026-09-12']);

        $this->actingAs($this->admin)->get(route('curvas'))
            ->assertOk()
            ->assertSee('La Esperanza · Lote 4')
            ->assertSee('Rebrote')
            ->assertSee('12/09/2026')
            ->assertSee('kg MS/ha = 108,0 × cm + 250')
            ->assertSee('Vigente');
    }

    public function test_la_ficha_del_campo_lleva_a_las_curvas_del_potrero(): void
    {
        $otro = Potrero::factory()->for($this->lote4->campo)->create(['nombre' => 'El Bajo']);
        $this->curva();
        CurvaBiomasa::factory()->recta(40, 200)->create(['potrero_id' => $otro->id, 'estado_fenologico_id' => $this->rebrote->id]);

        Livewire::withQueryParams(['potrero' => $this->lote4->id])
            ->actingAs($this->admin)->test(Curvas::class)
            ->assertSet('filtroCampo', $this->lote4->campo_id)
            ->assertSee('kg MS/ha = 108,0 × cm + 250')
            ->assertDontSee('kg MS/ha = 40,0 × cm + 200');
    }

    /** Por omisión solo las vigentes; "Reemplazadas" muestra las otras. */
    public function test_filtra_por_vigencia_y_estado(): void
    {
        $this->curva(recta: [50, 0]);
        $this->curva(recta: [108, 250]);
        $primavera = EstadoFenologico::factory()->create(['nombre' => 'Pleno crecimiento']);
        $this->curva(['estado_fenologico_id' => $primavera->id], [40, 200]);

        Livewire::actingAs($this->admin)->test(Curvas::class)
            ->assertSee('kg MS/ha = 108,0 × cm + 250')
            ->assertDontSee('kg MS/ha = 50,0 × cm + 0')
            ->set('filtroVigencia', 'reemplazadas')
            ->assertSee('kg MS/ha = 50,0 × cm + 0')
            ->assertDontSee('kg MS/ha = 108,0 × cm + 250')
            ->set('filtroVigencia', 'todas')
            ->set('filtroEstado', $primavera->id)
            ->assertSee('kg MS/ha = 40,0 × cm + 200')
            ->assertDontSee('kg MS/ha = 108,0 × cm + 250');
    }

    public function test_la_ficha_muestra_el_grafico_y_las_muestras(): void
    {
        $curva = $this->curva();

        Livewire::actingAs($this->admin)->test(Curvas::class)
            ->call('verDetalle', $curva->id)
            ->assertSeeHtml('data-detalle-curva')
            ->assertSeeHtml('data-grafico-dispersion')
            ->assertSeeHtml('data-recta')
            ->assertSee('Con el disco de 0,1 m²: cada gramo seco son 100 kg MS/ha.')
            // 108 × 10 + 250 = 1330 kg → 13,30 g con 0,10 m².
            ->assertSee('13,30')
            ->assertSee('1.330');
    }

    public function test_hacer_vigente_una_reemplazada(): void
    {
        $vieja = $this->curva(recta: [50, 0]);
        $nueva = $this->curva();

        Livewire::actingAs($this->admin)->test(Curvas::class)
            ->call('hacerVigente', $vieja->id)
            ->assertDispatched('toast', tipo: 'exito');

        $this->assertTrue($vieja->fresh()->activo);
        $this->assertFalse($nueva->fresh()->activo);
    }

    public function test_eliminar_borra_la_curva_con_sus_muestras_y_lo_audita(): void
    {
        $curva = $this->curva();

        Livewire::actingAs($this->admin)->test(Curvas::class)
            ->call('confirmarBaja', $curva->id)
            ->assertSee('¿Está seguro de que desea eliminar la curva de La Esperanza · Lote 4 (Rebrote,')
            ->call('baja')
            ->assertDispatched('toast', tipo: 'exito');

        $this->assertModelMissing($curva);
        $this->assertDatabaseCount('muestras_curva', 0);
        $this->assertSame('Eliminó la curva de biomasa de La Esperanza · Lote 4 (Rebrote, '.$curva->fecha->format('d/m/Y').').', Auditoria::query()->latest('id')->first()->descripcion());
    }

    public function test_con_ver_consulta_pero_no_edita(): void
    {
        $curva = $this->curva();
        $recorredor = User::factory()->create();

        $this->actingAs($recorredor)->get(route('curvas'))->assertOk()->assertDontSee('Nueva curva');

        Livewire::actingAs($recorredor)->test(Curvas::class)->call('verDetalle', $curva->id)->assertSeeHtml('data-detalle-curva');
        Livewire::actingAs($recorredor)->test(Curvas::class)->call('nueva')->assertForbidden();
        Livewire::actingAs($recorredor)->test(Curvas::class)->call('hacerVigente', $curva->id)->assertForbidden();
        Livewire::actingAs($recorredor)->test(Curvas::class)->call('confirmarBaja', $curva->id)->assertForbidden();
    }

    public function test_sin_acceso_da_403(): void
    {
        $this->actingAs(User::factory()->admin()->conNivel('curvas', Nivel::SinAcceso)->create())
            ->get(route('curvas'))
            ->assertForbidden();
    }
}
