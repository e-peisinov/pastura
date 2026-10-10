<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La raíz y la pantalla de ingreso (D52): `/` no tiene pantalla propia y el
 * login se ve con las piezas del sistema, no con el scaffolding de Breeze.
 */
class InicioTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_raiz_manda_al_login_sin_sesion(): void
    {
        $this->get('/')->assertRedirect(route('login', absolute: false));
    }

    public function test_la_raiz_manda_al_tablero_con_sesion(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_la_landing_de_laravel_ya_no_existe(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/welcome.blade.php'));
    }

    public function test_el_login_usa_las_piezas_del_sistema_en_espanol(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        // Marca de la organización arriba, como en el sidebar.
        $this->assertStringContainsString('data-pantalla-acceso', $html);
        $this->assertStringContainsString('data-nombre-organizacion', $html);

        // Textos del dominio, en español.
        $this->assertStringContainsString(__('acceso.login.titulo'), $html);
        $this->assertStringContainsString(__('acceso.login.email'), $html);
        $this->assertStringContainsString(__('acceso.login.password'), $html);
        $this->assertStringContainsString(__('acceso.login.recordarme'), $html);
        $this->assertStringContainsString(__('acceso.login.olvido'), $html);
        $this->assertStringContainsString(__('acceso.login.boton'), $html);
        $this->assertStringNotContainsString('Remember me', $html);
        $this->assertStringNotContainsString('Log in', $html);

        // Los toasts se montan también acá (regla 3) y nada del scaffolding de Breeze.
        $this->assertStringContainsString('data-toasts', $html);
        $this->assertStringNotContainsString('text-indigo-600', $html);
        $this->assertStringNotContainsString('viewBox="0 0 316 316"', $html);
    }

    public function test_las_pantallas_de_acceso_no_escriben_dark(): void
    {
        $archivos = array_merge(
            glob(resource_path('views/livewire/pages/auth/*.blade.php')),
            [resource_path('views/layouts/guest.blade.php')],
        );

        foreach ($archivos as $archivo) {
            $this->assertStringNotContainsString('dark:', file_get_contents($archivo), basename($archivo));
        }
    }
}
