<?php

namespace Tests\Feature\Usuarios;

use App\Enums\Nivel;
use App\Enums\Rol;
use App\Livewire\Usuarios\UsuarioFormulario;
use App\Models\User;
use App\Support\Modulos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alta y edición de un usuario con su matriz de permisos (D49): el rol carga
 * la matriz por omisión, se ajusta módulo por módulo y se guarda entera; los
 * nuevos nacen con el mail verificado; la guarda del último editor.
 */
class UsuarioFormularioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_el_alta_carga_la_matriz_del_rol_y_la_guarda_con_el_mail_verificado(): void
    {
        $componente = Livewire::actingAs($this->admin)->test(UsuarioFormulario::class)
            ->assertSet('rol', 'recorredor')
            ->assertSet('permisos.usuarios', 'sin_acceso')
            ->assertSet('permisos.campos', 'ver')
            ->assertSeeHtml('data-matriz-permisos')
            ->assertSeeHtml('data-permiso="usuarios"')
            ->assertSeeHtml('data-permiso="tablero"')
            // Los módulos sin ruta todavía no se muestran en la matriz.
            ->assertDontSeeHtml('data-permiso="recorridas"')
            ->set('rol', 'admin')
            ->assertSet('permisos.usuarios', 'editar')
            ->set('rol', 'recorredor')
            ->assertSet('permisos.usuarios', 'sin_acceso')
            ->set('nombre', ' Marcos Paz ')
            ->set('email', 'Marcos@Pasturometro.test')
            ->set('contrasena', 'clave-segura-1')
            ->set('contrasena_confirmation', 'clave-segura-1')
            ->set('permisos.usuarios', 'ver')
            ->set('permisos.recorridas', 'editar')
            ->set('permisos.tablero', 'sin_acceso')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'exito')
            ->assertDispatched('usuario-guardado')
            ->assertDispatched('cerrar-usuario-formulario');

        $usuario = User::query()->where('email', 'marcos@pasturometro.test')->firstOrFail();
        $this->assertSame('Marcos Paz', $usuario->name);
        $this->assertSame(Rol::Recorredor, $usuario->rol);
        $this->assertTrue($usuario->activo);
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertTrue(Hash::check('clave-segura-1', $usuario->password));

        // La matriz entera queda escrita, módulo por módulo, incluidos los que no tienen ruta.
        $this->assertCount(count(Modulos::nombres()), $usuario->permisos);
        $this->assertSame(Nivel::Ver, $usuario->nivel('usuarios'));
        $this->assertSame(Nivel::Editar, $usuario->nivel('recorridas'));
        $this->assertSame(Nivel::Ver, $usuario->nivel('campos'));
        $this->assertSame(Nivel::SinAcceso, $usuario->nivel('tablero'));
        $this->assertTrue($usuario->puedeVer('usuarios'));
        $this->assertFalse($usuario->puedeEditar('usuarios'));

        $this->actingAs($usuario)->get(route('admin.usuarios'))->assertOk();
        $this->actingAs($usuario)->get(route('dashboard'))->assertForbidden();
    }

    public function test_valida_nombre_email_unico_rol_contrasena_y_niveles(): void
    {
        User::factory()->create(['email' => 'repetido@pasturometro.test']);

        Livewire::actingAs($this->admin)->test(UsuarioFormulario::class)
            ->set('nombre', '')
            ->set('email', 'repetido@pasturometro.test')
            ->set('contrasena', 'corta')
            ->set('contrasena_confirmation', 'otra')
            ->set('permisos.campos', 'cualquiera')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required', 'email' => 'unique', 'contrasena', 'permisos.campos'])
            ->set('email', 'no-es-un-mail')
            ->call('guardar')
            ->assertHasErrors(['email' => 'email']);

        $this->assertSame(1, User::query()->where('email', 'repetido@pasturometro.test')->count());
    }

    public function test_la_edicion_carga_los_niveles_efectivos_y_no_pide_contrasena(): void
    {
        $usuario = User::factory()->conNivel('usuarios', Nivel::Ver)->create(['name' => 'Marcos', 'email' => 'marcos@pasturometro.test']);

        Livewire::actingAs($this->admin)->test(UsuarioFormulario::class, ['usuarioId' => $usuario->id])
            ->assertSet('nombre', 'Marcos')
            ->assertSet('email', 'marcos@pasturometro.test')
            ->assertSet('rol', 'recorredor')
            ->assertSet('permisos.usuarios', 'ver')
            ->assertSet('permisos.recorridas', 'ver')
            ->assertDontSeeHtml('wire:model="contrasena"')
            ->set('nombre', 'Marcos Paz')
            ->set('permisos.usuarios', 'editar')
            ->set('permisos.recorridas', 'sin_acceso')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'exito');

        $usuario->refresh();
        $this->assertSame('Marcos Paz', $usuario->name);
        $this->assertSame(Nivel::Editar, $usuario->nivel('usuarios'));
        $this->assertSame(Nivel::SinAcceso, $usuario->nivel('recorridas'));
        $this->assertTrue(Hash::check('password', $usuario->password));

        // El mismo mail propio no choca con la regla de unicidad.
        Livewire::actingAs($this->admin)->test(UsuarioFormulario::class, ['usuarioId' => $usuario->id])
            ->call('guardar')->assertHasNoErrors();
    }

    public function test_al_administrador_tambien_se_lo_puede_recortar(): void
    {
        $otroAdmin = User::factory()->admin()->create();

        Livewire::actingAs($this->admin)->test(UsuarioFormulario::class, ['usuarioId' => $otroAdmin->id])
            ->set('permisos.tablero', 'sin_acceso')
            ->set('permisos.usuarios', 'ver')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertFalse($otroAdmin->fresh()->puedeVer('tablero'));
        $this->assertFalse($otroAdmin->fresh()->puedeEditar('usuarios'));
        $this->actingAs($otroAdmin->fresh())->get(route('dashboard'))->assertForbidden();
    }

    public function test_no_se_le_quita_editar_en_usuarios_al_ultimo_editor_activo(): void
    {
        // El administrador que opera es el único activo con Editar en Usuarios.
        Livewire::actingAs($this->admin)->test(UsuarioFormulario::class, ['usuarioId' => $this->admin->id])
            ->set('permisos.usuarios', 'ver')
            ->call('guardar')
            ->assertHasErrors(['permisos.usuarios'])
            ->assertSee(__('usuarios.validacion.ultimo_editor'))
            ->assertDispatched('toast', tipo: 'error')
            ->assertNotDispatched('usuario-guardado');

        $this->assertTrue($this->admin->fresh()->puedeEditar('usuarios'));

        // Con otro editor activo, sí.
        User::factory()->admin()->create();
        Livewire::actingAs($this->admin)->test(UsuarioFormulario::class, ['usuarioId' => $this->admin->id])
            ->set('permisos.usuarios', 'ver')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertFalse($this->admin->fresh()->puedeEditar('usuarios'));
    }

    public function test_cerrar_con_cambios_pide_confirmacion(): void
    {
        Livewire::actingAs($this->admin)->test(UsuarioFormulario::class)
            ->call('intentarCerrar')
            ->assertDispatched('cerrar-usuario-formulario')
            ->set('permisos.campos', 'editar')
            ->call('intentarCerrar')
            ->assertSet('confirmandoDescarte', true);
    }

    public function test_solo_con_editar_en_usuarios(): void
    {
        Livewire::actingAs(User::factory()->conNivel('usuarios', Nivel::Ver)->create())->test(UsuarioFormulario::class)->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(UsuarioFormulario::class)->assertForbidden();
    }
}
