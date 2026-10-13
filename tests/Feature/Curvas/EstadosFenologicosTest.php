<?php

namespace Tests\Feature\Curvas;

use App\Enums\Nivel;
use App\Livewire\Curvas\AreaDisco;
use App\Livewire\Curvas\Curvas;
use App\Livewire\Curvas\EstadoFenologicoFormulario;
use App\Livewire\Curvas\EstadosFenologicos;
use App\Models\Configuracion;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los estados de la pastura y el área del disco se administran en Curvas
 * (P24): solo sirven para las curvas. Con Editar en Curvas se gestionan;
 * con Ver se miran.
 */
class EstadosFenologicosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Configuracion::factory()->create();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_la_pantalla_de_curvas_muestra_los_estados_y_el_area_del_disco(): void
    {
        Configuracion::instancia()->update(['area_disco_m2' => 0.25]);
        EstadoFenologico::factory()->create(['nombre' => 'Rebrote']);

        $this->actingAs($this->admin)->get(route('curvas'))
            ->assertOk()
            ->assertSeeHtml('data-bloque-estados')
            ->assertSeeHtml('data-bloque-area-disco')
            ->assertSee('Rebrote')
            ->assertSee('0,25 m²');
    }

    /** En el orden en que se cargaron, con cuántas curvas y recorridas los usan. */
    public function test_muestra_los_estados_con_su_uso(): void
    {
        $rebrote = EstadoFenologico::factory()->create(['nombre' => 'Rebrote']);
        EstadoFenologico::factory()->create(['nombre' => 'Pleno crecimiento']);
        CurvaBiomasa::factory()->count(2)->recta(100, 0)->create(['estado_fenologico_id' => $rebrote->id]);

        Livewire::actingAs($this->admin)->test(EstadosFenologicos::class)
            ->assertSeeInOrder(['Estados de la pastura', 'Rebrote', '2 curvas · sin recorridas', 'Pleno crecimiento', 'Sin curvas · sin recorridas']);
    }

    public function test_un_estado_sin_uso_se_elimina_y_uno_con_curvas_se_desactiva(): void
    {
        $sinUso = EstadoFenologico::factory()->create(['nombre' => 'Floración']);
        $conUso = EstadoFenologico::factory()->create(['nombre' => 'Rebrote']);
        CurvaBiomasa::factory()->recta(100, 0)->create(['estado_fenologico_id' => $conUso->id]);

        Livewire::actingAs($this->admin)->test(EstadosFenologicos::class)
            ->call('nuevo')->assertSeeLivewire(EstadoFenologicoFormulario::class)
            ->dispatch('cerrar-estado-fenologico')->assertSet('formularioId', null)
            ->call('editar', $conUso->id)->assertSet('formularioId', $conUso->id)
            ->dispatch('cerrar-estado-fenologico')
            ->call('confirmarBaja', $sinUso->id)
            ->assertSee('¿Eliminar el estado "Floración"?')
            ->call('baja')
            ->assertDispatched('toast', tipo: 'exito')
            ->assertDispatched('estados-fenologicos-cambiados')
            ->call('confirmarBaja', $conUso->id)
            ->assertSee('lo usan curvas o recorridas')
            ->call('baja')
            ->assertDispatched('toast', tipo: 'aviso');

        $this->assertModelMissing($sinUso);
        $this->assertFalse($conUso->fresh()->activo);

        Livewire::actingAs($this->admin)->test(EstadosFenologicos::class)
            ->call('reactivar', $conUso->id)
            ->assertDispatched('estados-fenologicos-cambiados');
        $this->assertTrue($conUso->fresh()->activo);
    }

    public function test_alta_y_edicion_de_un_estado_con_nombre_unico(): void
    {
        EstadoFenologico::factory()->create(['nombre' => 'Rebrote']);

        Livewire::actingAs($this->admin)->test(EstadoFenologicoFormulario::class)
            ->set('nombre', 'rebrote')
            ->call('guardar')
            ->assertHasErrors(['nombre'])
            ->set('nombre', ' Recién pastoreada ')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('estados-fenologicos-cambiados')
            ->assertDispatched('cerrar-estado-fenologico');

        $estado = EstadoFenologico::query()->where('nombre', 'Recién pastoreada')->sole();

        Livewire::actingAs($this->admin)->test(EstadoFenologicoFormulario::class, ['estadoId' => $estado->id])
            ->assertSet('nombre', 'Recién pastoreada')
            ->set('nombre', 'Post pastoreo')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Post pastoreo', $estado->fresh()->nombre);
    }

    /** Un estado nuevo aparece en el filtro de la pantalla sin recargarla. */
    public function test_la_pantalla_refresca_el_filtro_cuando_cambian_los_estados(): void
    {
        $pantalla = Livewire::actingAs($this->admin)->test(Curvas::class);

        EstadoFenologico::factory()->create(['nombre' => 'Floración']);

        $pantalla->dispatch('estados-fenologicos-cambiados')->assertSee('Floración');
    }

    /** El área se escribe con coma y va entre 0,005 y 1 m². */
    public function test_el_area_del_disco_se_edita_en_curvas(): void
    {
        Livewire::actingAs($this->admin)->test(Curvas::class)
            ->call('abrirAreaDisco')->assertSet('mostrarAreaDisco', true)->assertSeeLivewire(AreaDisco::class)
            ->dispatch('cerrar-area-disco')->assertSet('mostrarAreaDisco', false);

        Livewire::actingAs($this->admin)->test(AreaDisco::class)
            ->assertSet('area_disco_m2', '0,1')
            ->set('area_disco_m2', '2')
            ->call('guardar')
            ->assertHasErrors(['area_disco_m2'])
            ->set('area_disco_m2', '0,25')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('area-disco-guardada')
            ->assertDispatched('cerrar-area-disco');

        $this->assertSame(0.25, Configuracion::instancia()->area_disco_m2);
    }

    /** El recorredor nace con Ver en Curvas: mira los estados y el disco, no los cambia. */
    public function test_con_ver_se_mira_sin_botones_y_las_acciones_se_rechazan(): void
    {
        $estado = EstadoFenologico::factory()->create(['nombre' => 'Rebrote']);
        $soloVer = User::factory()->conNivel('curvas', Nivel::Ver)->create();

        Livewire::actingAs($soloVer)->test(EstadosFenologicos::class)
            ->assertSee('Rebrote')
            ->assertDontSeeHtml('wire:click="nuevo"')
            ->call('confirmarBaja', $estado->id)
            ->assertForbidden();

        Livewire::actingAs($soloVer)->test(Curvas::class)
            ->assertSee('0,1 m²')
            ->assertDontSeeHtml('wire:click="abrirAreaDisco"')
            ->call('abrirAreaDisco')
            ->assertForbidden();

        Livewire::actingAs($soloVer)->test(EstadoFenologicoFormulario::class)->assertForbidden();
        Livewire::actingAs($soloVer)->test(AreaDisco::class)->assertForbidden();
    }

    /** Configuración ya no los tiene. */
    public function test_configuracion_ya_no_tiene_estados_ni_area_del_disco(): void
    {
        $this->actingAs($this->admin)->get(route('configuracion'))
            ->assertOk()
            ->assertDontSeeHtml('data-bloque-estados')
            ->assertDontSee('Área del disco');
    }
}
