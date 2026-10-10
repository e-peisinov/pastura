<?php

namespace Tests\Feature\Roles;

use App\Enums\Nivel;
use App\Livewire\Usuarios\UsuarioFormulario;
use App\Livewire\Usuarios\Usuarios;
use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pasada de solo lectura (D49): con Ver en un módulo, la pantalla entra y
 * se mira, la vista esconde los botones de escritura y cada acción Livewire
 * rechaza en el servidor. Un test por pantalla. El usuario es un
 * administrador recortado a Ver en ese módulo: el rol no lo salva.
 */
class SoloLecturaTest extends TestCase
{
    use RefreshDatabase;

    private function lector(string $modulo): User
    {
        return User::factory()->admin()->conNivel($modulo, Nivel::Ver)->create();
    }

    public function test_tablero_es_igual_con_ver_o_editar(): void
    {
        Configuracion::factory()->create();

        $conVer = $this->actingAs($this->lector('tablero'))->get(route('dashboard'))->assertOk()->getContent();
        $conEditar = $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-tablero', $conVer);
        $this->assertStringContainsString('data-tablero', $conEditar);
        $this->assertStringContainsString(__('tablero.titulo'), $conVer);
    }

    public function test_usuarios_con_ver_lista_sin_crear_editar_ni_dar_de_baja(): void
    {
        $lector = $this->lector('usuarios');
        $otro = User::factory()->create();
        $inactivo = User::factory()->inactivo()->create();

        $this->actingAs($lector)->get(route('admin.usuarios'))->assertOk()
            ->assertSee($otro->name)
            ->assertDontSee('wire:click="nuevo"', false)
            ->assertDontSee('wire:click="editar(', false)
            ->assertDontSee('wire:click="abrirContrasena(', false)
            ->assertDontSee('wire:click="confirmarBaja(', false);

        Livewire::actingAs($lector)->test(Usuarios::class)->call('nuevo')->assertForbidden();
        Livewire::actingAs($lector)->test(Usuarios::class)->call('editar', $otro->id)->assertForbidden();
        Livewire::actingAs($lector)->test(Usuarios::class)->call('abrirContrasena', $otro->id)->assertForbidden();
        Livewire::actingAs($lector)->test(Usuarios::class)->call('confirmarBaja', $otro->id)->assertForbidden();
        Livewire::actingAs($lector)->test(Usuarios::class)->set('bajaId', $otro->id)->call('baja')->assertForbidden();
        Livewire::actingAs($lector)->test(Usuarios::class)->call('reactivar', $inactivo->id)->assertForbidden();
        Livewire::actingAs($lector)->test(UsuarioFormulario::class)->assertForbidden();

        $this->assertTrue($otro->fresh()->activo);
        $this->assertFalse($inactivo->fresh()->activo);
    }
}
