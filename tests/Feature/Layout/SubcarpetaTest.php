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

    /**
     * Hace lo mismo que el navegador en XAMPP: abre la pantalla por la
     * subcarpeta, toma el estado del componente y le manda la acción a
     * livewire/update. Devuelve la redirección que contesta Livewire.
     *
     * @param  array<string, mixed>  $cambios
     */
    private function redireccionEnSubcarpeta(string $ruta, string $componente, array $cambios, string $metodo): ?string
    {
        $html = $this->enSubcarpeta($ruta)->assertOk()->getContent();

        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $estados);
        $estado = collect($estados[1])
            ->map(fn ($e) => html_entity_decode($e))
            ->first(fn ($e) => json_decode($e, true)['memo']['name'] === $componente);
        $this->assertNotNull($estado, "No está el componente {$componente} en {$ruta}");

        $respuesta = $this->call('POST', 'http://localhost/pastura/public/livewire/update', server: [
            'SCRIPT_NAME' => '/pastura/public/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'PHP_SELF' => '/pastura/public/index.php',
            'HTTP_X_LIVEWIRE' => '1',
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['components' => [[
            'snapshot' => $estado,
            'updates' => $cambios,
            'calls' => [['path' => '', 'method' => $metodo, 'params' => []]],
        ]]]))->assertOk();

        return $respuesta->json('components.0.effects.redirect');
    }

    public function test_al_ingresar_en_una_subcarpeta_lleva_al_tablero_de_la_subcarpeta(): void
    {
        $usuario = User::factory()->create();

        $destino = $this->redireccionEnSubcarpeta('/login', 'pages.auth.login', [
            'form.email' => $usuario->email,
            'form.password' => 'password',
        ], 'login');

        $this->assertSame('http://localhost/pastura/public/dashboard', $destino);
    }

    public function test_al_confirmar_la_contrasena_en_una_subcarpeta_vuelve_a_la_subcarpeta(): void
    {
        $this->actingAs(User::factory()->create());

        $destino = $this->redireccionEnSubcarpeta('/confirm-password', 'pages.auth.confirm-password', [
            'password' => 'password',
        ], 'confirmPassword');

        $this->assertSame('http://localhost/pastura/public/dashboard', $destino);
    }

    public function test_al_borrar_la_cuenta_en_una_subcarpeta_vuelve_a_la_subcarpeta(): void
    {
        $this->actingAs(User::factory()->create());

        $destino = $this->redireccionEnSubcarpeta('/profile', 'profile.delete-user-form', [
            'password' => 'password',
        ], 'deleteUser');

        $this->assertSame('http://localhost/pastura/public', $destino);
    }

    public function test_en_la_raiz_del_dominio_no_cambia_nada(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<script src="/livewire/livewire(\.min)?\.js\?id=#', $html);
        $this->assertStringContainsString('data-update-uri="/livewire/update"', $html);
    }
}
