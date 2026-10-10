<?php

namespace Tests\Feature\Layout;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * El panel también anda en una subcarpeta (XAMPP: /pastura/public). Livewire
 * escribe las direcciones de su script y de sus pedidos desde la raíz del
 * dominio; acá se comprueba que lleven la subcarpeta adelante (P13).
 */
class SubcarpetaTest extends TestCase
{
    use RefreshDatabase;

    private function enSubcarpeta(string $ruta): TestResponse
    {
        return $this->call('GET', "http://localhost/pastura/public{$ruta}", server: [
            'SCRIPT_NAME' => '/pastura/public/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'PHP_SELF' => '/pastura/public/index.php',
        ]);
    }

    public function test_en_una_subcarpeta_el_script_y_los_pedidos_de_livewire_la_llevan_adelante(): void
    {
        $html = $this->enSubcarpeta('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<script src="/pastura/public/livewire/livewire(\.min)?\.js\?id=#', $html);
        $this->assertStringContainsString('data-update-uri="/pastura/public/livewire/update"', $html);
        $this->assertStringNotContainsString('src="/livewire/', $html);
    }

    public function test_tambien_en_las_pantallas_con_sesion(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $html = $this->enSubcarpeta('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('data-update-uri="/pastura/public/livewire/update"', $html);
    }

    public function test_en_la_raiz_del_dominio_no_cambia_nada(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<script src="/livewire/livewire(\.min)?\.js\?id=#', $html);
        $this->assertStringContainsString('data-update-uri="/livewire/update"', $html);
    }
}
