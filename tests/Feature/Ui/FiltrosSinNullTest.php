<?php

namespace Tests\Feature\Ui;

use App\Livewire\Usuarios\Usuarios;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Un filtro nunca representa un estado con null (convenciones.md, "Filtros").
 *
 * Livewire no rehidrata null en una propiedad tipada: la deja en su valor por
 * omisión. Con "Todos" = null, elegir Todos y hacer cualquier otra acción
 * (paginar, ordenar, abrir un modal) devolvía el filtro a "Activos" solo.
 */
class FiltrosSinNullTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string, string, string}> */
    public static function listados(): array
    {
        return [
            'usuarios' => [Usuarios::class, 'activos', 'todos'],
        ];
    }

    #[DataProvider('listados')]
    public function test_elegir_todos_sobrevive_a_la_siguiente_accion(string $componente, string $inicial, string $todos): void
    {
        $admin = User::factory()->admin()->create();

        $prueba = Livewire::actingAs($admin)->test($componente)
            ->assertSet('filtroEstado', $inicial)
            ->set('filtroEstado', $todos)
            ->assertSet('filtroEstado', $todos);

        // La acción siguiente (cualquiera) rehidrata el componente: el filtro
        // tiene que seguir en Todos.
        $prueba->call('$refresh')->assertSet('filtroEstado', $todos);

        // El combo ofrece Todos como una opción más (la X va aparte: ComboboxTest).
        $this->assertMatchesRegularExpression('/("|&quot;)valor("|&quot;):("|&quot;)'.$todos.'("|&quot;)/', $prueba->html());
    }
}
