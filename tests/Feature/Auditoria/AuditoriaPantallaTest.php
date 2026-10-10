<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Nivel;
use App\Enums\Rol;
use App\Livewire\Auditoria\Auditoria as PantallaAuditoria;
use App\Livewire\Recorridas\RecorridaDetalle;
use App\Livewire\Usuarios\UsuarioFormulario;
use App\Livewire\Usuarios\Usuarios;
use App\Models\Auditoria;
use App\Models\Campo;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de Auditoría (P18) y las filas que dejan las acciones de
 * verdad: desde las pantallas del panel y desde la API de la app.
 */
class AuditoriaPantallaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $juan;

    private Potrero $lote4;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 15:00:00');
        $this->admin = User::factory()->admin()->create(['name' => 'Marta Admin']);
        $this->juan = User::factory()->create(['name' => 'Juan Pérez', 'email' => 'juan@pastura.test', 'rol' => Rol::Recorredor]);
        $this->lote4 = Potrero::factory()->for(Campo::factory()->create(['nombre' => 'La Esperanza']))->create(['nombre' => 'Lote 4']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function recorrida(): Recorrida
    {
        return Recorrida::factory()->for($this->lote4)->for($this->juan)->create(['iniciada_en' => '2026-10-05 08:00:00']);
    }

    public function test_es_del_administrador_aparece_en_el_sidebar_y_con_ver_alcanza(): void
    {
        $this->actingAs($this->admin)->get(route('admin.auditoria'))->assertOk()->assertSee('Auditoría');
        $this->actingAs($this->juan)->get(route('admin.auditoria'))->assertForbidden();

        $soloVer = User::factory()->conNivel('auditoria', Nivel::Ver)->create();
        $this->actingAs($soloVer)->get(route('admin.auditoria'))->assertOk();
    }

    public function test_anular_desde_la_ficha_deja_la_fila_y_se_lee_en_la_pantalla(): void
    {
        $recorrida = $this->recorrida();

        Livewire::actingAs($this->admin)->test(RecorridaDetalle::class, ['recorridaId' => $recorrida->id])
            ->call('confirmarAnular')
            ->call('anular');

        Livewire::actingAs($this->admin)->test(PantallaAuditoria::class)
            ->assertSeeInOrder(['10/10/2026', '15:00', 'Marta Admin', 'Cambio', 'Recorrida',
                'Modificó la recorrida de La Esperanza · Lote 4 del 05/10/2026 (Juan Pérez): estado de válida a anulada.'])
            ->assertSeeHtml('data-registro="recorrida-modificado"')
            ->assertSeeHtml(e(route('recorridas', ['potrero' => $this->lote4->id, 'desde' => '2026-10-05', 'hasta' => '2026-10-05', 'estado' => 'todas'])));
    }

    public function test_contrasena_y_permisos_desde_las_pantallas_de_usuarios(): void
    {
        Livewire::actingAs($this->admin)->test(Usuarios::class)
            ->call('abrirContrasena', $this->juan->id)
            ->set('contrasena', 'clave-nueva-1')
            ->set('contrasena_confirmation', 'clave-nueva-1')
            ->call('guardarContrasena')
            ->assertHasNoErrors();

        Livewire::actingAs($this->admin)->test(UsuarioFormulario::class, ['usuarioId' => $this->juan->id])
            ->set('permisos.campos', 'editar')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame([
            'Modificó el usuario Juan Pérez (juan@pastura.test): contraseña nueva.',
            'Modificó el usuario Juan Pérez (juan@pastura.test): permiso en Campos de Ver a Editar.',
        ], Auditoria::query()->orderBy('id')->get()->map->descripcion()->all());
    }

    public function test_el_alta_de_un_usuario_no_es_un_cambio_de_permisos(): void
    {
        Livewire::actingAs($this->admin)->test(UsuarioFormulario::class)
            ->set('nombre', 'Marcos Paz')
            ->set('email', 'marcos@pastura.test')
            ->set('contrasena', 'clave-segura-1')
            ->set('contrasena_confirmation', 'clave-segura-1')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(0, Auditoria::query()->count());
    }

    public function test_mover_de_potrero_desde_la_app_queda_a_nombre_del_dueno_del_token(): void
    {
        $recorrida = $this->recorrida();
        $bajo = Potrero::factory()->for($this->lote4->campo)->create(['nombre' => 'El Bajo']);
        Sanctum::actingAs($this->juan);

        $this->putJson('/api/v1/recorridas/'.$recorrida->uuid, [
            'potrero_id' => $bajo->id,
            'iniciada_en' => '2026-10-05T08:00:00-03:00',
            'mediciones' => [],
        ])->assertOk();

        $fila = Auditoria::query()->sole();
        $this->assertSame($this->juan->id, $fila->user_id);
        $this->assertStringContainsString('potrero de La Esperanza · Lote 4 a La Esperanza · El Bajo', $fila->descripcion());
    }

    public function test_periodo_y_filtros(): void
    {
        $this->actingAs($this->admin);
        $vieja = $this->recorrida();
        Carbon::setTestNow('2026-08-01 10:00:00');
        $vieja->update(['activo' => false]);
        Carbon::setTestNow('2026-10-10 15:00:00');
        $this->juan->update(['activo' => false]);
        Potrero::factory()->for($this->lote4->campo)->create(['nombre' => 'Viejo'])->delete();

        Livewire::actingAs($this->admin)->test(PantallaAuditoria::class)
            ->assertSet('desde', '2026-09-10')
            ->assertSet('hasta', '2026-10-10')
            ->assertDontSee('estado de válida a anulada')
            ->assertSee('Modificó el usuario Juan Pérez')
            ->assertSee('Eliminó el potrero La Esperanza · Viejo.')
            ->set('filtroEntidad', 'potrero')
            ->assertDontSee('Modificó el usuario')->assertSee('Eliminó el potrero')
            ->set('filtroEntidad', null)
            ->set('filtroAccion', 'modificado')
            ->assertSee('Modificó el usuario')->assertDontSee('Eliminó el potrero')
            ->call('limpiarFiltros')
            ->set('filtroUsuario', $this->juan->id)
            ->assertSee(__('auditoria.sin_resultados'))
            ->call('limpiarFiltros')
            ->set('desde', '2026-08-01')
            ->assertSee('estado de válida a anulada');
    }

    public function test_sin_acciones_en_el_periodo(): void
    {
        Livewire::actingAs($this->admin)->test(PantallaAuditoria::class)
            ->assertSee(__('auditoria.sin_registros'));
    }
}
