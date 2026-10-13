<?php

namespace Tests\Feature\Configuracion;

use App\Enums\Nivel;
use App\Livewire\Configuracion\Configuracion as PantallaConfiguracion;
use App\Livewire\Configuracion\DatosOrganizacion;
use App\Livewire\Configuracion\EstadoFenologicoFormulario;
use App\Livewire\Configuracion\Parametros;
use App\Livewire\Configuracion\TipoPasturaFormulario;
use App\Models\Configuracion;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\Potrero;
use App\Models\TipoPastura;
use App\Models\User;
use App\Support\Modulos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Configuración (P17): la organización, los tipos de pastura (P16), los
 * estados de la pastura (P23) y los parámetros. Cada bloque se edita en su
 * modal hijo; la baja y la reactivación de un tipo o un estado viven en el padre.
 */
class ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_la_pantalla_es_del_administrador_y_aparece_en_el_sidebar(): void
    {
        Configuracion::factory()->create();
        $recorredor = User::factory()->create();

        $this->actingAs($this->admin)->get(route('configuracion'))->assertOk()->assertSee('Configuración');
        $this->actingAs($recorredor)->get(route('configuracion'))->assertForbidden();

        $this->assertContains('configuracion', collect(Modulos::visibles($this->admin))->pluck('nombre'));
        $this->assertNotContains('configuracion', collect(Modulos::visibles($recorredor))->pluck('nombre'));
    }

    public function test_muestra_la_organizacion_los_tipos_y_los_parametros(): void
    {
        Configuracion::factory()->create(['nombre' => 'Estancia La Paz', 'logo_path' => 'logos/paz.png', 'dias_sin_medir' => 10, 'puntos_minimos' => 25, 'area_disco_m2' => 0.25]);
        $alfalfa = TipoPastura::factory()->create([
            'nombre' => 'Alfalfa', 'altura_ingreso_cm' => 22.5, 'altura_salida_cm' => 7,
        ]);
        Potrero::factory()->count(2)->create(['tipo_pastura_id' => $alfalfa->id]);
        TipoPastura::factory()->sinAlturas()->inactivo()->create(['nombre' => 'Sorgo']);

        Livewire::actingAs($this->admin)->test(PantallaConfiguracion::class)
            ->assertSee('Estancia La Paz')
            ->assertSeeHtml('storage/logos/paz.png')
            ->assertSee('Verde')
            ->assertSeeInOrder(['Alfalfa', '22,5 cm', '7,0 cm', '2 potreros', 'Sorgo', 'Sin alturas', 'Inactivo'])
            ->assertDontSee('Sin fórmula')
            ->assertSee('10 días')
            ->assertSee('25 puntos')
            ->assertSee('0,25 m²');
    }

    /** P23: los estados en el orden en que se cargaron, con cuántas curvas y recorridas los usan. */
    public function test_muestra_los_estados_de_la_pastura_con_su_uso(): void
    {
        Configuracion::factory()->create();
        $rebrote = EstadoFenologico::factory()->create(['nombre' => 'Rebrote']);
        EstadoFenologico::factory()->create(['nombre' => 'Pleno crecimiento']);
        CurvaBiomasa::factory()->count(2)->recta(100, 0)->create(['estado_fenologico_id' => $rebrote->id]);

        Livewire::actingAs($this->admin)->test(PantallaConfiguracion::class)
            ->assertSeeHtml('data-bloque-estados')
            ->assertSeeInOrder(['Estados de la pastura', 'Rebrote', '2 curvas · sin recorridas', 'Pleno crecimiento', 'Sin curvas · sin recorridas']);
    }

    public function test_un_estado_sin_uso_se_elimina_y_uno_con_curvas_se_desactiva(): void
    {
        Configuracion::factory()->create();
        $sinUso = EstadoFenologico::factory()->create(['nombre' => 'Floración']);
        $conUso = EstadoFenologico::factory()->create(['nombre' => 'Rebrote']);
        CurvaBiomasa::factory()->recta(100, 0)->create(['estado_fenologico_id' => $conUso->id]);

        Livewire::actingAs($this->admin)->test(PantallaConfiguracion::class)
            ->call('nuevoEstado')->assertSeeLivewire(EstadoFenologicoFormulario::class)
            ->dispatch('cerrar-estado-fenologico')->assertSet('estadoFormulario', null)
            ->call('confirmarBajaEstado', $sinUso->id)
            ->assertSee('¿Eliminar el estado "Floración"?')
            ->call('bajaEstado')
            ->assertDispatched('toast', tipo: 'exito')
            ->call('confirmarBajaEstado', $conUso->id)
            ->assertSee('lo usan curvas o recorridas')
            ->call('bajaEstado')
            ->assertDispatched('toast', tipo: 'aviso');

        $this->assertModelMissing($sinUso);
        $this->assertFalse($conUso->fresh()->activo);

        Livewire::actingAs($this->admin)->test(PantallaConfiguracion::class)->call('reactivarEstado', $conUso->id);
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
            ->assertDispatched('estado-fenologico-guardado')
            ->assertDispatched('cerrar-estado-fenologico');

        $estado = EstadoFenologico::query()->where('nombre', 'Recién pastoreada')->sole();

        Livewire::actingAs($this->admin)->test(EstadoFenologicoFormulario::class, ['estadoId' => $estado->id])
            ->assertSet('nombre', 'Recién pastoreada')
            ->set('nombre', 'Post pastoreo')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Post pastoreo', $estado->fresh()->nombre);
    }

    /** El área del disco se escribe con coma y va entre 0,005 y 1 m². */
    public function test_el_area_del_disco_se_edita_en_parametros(): void
    {
        Configuracion::factory()->create();

        Livewire::actingAs($this->admin)->test(Parametros::class)
            ->assertSet('area_disco_m2', '0,1')
            ->set('area_disco_m2', '2')
            ->call('guardar')
            ->assertHasErrors(['area_disco_m2'])
            ->set('area_disco_m2', '0,25')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(0.25, Configuracion::instancia()->area_disco_m2);
    }

    public function test_abre_los_modales_hijos(): void
    {
        Configuracion::factory()->create();
        $tipo = TipoPastura::factory()->create();

        Livewire::actingAs($this->admin)->test(PantallaConfiguracion::class)
            ->assertDontSeeLivewire(DatosOrganizacion::class)
            ->call('abrirOrganizacion')->assertSet('mostrarOrganizacion', true)->assertSeeLivewire(DatosOrganizacion::class)
            ->dispatch('cerrar-datos-organizacion')->assertSet('mostrarOrganizacion', false)
            ->call('abrirParametros')->assertSet('mostrarParametros', true)->assertSeeLivewire(Parametros::class)
            ->dispatch('cerrar-parametros')->assertSet('mostrarParametros', false)
            ->call('nuevoTipo')->assertSet('tipoFormulario', 0)->assertSeeLivewire(TipoPasturaFormulario::class)
            ->dispatch('cerrar-tipo-pastura')->assertSet('tipoFormulario', null)
            ->call('editarTipo', $tipo->id)->assertSet('tipoFormulario', $tipo->id);
    }

    public function test_un_tipo_sin_potreros_se_elimina_y_uno_con_potreros_se_desactiva(): void
    {
        Configuracion::factory()->create();
        $sinUso = TipoPastura::factory()->create(['nombre' => 'Sorgo']);
        $conUso = TipoPastura::factory()->create(['nombre' => 'Alfalfa']);
        Potrero::factory()->create(['tipo_pastura_id' => $conUso->id]);

        Livewire::actingAs($this->admin)->test(PantallaConfiguracion::class)
            ->call('confirmarBaja', $sinUso->id)
            ->assertSee('¿Eliminar el tipo "Sorgo"?')
            ->call('baja')
            ->assertSet('bajaId', null)
            ->assertDispatched('toast', tipo: 'exito')
            ->call('confirmarBaja', $conUso->id)
            ->assertSee('lo usan potreros')
            ->call('baja')
            ->assertDispatched('toast', tipo: 'aviso');

        $this->assertDatabaseMissing('tipos_pastura', ['id' => $sinUso->id]);
        $this->assertFalse($conUso->fresh()->activo);

        Livewire::actingAs($this->admin)->test(PantallaConfiguracion::class)
            ->call('reactivar', $conUso->id)
            ->assertDispatched('toast', tipo: 'exito');

        $this->assertTrue($conUso->fresh()->activo);
    }

    public function test_con_ver_se_mira_sin_botones_y_las_acciones_se_rechazan(): void
    {
        Configuracion::factory()->create();
        $tipo = TipoPastura::factory()->create();
        $soloVer = User::factory()->conNivel('configuracion', Nivel::Ver)->create();

        Livewire::actingAs($soloVer)->test(PantallaConfiguracion::class)
            ->assertOk()
            ->assertDontSeeHtml('wire:click="abrirOrganizacion"')
            ->assertDontSeeHtml('wire:click="nuevoTipo"')
            ->call('confirmarBaja', $tipo->id)
            ->assertForbidden();
    }
}
