<?php

namespace Tests\Feature\Configuracion;

use App\Livewire\Configuracion\Parametros;
use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Parámetros del seguimiento (P17): días para quedar sin medir y puntos
 * mínimos por recorrida.
 */
class ParametrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_fila_arranca_con_los_valores_de_instalacion(): void
    {
        $this->assertSame(7, Configuracion::instancia()->dias_sin_medir);
        $this->assertSame(20, Configuracion::instancia()->puntos_minimos);
    }

    public function test_carga_y_guarda(): void
    {
        Configuracion::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())->test(Parametros::class)
            ->assertSet('dias_sin_medir', '7')
            ->assertSet('puntos_minimos', '20')
            ->set('dias_sin_medir', '10')
            ->set('puntos_minimos', '30')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'exito')
            ->assertDispatched('parametros-guardados')
            ->assertDispatched('cerrar-parametros');

        $this->assertSame(10, Configuracion::instancia()->dias_sin_medir);
        $this->assertSame(30, Configuracion::instancia()->puntos_minimos);
    }

    public function test_valida_rangos(): void
    {
        Configuracion::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())->test(Parametros::class)
            ->set('dias_sin_medir', '0')
            ->set('puntos_minimos', 'muchos')
            ->call('guardar')
            ->assertHasErrors(['dias_sin_medir' => 'min', 'puntos_minimos' => 'integer'])
            ->set('dias_sin_medir', '366')
            ->set('puntos_minimos', '1001')
            ->call('guardar')
            ->assertHasErrors(['dias_sin_medir' => 'max', 'puntos_minimos' => 'max']);
    }

    public function test_solo_con_editar_en_configuracion(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Parametros::class)->assertForbidden();
    }
}
