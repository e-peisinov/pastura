<?php

namespace Tests\Feature\Instrucciones;

use App\Enums\Nivel;
use App\Livewire\Instrucciones\Instrucciones;
use App\Models\User;
use App\Support\Modulos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Instrucciones (P25): cómo funciona el sistema. La ve todo usuario con
 * sesión, sin permiso de la matriz. Cada sección del panel aparece si el
 * usuario puede ver ese módulo; el administrador ve todas.
 */
class InstruccionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ve_todo_usuario_con_sesion_y_esta_en_el_sidebar(): void
    {
        $sinNada = User::factory()->create();
        foreach (Modulos::nombres() as $modulo) {
            $sinNada->asignarNivel($modulo, Nivel::SinAcceso);
        }

        $this->actingAs($sinNada)->get(route('instrucciones'))
            ->assertOk()
            ->assertSee('Instrucciones')
            ->assertSeeHtml('data-modulo="instrucciones"');

        $this->assertContains('instrucciones', collect(Modulos::visibles($sinNada->fresh()))->pluck('nombre'));
    }

    public function test_sin_sesion_va_al_ingreso(): void
    {
        $this->get(route('instrucciones'))->assertRedirect(route('login'));
    }

    /** No tiene permisos: no es una fila de la matriz de usuarios. */
    public function test_no_esta_en_la_matriz_de_permisos(): void
    {
        $this->assertNotContains('instrucciones', Modulos::nombres());
    }

    public function test_el_administrador_ve_todas_las_secciones(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(Instrucciones::class)
            ->assertSeeHtml('id="como-funciona"')
            ->assertSeeHtml('id="instalar"')
            ->assertSeeHtml('id="recorrida"')
            ->assertSeeHtml('id="calibrar"')
            ->assertSeeHtml('id="senal"')
            ->assertSeeHtml('id="curvas"')
            ->assertSeeHtml('id="campos"')
            ->assertSeeHtml('id="panel-recorridas"')
            ->assertSeeHtml('id="reportes"')
            ->assertSeeHtml('id="usuarios"')
            ->assertSeeHtml('id="configuracion"')
            ->assertSeeHtml('id="auditoria"')
            ->assertSeeHtml('id="problemas"');
    }

    /** El recorredor nace sin acceso a reportes, usuarios, configuración y auditoría. */
    public function test_el_recorredor_ve_lo_del_campo_y_lo_que_puede_abrir(): void
    {
        $recorredor = User::factory()->create();

        Livewire::actingAs($recorredor)->test(Instrucciones::class)
            ->assertSeeHtml('id="instalar"')
            ->assertSeeHtml('id="senal"')
            ->assertSeeHtml('id="curvas"')
            ->assertSeeHtml('id="panel-recorridas"')
            ->assertSeeHtml('id="problemas"')
            ->assertDontSeeHtml('id="reportes"')
            ->assertDontSeeHtml('id="usuarios"')
            ->assertDontSeeHtml('id="configuracion"')
            ->assertDontSeeHtml('id="auditoria"');
    }

    /** El índice lleva a cada sección que se muestra, y solo a esas. */
    public function test_el_indice_enlaza_las_secciones_visibles(): void
    {
        $recorredor = User::factory()->create();

        Livewire::actingAs($recorredor)->test(Instrucciones::class)
            ->assertSeeHtml('href="#instalar"')
            ->assertDontSeeHtml('href="#usuarios"');
    }
}
