<?php

namespace Tests\Feature\Layout;

use App\Enums\Nivel;
use App\Enums\Rol;
use App\Models\User;
use App\Support\Modulo;
use App\Support\Modulos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModulosTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function nombres(array $modulos): array
    {
        return array_map(fn (Modulo $m) => $m->nombre, $modulos);
    }

    public function test_solo_se_muestran_los_modulos_cuya_ruta_ya_existe(): void
    {
        $lista = [
            new Modulo('tablero', 'dashboard', 'fa-gauge-high'),
            new Modulo('campos', 'ruta.que.no.existe', 'fa-map-location-dot'),
        ];

        $visibles = Modulos::visibles(User::factory()->create(), $lista);

        $this->assertSame(['tablero'], $this->nombres($visibles));
    }

    public function test_un_modulo_se_muestra_solo_con_al_menos_ver(): void
    {
        $lista = [
            new Modulo('tablero', 'dashboard', 'fa-gauge-high'),
            new Modulo('usuarios', 'admin.usuarios', 'fa-user-gear', Nivel::SinAcceso),
        ];

        $admin = User::factory()->create(['rol' => Rol::Admin]);
        $recorredor = User::factory()->create(['rol' => Rol::Recorredor]);
        $recorredorConUsuarios = User::factory()->conNivel('usuarios', Nivel::Ver)->create();

        $this->assertSame(['tablero', 'usuarios'], $this->nombres(Modulos::visibles($admin, $lista)));
        $this->assertSame(['tablero'], $this->nombres(Modulos::visibles($recorredor, $lista)));
        $this->assertSame(['tablero', 'usuarios'], $this->nombres(Modulos::visibles($recorredorConUsuarios, $lista)));
    }

    public function test_sin_usuario_no_se_ve_nada(): void
    {
        $lista = [new Modulo('tablero', 'dashboard', 'fa-gauge-high')];

        $this->assertSame([], Modulos::visibles(null, $lista));
    }

    public function test_la_lista_real_tiene_el_tablero_en_dashboard_y_cada_modulo_su_etiqueta(): void
    {
        $todos = Modulos::todos();

        $tablero = collect($todos)->firstWhere('nombre', 'tablero');
        $this->assertSame('dashboard', $tablero->ruta);

        foreach ($todos as $modulo) {
            $this->assertNotSame('layout.modulos.'.$modulo->nombre, $modulo->etiqueta(), "Falta el texto de {$modulo->nombre} en lang/es/layout.php");
            $this->assertStringStartsWith('fa-', $modulo->icono);
        }

        $this->assertSame(['tablero', 'campos', 'recorridas', 'usuarios'], Modulos::nombres());
        $this->assertSame($this->nombres($todos), Modulos::nombres());
        $this->assertSame('usuarios', Modulos::porNombre('usuarios')?->nombre);
        $this->assertSame('admin.usuarios', Modulos::porNombre('usuarios')->ruta);

        // Los cuatro ya tienen ruta: el administrador los ve todos.
        $admin = User::factory()->admin()->create();
        $this->assertSame(['tablero', 'campos', 'recorridas', 'usuarios'], $this->nombres(Modulos::visibles($admin)));
        $this->assertNull(Modulos::porNombre('inexistente'));
    }

    public function test_el_recorredor_nace_sin_acceso_solo_a_usuarios(): void
    {
        $sinAcceso = collect(Modulos::todos())
            ->filter(fn (Modulo $m) => $m->porOmision(Rol::Recorredor) === Nivel::SinAcceso)
            ->pluck('nombre')->all();

        $this->assertEqualsCanonicalizing(['usuarios'], $sinAcceso);

        foreach (Modulos::todos() as $modulo) {
            $this->assertSame(Nivel::Editar, $modulo->porOmision(Rol::Admin));
        }
    }

    public function test_reportes_y_configuracion_estan_anunciados_y_fuera_de_la_lista_de_modulos(): void
    {
        // D66: se ven en el sidebar apagados, pero no existen para permisos,
        // atajos ni la matriz de usuarios.
        $anunciados = Modulos::anunciados();

        $this->assertSame(['reportes', 'configuracion'], $this->nombres($anunciados));
        $this->assertSame('Reportes: próximamente', $anunciados[0]->titulo());
        $this->assertSame('Configuración: próximamente', $anunciados[1]->titulo());

        foreach ($anunciados as $anunciado) {
            $this->assertTrue($anunciado->anunciado);
            $this->assertFalse($anunciado->tieneRuta());
            $this->assertNotContains($anunciado->nombre, Modulos::nombres());
            $this->assertArrayNotHasKey($anunciado->nombre, Modulos::nivelesPorOmision(Rol::Recorredor));
        }
    }

    public function test_el_sidebar_muestra_el_modulo_anunciado_apagado_y_sin_enlace(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::Recorredor]));

        $respuesta = $this->get('/dashboard');

        $respuesta->assertSee('data-anunciado', false);
        $respuesta->assertSee('Reportes');
        $respuesta->assertSee('Configuración');
        $respuesta->assertSee('Próximamente');
        $respuesta->assertDontSee('href="'.url('/reportes').'"', false);
        $respuesta->assertDontSee('href="'.url('/configuracion').'"', false);
    }

    public function test_el_modulo_esta_activo_en_su_ruta_y_en_las_hijas(): void
    {
        Route::get('/_test/campos/lotes', fn () => 'ok')->name('test.campos.lotes');
        Route::get('/_test/campos/lotes/nueva', fn () => 'ok')->name('test.campos.lotes.nueva');

        $modulo = new Modulo('campos', 'test.campos.lotes', 'fa-map-location-dot');

        $this->get('/_test/campos/lotes/nueva');
        $this->assertTrue($modulo->activo());

        $this->get('/dashboard');
        $this->assertFalse($modulo->activo());
    }

    public function test_el_titulo_del_modulo_incluye_el_atajo_alt_letra_si_lo_tiene(): void
    {
        $this->assertSame('Campos (Alt+C)', (new Modulo('campos', 'campos', 'fa-map-location-dot', tecla: 'c'))->titulo());
        $this->assertSame('Inicio', (new Modulo('tablero', 'dashboard', 'fa-gauge-high'))->titulo());

        // D55: todos los módulos del sistema tienen letra, ninguna repetida,
        // y ninguna es D ni E (barra de direcciones y menú del navegador).
        $teclas = array_map(fn (Modulo $m) => $m->tecla, Modulos::todos());
        $this->assertNotContains(null, $teclas);
        $this->assertSame(count($teclas), count(array_unique($teclas)));
        $this->assertNotContains('d', $teclas);
        $this->assertNotContains('e', $teclas);
    }
}
