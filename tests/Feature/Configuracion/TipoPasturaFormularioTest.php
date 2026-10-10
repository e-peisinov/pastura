<?php

namespace Tests\Feature\Configuracion;

use App\Livewire\Configuracion\TipoPasturaFormulario;
use App\Models\TipoPastura;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alta y edición de un tipo de pastura (P16): nombre, alturas de referencia
 * (las dos o ninguna, ingreso > salida) y fórmula (A y B, o ninguno).
 */
class TipoPasturaFormularioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_alta_con_alturas_y_formula_acepta_coma_decimal(): void
    {
        Livewire::actingAs($this->admin)->test(TipoPasturaFormulario::class)
            ->set('nombre', ' Alfalfa ')
            ->set('altura_ingreso_cm', '22,5')
            ->set('altura_salida_cm', '7')
            ->set('biomasa_a', '25,5')
            ->set('biomasa_b', '-100')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'exito')
            ->assertDispatched('tipo-pastura-guardado')
            ->assertDispatched('cerrar-tipo-pastura');

        $tipo = TipoPastura::query()->where('nombre', 'Alfalfa')->sole();
        $this->assertSame(22.5, $tipo->altura_ingreso_cm);
        $this->assertSame(7.0, $tipo->altura_salida_cm);
        $this->assertSame(25.5, $tipo->biomasa_a);
        $this->assertSame(-100.0, $tipo->biomasa_b);
        $this->assertTrue($tipo->activo);
    }

    public function test_alta_solo_con_el_nombre(): void
    {
        Livewire::actingAs($this->admin)->test(TipoPasturaFormulario::class)
            ->set('nombre', 'Sorgo')
            ->call('guardar')
            ->assertHasNoErrors();

        $tipo = TipoPastura::query()->where('nombre', 'Sorgo')->sole();
        $this->assertFalse($tipo->tieneAlturas());
        $this->assertFalse($tipo->tieneFormula());
    }

    public function test_valida_nombre_unico_alturas_juntas_e_ingreso_mayor_que_salida(): void
    {
        TipoPastura::factory()->create(['nombre' => 'Alfalfa']);

        Livewire::actingAs($this->admin)->test(TipoPasturaFormulario::class)
            ->set('nombre', 'alfalfa')
            ->set('altura_ingreso_cm', '20')
            ->set('biomasa_b', '50')
            ->call('guardar')
            ->assertHasErrors(['nombre', 'altura_salida_cm', 'biomasa_a'])
            ->set('nombre', 'Festuca')
            ->set('altura_ingreso_cm', '8')
            ->set('altura_salida_cm', '8')
            ->set('biomasa_a', 'mucho')
            ->call('guardar')
            ->assertHasErrors(['altura_ingreso_cm', 'biomasa_a'])
            ->set('altura_ingreso_cm', '1000')
            ->set('altura_salida_cm', '0')
            ->call('guardar')
            ->assertHasErrors(['altura_ingreso_cm', 'altura_salida_cm']);
    }

    public function test_edicion(): void
    {
        $tipo = TipoPastura::factory()->create(['nombre' => 'Festuca', 'altura_ingreso_cm' => 20, 'altura_salida_cm' => 7, 'biomasa_a' => 30, 'biomasa_b' => 0]);

        Livewire::actingAs($this->admin)->test(TipoPasturaFormulario::class, ['tipoId' => $tipo->id])
            ->assertSet('nombre', 'Festuca')
            ->assertSet('altura_ingreso_cm', '20')
            ->assertSet('biomasa_a', '30')
            ->set('altura_ingreso_cm', '18')
            ->set('biomasa_a', '')
            ->set('biomasa_b', '')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'exito');

        $tipo->refresh();
        $this->assertSame(18.0, $tipo->altura_ingreso_cm);
        $this->assertFalse($tipo->tieneFormula());
    }

    public function test_cerrar_con_cambios_pide_confirmacion(): void
    {
        Livewire::actingAs($this->admin)->test(TipoPasturaFormulario::class)
            ->call('intentarCerrar')
            ->assertDispatched('cerrar-tipo-pastura')
            ->set('nombre', 'Moha')
            ->call('intentarCerrar')
            ->assertSet('confirmandoDescarte', true);
    }

    public function test_solo_con_editar_en_configuracion(): void
    {
        Livewire::actingAs(User::factory()->create())->test(TipoPasturaFormulario::class)->assertForbidden();
    }
}
