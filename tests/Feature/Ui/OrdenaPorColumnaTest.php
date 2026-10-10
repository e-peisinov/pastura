<?php

namespace Tests\Feature\Ui;

use App\Livewire\Traits\OrdenaPorColumna;
use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/** Listado mínimo para probar el trait y <x-ui.th> juntos (D55). */
class ListadoOrdenablePrueba extends Component
{
    use OrdenaPorColumna;

    protected function columnasOrdenables(): array
    {
        return [
            'nombre' => 'name',
            'rol' => ['rol', 'name'],
            'email' => fn (Builder $q, string $direccion) => $q->orderBy('email', $direccion),
        ];
    }

    protected function ordenInicial(): array
    {
        return ['nombre', 'asc'];
    }

    /** @return Collection<int, User> */
    public function filas(): Collection
    {
        return $this->aplicarOrden(User::query())->get();
    }

    public function render()
    {
        return <<<'HTML'
            <div>
                <table>
                    <thead><tr>
                        <x-ui.th columna="nombre" :orden="$this->orden()">Nombre</x-ui.th>
                        <x-ui.th columna="rol" :orden="$this->orden()">Rol</x-ui.th>
                        <x-ui.th derecha>Acciones</x-ui.th>
                    </tr></thead>
                </table>
                @foreach($this->filas() as $usuario)
                    <p data-fila>{{ $usuario->name }}</p>
                @endforeach
            </div>
        HTML;
    }
}

class OrdenaPorColumnaTest extends TestCase
{
    use RefreshDatabase;

    private function tresUsuarios(): void
    {
        User::factory()->create(['name' => 'Beltran', 'rol' => Rol::Admin, 'email' => 'c@pasturometro.test']);
        User::factory()->create(['name' => 'Alvarez', 'rol' => Rol::Recorredor, 'email' => 'a@pasturometro.test']);
        User::factory()->create(['name' => 'Correa', 'rol' => Rol::Admin, 'email' => 'b@pasturometro.test']);
    }

    public function test_abre_con_el_orden_inicial_y_la_cabecera_lo_marca(): void
    {
        $this->tresUsuarios();

        Livewire::test(ListadoOrdenablePrueba::class)
            ->assertSet('ordenColumna', 'nombre')
            ->assertSet('ordenDireccion', 'asc')
            ->assertSeeInOrder(['Alvarez', 'Beltran', 'Correa'])
            ->assertSeeHtml('aria-sort="ascending"')
            ->assertSeeHtml('data-columna="nombre"')
            ->assertSeeHtml("wire:click=\"ordenarPor('nombre')\"")
            ->assertSeeHtml('title="Ordenar por Nombre"');
    }

    public function test_el_segundo_click_invierte_y_otra_columna_vuelve_a_asc(): void
    {
        $this->tresUsuarios();

        $componente = Livewire::test(ListadoOrdenablePrueba::class)
            ->call('ordenarPor', 'nombre')
            ->assertSet('ordenDireccion', 'desc')
            ->assertSeeInOrder(['Correa', 'Beltran', 'Alvarez'])
            ->assertSeeHtml('aria-sort="descending"');

        // Varias columnas en la misma dirección: rol y después nombre.
        $componente->call('ordenarPor', 'rol')
            ->assertSet('ordenColumna', 'rol')
            ->assertSet('ordenDireccion', 'asc')
            ->assertSeeInOrder(['Beltran', 'Correa', 'Alvarez']);

        // Un closure.
        $componente->call('ordenarPor', 'email')
            ->assertSeeInOrder(['Alvarez', 'Correa', 'Beltran']);
    }

    public function test_una_clave_desconocida_no_cambia_nada(): void
    {
        $this->tresUsuarios();

        Livewire::test(ListadoOrdenablePrueba::class)
            ->call('ordenarPor', 'password')
            ->assertSet('ordenColumna', 'nombre')
            ->assertSet('ordenDireccion', 'asc')
            ->assertSeeInOrder(['Alvarez', 'Beltran', 'Correa']);
    }

    public function test_la_columna_sin_clave_es_un_th_comun_y_derecha_alinea(): void
    {
        $html = Blade::render('<table><tr><x-ui.th derecha>Acciones</x-ui.th><x-ui.th>Estado</x-ui.th></tr></table>');

        $this->assertStringNotContainsString('wire:click', $html);
        $this->assertStringNotContainsString('aria-sort', $html);
        $this->assertMatchesRegularExpression('/<th class="px-4 py-3 text-right"[^>]*>\s*Acciones/', $html);
        $this->assertMatchesRegularExpression('/<th class="px-4 py-3"[^>]*>\s*Estado/', $html);
    }

    public function test_la_columna_inactiva_muestra_la_flecha_solo_al_pasar_el_mouse(): void
    {
        $html = Blade::render('<table><tr><x-ui.th columna="saldo" :orden="[\'columna\' => \'nombre\', \'direccion\' => \'asc\']" derecha>Saldo</x-ui.th></tr></table>');

        $this->assertStringContainsString("wire:click=\"ordenarPor('saldo')\"", $html);
        $this->assertStringNotContainsString('aria-sort', $html);
        $this->assertStringContainsString('group-hover:opacity-100', $html);
        // Alineada a la derecha: la flecha va a la izquierda del rótulo.
        $this->assertStringContainsString('flex-row-reverse', $html);
    }
}
