<?php

namespace Tests\Feature\Configuracion;

use App\Enums\Nivel;
use App\Livewire\Configuracion\Configuracion as PantallaConfiguracion;
use App\Livewire\Configuracion\DatosOrganizacion;
use App\Livewire\Configuracion\Parametros;
use App\Livewire\Configuracion\TipoPasturaFormulario;
use App\Models\Configuracion;
use App\Models\Potrero;
use App\Models\TipoPastura;
use App\Models\User;
use App\Support\Modulos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Configuración (P17): la organización, los tipos de pastura (P16) y los
 * parámetros. Cada bloque se edita en su modal hijo; la baja y la
 * reactivación de un tipo viven en el padre. Los estados de la pastura y el
 * área del disco están en Curvas (P24, EstadosFenologicosTest).
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
        Configuracion::factory()->create(['nombre' => 'Estancia La Paz', 'logo_path' => 'logos/paz.png', 'dias_sin_medir' => 10, 'puntos_minimos' => 25]);
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
            ->assertSee('25 puntos');
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
