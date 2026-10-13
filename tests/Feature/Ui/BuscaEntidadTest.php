<?php

namespace Tests\Feature\Ui;

use App\Livewire\Traits\BuscaEntidad;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class BuscaEntidadTest extends TestCase
{
    use RefreshDatabase;

    private function componente(): Component
    {
        return new class extends Component
        {
            use BuscaEntidad;

            protected function consultaBusqueda(string $texto): Builder
            {
                return User::query()
                    ->where('name', 'like', "%{$texto}%")
                    ->orderBy('name');
            }

            public function etiquetaResultado(Model $modelo): string
            {
                return "{$modelo->name} ({$modelo->email})";
            }

            public function render(): string
            {
                return <<<'BLADE'
                    <div>
                        <p data-mensaje>{{ $this->mensajeBusqueda() }}</p>
                        @foreach($this->resultadosBusqueda as $r)
                            <span data-resultado>{{ $this->etiquetaResultado($r) }}</span>
                        @endforeach
                    </div>
                BLADE;
            }
        };
    }

    public function test_con_menos_de_2_caracteres_no_consulta(): void
    {
        User::factory()->create(['name' => 'Ana Pérez']);

        Livewire::test($this->componente())
            ->set('busqueda', 'a')
            ->assertDontSeeHtml('data-resultado')
            ->assertSee('Escriba al menos 2 caracteres para buscar');
    }

    public function test_los_espacios_no_cuentan_para_el_minimo(): void
    {
        Livewire::test($this->componente())
            ->set('busqueda', ' a ')
            ->assertSee('Escriba al menos 2 caracteres para buscar');
    }

    public function test_con_2_caracteres_devuelve_los_resultados_con_su_etiqueta(): void
    {
        User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@geograss.test']);
        User::factory()->create(['name' => 'Juan Anaya', 'email' => 'juan@geograss.test']);
        User::factory()->create(['name' => 'Pedro Gómez', 'email' => 'pedro@geograss.test']);

        Livewire::test($this->componente())
            ->set('busqueda', 'an')
            ->assertSee('Ana Pérez (ana@geograss.test)')
            ->assertSee('Juan Anaya (juan@geograss.test)')
            ->assertDontSee('Pedro Gómez');
    }

    public function test_limita_a_20_resultados(): void
    {
        User::factory()->count(25)->sequence(fn ($s) => ['name' => sprintf('Recorredor %02d', $s->index)])->create();

        $componente = Livewire::test($this->componente())->set('busqueda', 'Recorredor');

        $this->assertSame(20, substr_count($componente->html(), 'data-resultado'));
    }

    public function test_sin_resultados_lo_dice_con_el_texto_buscado(): void
    {
        Livewire::test($this->componente())
            ->set('busqueda', 'zzz')
            ->assertSee('No se encontraron resultados para "zzz"');
    }

    public function test_limpiar_busqueda_vacia_el_texto(): void
    {
        User::factory()->create(['name' => 'Ana Pérez']);

        Livewire::test($this->componente())
            ->set('busqueda', 'Ana')
            ->assertSee('Ana Pérez')
            ->call('limpiarBusqueda')
            ->assertSet('busqueda', '')
            ->assertDontSee('Ana Pérez');
    }

    public function test_el_minimo_y_el_limite_son_los_del_estandar(): void
    {
        $componente = $this->componente();

        $this->assertSame(2, $componente::MINIMO_BUSQUEDA);
        $this->assertSame(20, $componente::LIMITE_BUSQUEDA);
    }
}
