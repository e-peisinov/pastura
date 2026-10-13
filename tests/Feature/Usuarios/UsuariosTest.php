<?php

namespace Tests\Feature\Usuarios;

use App\Enums\Nivel;
use App\Livewire\Usuarios\Usuarios;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de Usuarios (D49): listado con resumen de permisos, cambio de
 * contraseña desde el listado, baja lógica con sus guardas y reactivación.
 */
class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Marta Ledesma']);
    }

    public function test_la_ruta_lista_los_usuarios_con_rol_resumen_y_estado(): void
    {
        User::factory()->create(['name' => 'Marcos Paz', 'email' => 'marcos@geograss.test']);
        User::factory()->admin()->inactivo()->create(['name' => 'Viejo Admin']);

        $this->actingAs($this->admin)->get(route('admin.usuarios'))
            ->assertOk()
            ->assertSeeInOrder(['Marcos Paz', 'marcos@geograss.test', 'Recorredor'])
            ->assertSee('Marta Ledesma')->assertSee('Usted')
            ->assertSee('Edita 7 módulos')
            ->assertSee('Edita 1 módulo · ve 2 · sin acceso a 4')
            ->assertDontSee('Viejo Admin');

        Livewire::actingAs($this->admin)->test(Usuarios::class)
            ->set('filtroEstado', 'inactivos')
            ->assertSee('Viejo Admin')->assertDontSee('Marcos Paz')
            ->assertSeeHtml('wire:click="reactivar(');
    }

    public function test_ordena_por_columna_desde_la_cabecera(): void
    {
        User::factory()->create(['name' => 'Zoe Vega']);
        User::factory()->create(['name' => 'Ana Torres']);

        $componente = Livewire::actingAs($this->admin)->test(Usuarios::class)
            // Abre por nombre asc (con los activos siempre arriba).
            ->assertSet('ordenColumna', 'usuario')->assertSet('ordenDireccion', 'asc')
            ->assertSeeInOrder(['Ana Torres', 'Marta Ledesma', 'Zoe Vega'])
            ->assertSeeHtml('aria-sort="ascending"')
            ->call('ordenarPor', 'usuario')->assertSet('ordenDireccion', 'desc')
            ->assertSeeInOrder(['Zoe Vega', 'Marta Ledesma', 'Ana Torres'])
            ->assertSeeHtml('aria-sort="descending"')
            // Otra columna arranca en asc: admin antes que recorredor, y a igual valor desempata el id.
            ->call('ordenarPor', 'rol')->assertSet('ordenColumna', 'rol')->assertSet('ordenDireccion', 'asc')
            ->assertSeeInOrder(['Marta Ledesma', 'Zoe Vega', 'Ana Torres'])
            ->call('ordenarPor', 'estado')
            ->assertSeeInOrder(['Marta Ledesma', 'Zoe Vega', 'Ana Torres']);

        // Una clave que no está en el mapa no cambia nada.
        $componente->call('ordenarPor', 'password')
            ->assertSet('ordenColumna', 'estado')->assertSet('ordenDireccion', 'asc')
            ->assertSeeInOrder(['Marta Ledesma', 'Zoe Vega', 'Ana Torres']);
    }

    public function test_abre_el_alta_y_la_edicion_en_el_hijo(): void
    {
        $otro = User::factory()->create();

        Livewire::actingAs($this->admin)->test(Usuarios::class)
            ->call('nuevo')->assertSet('formulario', 0)
            ->call('cerrarUsuarioFormulario')->assertSet('formulario', null)
            ->call('editar', $otro->id)->assertSet('formulario', $otro->id);
    }

    public function test_cambia_la_contrasena_desde_el_listado(): void
    {
        $otro = User::factory()->create();

        Livewire::actingAs($this->admin)->test(Usuarios::class)
            ->call('abrirContrasena', $otro->id)->assertSet('contrasenaId', $otro->id)
            ->assertSeeHtml('data-formulario-contrasena')
            ->set('contrasena', 'corta')->set('contrasena_confirmation', 'corta')
            ->call('guardarContrasena')->assertHasErrors(['contrasena'])
            ->set('contrasena', 'nueva-clave-123')->set('contrasena_confirmation', 'otra')
            ->call('guardarContrasena')->assertHasErrors(['contrasena' => 'confirmed'])
            ->set('contrasena_confirmation', 'nueva-clave-123')
            ->call('guardarContrasena')->assertHasNoErrors()
            ->assertSet('contrasenaId', null)
            ->assertDispatched('toast', tipo: 'exito');

        $this->assertTrue(Hash::check('nueva-clave-123', $otro->fresh()->password));
    }

    public function test_cerrar_la_contrasena_con_cambios_pide_confirmacion(): void
    {
        $otro = User::factory()->create();

        Livewire::actingAs($this->admin)->test(Usuarios::class)
            ->call('abrirContrasena', $otro->id)
            ->call('intentarCerrar')->assertSet('contrasenaId', null)
            ->call('abrirContrasena', $otro->id)
            ->set('contrasena', 'algo')
            ->call('intentarCerrar')->assertSet('confirmandoDescarte', true)->assertSet('contrasenaId', $otro->id)
            ->call('descartarCambios')->assertSet('contrasenaId', null);
    }

    public function test_baja_logica_y_reactivacion(): void
    {
        $otro = User::factory()->create(['name' => 'Marcos Paz']);

        $componente = Livewire::actingAs($this->admin)->test(Usuarios::class)
            ->call('confirmarBaja', $otro->id)->assertSet('bajaId', $otro->id)
            ->assertSee('dar de baja a Marcos Paz')
            ->call('baja')->assertSet('bajaId', null)->assertDispatched('toast', tipo: 'aviso');

        $this->assertFalse($otro->fresh()->activo);

        $componente->call('reactivar', $otro->id)->assertDispatched('toast', tipo: 'exito');
        $this->assertTrue($otro->fresh()->activo);
    }

    public function test_nadie_se_da_de_baja_a_si_mismo(): void
    {
        Livewire::actingAs($this->admin)->test(Usuarios::class)
            ->assertDontSeeHtml('wire:click="confirmarBaja('.$this->admin->id.')"')
            ->call('confirmarBaja', $this->admin->id)->assertSet('bajaId', null)
            ->assertDispatched('toast', tipo: 'aviso');

        $this->assertTrue($this->admin->fresh()->activo);
    }

    public function test_un_recorredor_con_editar_en_usuarios_opera_la_pantalla_y_puede_dar_de_baja_al_administrador(): void
    {
        // El permiso es la matriz, no el rol (D49): un recorredor con Editar en
        // Usuarios da de baja al administrador porque él mismo sigue activo
        // con Editar, así que la guarda del último editor no salta.
        $operador = User::factory()->conNivel('usuarios', Nivel::Editar)->create();

        Livewire::actingAs($operador)->test(Usuarios::class)
            ->assertSeeHtml('wire:click="nuevo"')
            ->call('confirmarBaja', $this->admin->id)->call('baja')->assertDispatched('toast', tipo: 'aviso');

        $this->assertFalse($this->admin->fresh()->activo);
        $this->assertTrue(User::otroPuedeEditarUsuarios($this->admin->id));
        $this->assertFalse(User::otroPuedeEditarUsuarios($operador->id));
    }

    public function test_con_ver_se_mira_el_listado_sin_botones_y_las_acciones_rechazan(): void
    {
        $lector = User::factory()->conNivel('usuarios', Nivel::Ver)->create();
        $otro = User::factory()->create();

        $this->actingAs($lector)->get(route('admin.usuarios'))->assertOk();

        Livewire::actingAs($lector)->test(Usuarios::class)
            ->assertSee($otro->name)
            ->assertDontSeeHtml('wire:click="nuevo"')
            ->assertDontSeeHtml('wire:click="editar(')
            ->assertDontSeeHtml('wire:click="abrirContrasena(')
            ->assertDontSeeHtml('wire:click="confirmarBaja(');

        Livewire::actingAs($lector)->test(Usuarios::class)->call('nuevo')->assertForbidden();
        Livewire::actingAs($lector)->test(Usuarios::class)->call('editar', $otro->id)->assertForbidden();
        Livewire::actingAs($lector)->test(Usuarios::class)->call('abrirContrasena', $otro->id)->assertForbidden();
        Livewire::actingAs($lector)->test(Usuarios::class)->call('confirmarBaja', $otro->id)->assertForbidden();
        Livewire::actingAs($lector)->test(Usuarios::class)->call('reactivar', $otro->id)->assertForbidden();

        $this->actingAs(User::factory()->create())->get(route('admin.usuarios'))->assertForbidden();
    }
}
