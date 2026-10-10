<?php

namespace Tests\Feature\Roles;

use App\Enums\Nivel;
use App\Enums\Rol;
use App\Models\User;
use App\Support\Modulos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Permisos por módulo y por usuario (D49): el rol carga la matriz por
 * omisión, la fila del usuario manda, los Gates puedeVer / puedeEditar y el
 * middleware `modulo:<nombre>` leen de ahí, y un usuario dado de baja no
 * pasa ninguno.
 */
class PermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'modulo:usuarios'])
            ->get('/_test/usuarios', fn () => 'ok')
            ->name('test.usuarios');
    }

    public function test_el_administrador_nace_con_editar_en_todo(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (Modulos::nombres() as $modulo) {
            $this->assertSame(Nivel::Editar, $admin->nivel($modulo), $modulo);
        }
    }

    public function test_el_recorredor_nace_con_la_matriz_de_dominio_md(): void
    {
        $recorredor = User::factory()->create();

        $esperado = [
            'tablero' => Nivel::Ver, 'campos' => Nivel::Ver,
            'recorridas' => Nivel::Editar, 'usuarios' => Nivel::SinAcceso,
            'configuracion' => Nivel::SinAcceso,
        ];

        $this->assertSame($esperado, $recorredor->niveles());
        $this->assertSame($esperado, Modulos::nivelesPorOmision(Rol::Recorredor));
    }

    public function test_la_fila_del_usuario_manda_sobre_el_default_del_rol(): void
    {
        $admin = User::factory()->admin()->conNivel('usuarios', Nivel::Ver)->create();
        $recorredor = User::factory()->conNivel('usuarios', Nivel::Editar)->create();

        $this->assertSame(Nivel::Ver, $admin->nivel('usuarios'));
        $this->assertTrue($admin->puedeVer('usuarios'));
        $this->assertFalse($admin->puedeEditar('usuarios'));

        $this->assertTrue($recorredor->puedeEditar('usuarios'));

        // Recortar al administrador también vale (D49).
        $admin->asignarNivel('usuarios', Nivel::SinAcceso);
        $this->assertFalse($admin->fresh()->puedeVer('usuarios'));
    }

    public function test_un_modulo_desconocido_es_sin_acceso(): void
    {
        $this->assertSame(Nivel::SinAcceso, User::factory()->admin()->create()->nivel('inexistente'));
    }

    public function test_los_gates_puede_ver_y_puede_editar_reciben_el_modulo(): void
    {
        $admin = User::factory()->admin()->create();
        $recorredor = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('puedeEditar', 'usuarios'));
        // Sube y corrige sus recorridas (dominio.md, roles); los campos solo los mira.
        $this->assertTrue(Gate::forUser($recorredor)->allows('puedeEditar', 'recorridas'));
        $this->assertTrue(Gate::forUser($recorredor)->allows('puedeVer', 'campos'));
        $this->assertFalse(Gate::forUser($recorredor)->allows('puedeEditar', 'campos'));
        $this->assertFalse(Gate::forUser($recorredor)->allows('puedeVer', 'usuarios'));
        $this->assertTrue($recorredor->can('puedeVer', 'campos'));
    }

    public function test_un_usuario_dado_de_baja_no_pasa_ningun_gate(): void
    {
        $admin = User::factory()->admin()->inactivo()->create();

        $this->assertFalse($admin->puedeVer('tablero'));
        $this->assertFalse($admin->puedeEditar('usuarios'));
        $this->assertSame([], Modulos::visibles($admin));
    }

    public function test_el_middleware_modulo_deja_pasar_con_ver_y_rechaza_sin_acceso(): void
    {
        $this->get('/_test/usuarios')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->admin()->create())->get('/_test/usuarios')->assertOk()->assertSee('ok');
        $this->actingAs(User::factory()->conNivel('usuarios', Nivel::Ver)->create())->get('/_test/usuarios')->assertOk();
        $this->actingAs(User::factory()->create())->get('/_test/usuarios')->assertForbidden();
        $this->actingAs(User::factory()->admin()->conNivel('usuarios', Nivel::SinAcceso)->create())->get('/_test/usuarios')->assertForbidden();
    }

    public function test_las_rutas_del_sistema_bloquean_al_que_no_tiene_acceso(): void
    {
        $recorredor = User::factory()->create();

        $this->actingAs($recorredor)->get(route('admin.usuarios'))->assertForbidden();
        $this->actingAs($recorredor)->get(route('dashboard'))->assertOk();

        $recortado = User::factory()->admin()->conNivel('tablero', Nivel::SinAcceso)->create();
        $this->actingAs($recortado)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($recortado)->get(route('admin.usuarios'))->assertOk();
    }

    public function test_el_sidebar_muestra_solo_los_modulos_con_al_menos_ver(): void
    {
        $conUsuarios = User::factory()->conNivel('usuarios', Nivel::Ver)->create();
        $html = $this->actingAs($conUsuarios)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-modulo="usuarios"', $html);
        $this->assertStringContainsString('data-modulo="tablero"', $html);

        $recorredor = User::factory()->create();
        $html = $this->actingAs($recorredor)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-modulo="tablero"', $html);
        $this->assertStringNotContainsString('data-modulo="usuarios"', $html);

        $sinTablero = User::factory()->admin()->conNivel('tablero', Nivel::SinAcceso)->create();
        $html = $this->actingAs($sinTablero)->get(route('admin.usuarios'))->assertOk()->getContent();

        $this->assertStringContainsString('data-modulo="usuarios"', $html);
        $this->assertStringNotContainsString('data-modulo="tablero"', $html);
    }

    public function test_un_usuario_dado_de_baja_pierde_la_sesion_y_no_puede_ingresar(): void
    {
        $usuario = User::factory()->admin()->inactivo()->create(['email' => 'baja@pasturometro.test']);

        $this->actingAs($usuario)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login'), ['email' => 'baja@pasturometro.test', 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_el_scope_con_editar_en_encuentra_por_fila_o_por_rol(): void
    {
        $adminSinFila = User::factory()->admin()->create();
        $adminRecortado = User::factory()->admin()->conNivel('usuarios', Nivel::Ver)->create();
        $recorredorConFila = User::factory()->conNivel('usuarios', Nivel::Editar)->create();
        $recorredor = User::factory()->create();
        $adminInactivo = User::factory()->admin()->inactivo()->create();

        $ids = User::query()->conEditarEn('usuarios')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$adminSinFila->id, $recorredorConFila->id, $adminInactivo->id], $ids);
        $this->assertNotContains($adminRecortado->id, $ids);
        $this->assertNotContains($recorredor->id, $ids);

        $this->assertTrue(User::otroPuedeEditarUsuarios($adminSinFila->id));
        $this->assertTrue(User::otroPuedeEditarUsuarios($recorredorConFila->id));
        $recorredorConFila->delete();
        $this->assertFalse(User::otroPuedeEditarUsuarios($adminSinFila->id));
    }
}
