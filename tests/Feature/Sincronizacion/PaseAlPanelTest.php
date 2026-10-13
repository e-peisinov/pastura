<?php

namespace Tests\Feature\Sincronizacion;

use App\Enums\Nivel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ir al panel desde la app sin volver a escribir la contraseña (P12): la app
 * pide con su token un enlace firmado, de un solo uso y que vence en un
 * minuto; el enlace abre la sesión del panel de ese usuario.
 */
class PaseAlPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $juan;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->juan = User::factory()->create(['email' => 'juan@pastura.test']);
        $this->token = $this->juan->createToken('iPhone · Safari')->plainTextToken;
    }

    private function pedirEnlace(array $datos = []): string
    {
        $url = $this->withToken($this->token)->postJson('/api/v1/enlace-panel', $datos)
            ->assertOk()
            ->json('url');

        // Los pedidos que siguen son del navegador, no de la app.
        $this->app['auth']->forgetGuards();
        $this->withoutToken();

        return $url;
    }

    private function assertNoEntra(string $url): void
    {
        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', __('api.enlace_vencido'));
        $this->assertGuest('web');
    }

    public function test_el_enlace_abre_la_sesion_del_panel_y_lleva_al_tablero(): void
    {
        $url = $this->pedirEnlace();

        $this->assertStringStartsWith(url('/'), $url);
        $this->assertStringNotContainsString($this->token, $url);

        $this->get($url)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->juan, 'web');
    }

    /** La ayuda de la app abre el instructivo completo (P25). */
    public function test_el_enlace_puede_llevar_a_las_instrucciones(): void
    {
        $url = $this->pedirEnlace(['destino' => 'instrucciones']);

        $this->get($url)->assertRedirect(route('instrucciones'));
        $this->assertAuthenticatedAs($this->juan, 'web');
    }

    /** Solo destinos conocidos: cualquier otro se rechaza al pedir el enlace. */
    public function test_un_destino_desconocido_se_rechaza(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/enlace-panel', ['destino' => 'https://otro.sitio'])
            ->assertUnprocessable();
    }

    public function test_el_enlace_sirve_una_sola_vez(): void
    {
        $url = $this->pedirEnlace();

        $this->get($url)->assertRedirect(route('dashboard'));

        // Otro navegador con el mismo enlace (copiado, o del historial).
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->assertNoEntra($url);
    }

    public function test_el_enlace_vence_al_minuto(): void
    {
        $url = $this->pedirEnlace();

        $this->travel(61)->seconds();

        $this->assertNoEntra($url);
    }

    public function test_un_enlace_alterado_no_entra(): void
    {
        $url = $this->pedirEnlace();

        // Se cambia el primer carácter de la firma por otro distinto (si ya era 0, por 1).
        $this->assertNoEntra(preg_replace_callback('/signature=(\w)/', fn ($m) => 'signature='.($m[1] === '0' ? '1' : '0'), $url));
        // Ni cambiando el pase por otro: la firma cubre toda la dirección.
        $this->assertNoEntra(preg_replace('#entrar-desde-app/[^?]+#', 'entrar-desde-app/inventado', $url));
    }

    public function test_si_lo_dieron_de_baja_antes_de_usarlo_no_entra(): void
    {
        $url = $this->pedirEnlace();
        $this->juan->update(['activo' => false]);

        $this->get($url)->assertRedirect(route('login'))->assertSessionHas('error', __('auth.inactivo'));
        $this->assertGuest('web');
    }

    public function test_reemplaza_la_sesion_de_otro_usuario_en_ese_navegador(): void
    {
        $url = $this->pedirEnlace();
        $otro = User::factory()->admin()->create();

        $this->actingAs($otro, 'web')->get($url)->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->juan, 'web');
    }

    public function test_sin_permiso_de_recorridas_tambien_puede_ir_al_panel(): void
    {
        $this->juan->asignarNivel('recorridas', Nivel::Ver);

        $this->get($this->pedirEnlace())->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->juan, 'web');
    }

    public function test_sin_token_no_hay_enlace(): void
    {
        $this->postJson('/api/v1/enlace-panel')->assertUnauthorized();
    }
}
