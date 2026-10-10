<?php

namespace Tests\Feature\Layout;

use App\Enums\ColorAcento;
use App\Enums\Rol;
use App\Models\Configuracion;
use App\Models\User;
use App\Support\Tema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tema::reiniciar();
    }

    private function dashboardComo(User $usuario): string
    {
        return $this->actingAs($usuario)->get(route('dashboard'))->assertOk()->getContent();
    }

    public function test_el_sidebar_muestra_el_nombre_de_la_organizacion_de_la_configuracion(): void
    {
        Configuracion::factory()->create(['nombre' => 'Estancia La Pastora']);

        $html = $this->dashboardComo(User::factory()->create());

        $this->assertStringContainsString('data-sidebar', $html);
        $this->assertStringContainsString('Estancia La Pastora', $html);
        $this->assertStringContainsString('<title>Estancia La Pastora</title>', $html);
    }

    public function test_muestra_el_logo_de_la_organizacion_si_esta_cargado(): void
    {
        Configuracion::factory()->create(['logo_path' => 'logos/organizacion.png']);

        $html = $this->dashboardComo(User::factory()->create());

        $this->assertStringContainsString('storage/logos/organizacion.png', $html);
    }

    public function test_el_tablero_esta_en_dashboard_y_aparece_activo_con_el_acento_del_tema(): void
    {
        Configuracion::factory()->create(['color_acento' => ColorAcento::Teal]);

        $html = $this->dashboardComo(User::factory()->create());

        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/dashboard"[^>]*class="[^"]*bg-teal-700 text-white[^"]*"[^>]*aria-current="page"[^>]*data-modulo="tablero"/s',
            $html,
        );
        $this->assertStringContainsString('>Inicio</span>', $html);
    }

    public function test_los_enlaces_del_sidebar_navegan_sin_recargar_la_pagina(): void
    {
        $html = $this->dashboardComo(User::factory()->create());

        // D31: cada módulo y el perfil van con wire:navigate...
        $enlaces = preg_match_all('/<a href="[^"]*"[^>]*data-modulo="/', $html);
        $conNavigate = preg_match_all('/<a href="[^"]*" wire:navigate[^>]*data-modulo="/', $html);

        $this->assertGreaterThan(0, $enlaces);
        $this->assertSame($enlaces, $conNavigate, 'Todos los módulos del sidebar llevan wire:navigate');
        $this->assertMatchesRegularExpression('/<a href="[^"]*\/profile" wire:navigate/', $html);

        // ...y el POST de cerrar sesión, no.
        $this->assertDoesNotMatchRegularExpression('/<form method="POST"[^>]*wire:navigate/', $html);
    }

    public function test_el_sidebar_muestra_los_modulos_que_ya_tienen_ruta(): void
    {
        $html = $this->dashboardComo(User::factory()->admin()->create());

        $this->assertStringContainsString('data-modulo="tablero"', $html);
        $this->assertStringContainsString('data-modulo="campos"', $html);
        $this->assertStringContainsString('data-modulo="recorridas"', $html);
        $this->assertStringContainsString('data-modulo="usuarios"', $html);
        $this->assertMatchesRegularExpression('/data-modulo="recorridas"[^>]*data-tecla="r"/', $html);
    }

    public function test_cada_modulo_lleva_su_atajo_alt_letra_y_el_layout_lo_escucha(): void
    {
        $html = $this->dashboardComo(User::factory()->admin()->create());

        // D55: la letra viaja en data-tecla y en el title del enlace.
        $this->assertMatchesRegularExpression('/<a href="[^"]*"[^>]*title="Usuarios \(Alt\+U\)"[^>]*data-modulo="usuarios"[^>]*data-tecla="u"/s', $html);
        $this->assertMatchesRegularExpression('/data-modulo="tablero"[^>]*data-tecla="t"/', $html);
        $this->assertStringContainsString('Alt+T</kbd>', $html);

        // El layout escucha keydown en window y navega al enlace con esa letra.
        $this->assertStringContainsString('x-on:keydown.window="atajoModulo($event)"', $html);
        $this->assertStringContainsString('Livewire.navigate(enlace.href)', $html);
        $this->assertStringContainsString('[data-sidebar] a[data-tecla=', $html);
    }

    public function test_un_recorredor_no_tiene_atajo_a_los_modulos_que_no_ve(): void
    {
        $html = $this->dashboardComo(User::factory()->create(['rol' => Rol::Recorredor]));

        $this->assertStringNotContainsString('data-tecla="u"', $html); // usuarios
        $this->assertStringContainsString('data-tecla="t"', $html); // tablero
    }

    public function test_abajo_de_todo_va_el_usuario_con_nombre_rol_perfil_y_cerrar_sesion(): void
    {
        $html = $this->dashboardComo(User::factory()->create(['name' => 'Marta Ledesma', 'rol' => Rol::Admin]));

        $this->assertStringContainsString('Marta Ledesma', $html);
        $this->assertStringContainsString('Administrador', $html);
        $this->assertStringContainsString('data-menu-usuario', $html);
        $this->assertStringContainsString('href="'.route('profile').'"', $html);
        $this->assertMatchesRegularExpression('/>\s*Perfil\s*<\/a>/', $html);
        $this->assertStringContainsString('action="'.route('logout').'"', $html);
        $this->assertStringContainsString('Cerrar sesión', $html);
    }

    public function test_en_mobile_hay_barra_superior_con_hamburguesa_y_drawer_con_backdrop(): void
    {
        $html = $this->dashboardComo(User::factory()->create());

        $this->assertStringContainsString('data-barra-mobile', $html);
        $this->assertStringContainsString('aria-label="Abrir menú"', $html);
        $this->assertStringContainsString('x-on:click="menuAbierto = true"', $html);
        // Backdrop bg-black/50 que cierra al click, y Escape.
        $this->assertMatchesRegularExpression('/data-backdrop-menu|bg-black\/50/', $html);
        $this->assertStringContainsString('x-on:click="menuAbierto = false"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window="menuAbierto = false"', $html);
        // Fijo en escritorio, escondido en mobile.
        $this->assertStringContainsString('-translate-x-full md:translate-x-0', $html);
        $this->assertStringContainsString('md:pl-64', $html);
    }

    public function test_no_queda_nada_de_la_navegacion_de_breeze(): void
    {
        $html = $this->dashboardComo(User::factory()->create());

        $this->assertStringNotContainsString('layout.navigation', $html);
        $this->assertFileDoesNotExist(resource_path('views/livewire/layout/navigation.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/components/nav-link.blade.php'));
        $this->assertStringNotContainsString('dark:', file_get_contents(resource_path('views/layouts/app.blade.php')));
        $this->assertStringNotContainsString('dark:', file_get_contents(resource_path('views/components/ui/sidebar.blade.php')));
    }

    public function test_el_header_de_la_pagina_se_renderiza_dentro_del_contenido(): void
    {
        $html = $this->dashboardComo(User::factory()->create(['name' => 'Juan']));

        $this->assertMatchesRegularExpression('/<h1[^>]*>(?:(?!<\/h1>).)*Inicio\s*<\/span>\s*<\/h1>/s', $html);
        $this->assertStringContainsString('Bienvenido, Juan.', $html);
    }

    public function test_cerrar_sesion_por_post_cierra_la_sesion_y_vuelve_al_inicio(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_un_visitante_no_puede_cerrar_sesion_ni_ver_el_tablero(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
