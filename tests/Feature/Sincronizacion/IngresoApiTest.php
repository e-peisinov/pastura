<?php

namespace Tests\Feature\Sincronizacion;

use App\Enums\Nivel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * El ingreso de la app de campo con el usuario del panel (P10, P12): token
 * por celular, usuarios dados de baja, permiso Editar en Recorridas y salida.
 */
class IngresoApiTest extends TestCase
{
    use RefreshDatabase;

    private User $juan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->juan = User::factory()->create(['name' => 'Juan Pérez', 'email' => 'juan@pastura.test']);
    }

    private function ingresar(array $datos = []): TestResponse
    {
        return $this->postJson('/api/v1/ingreso', [
            'email' => 'juan@pastura.test',
            'password' => 'password',
            'dispositivo' => 'iPhone · Safari 18',
            ...$datos,
        ]);
    }

    public function test_ingresa_y_recibe_un_token_con_el_nombre_del_celular(): void
    {
        $respuesta = $this->ingresar()->assertOk()
            ->assertJsonPath('usuario.id', $this->juan->id)
            ->assertJsonPath('usuario.nombre', 'Juan Pérez')
            ->assertJsonPath('usuario.rol', 'recorredor')
            ->assertJsonPath('usuario.puede_recorrer', true);

        $this->assertNotEmpty($respuesta->json('token'));
        $this->assertSame('iPhone · Safari 18', $this->juan->tokens()->sole()->name);

        // El token abre la API.
        $this->withToken($respuesta->json('token'))->getJson('/api/v1/yo')
            ->assertOk()
            ->assertJsonPath('usuario.email', 'juan@pastura.test');
    }

    public function test_con_una_contrasena_equivocada_no_ingresa(): void
    {
        $this->ingresar(['password' => 'otra'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => __('auth.failed')]);

        $this->ingresar(['email' => 'nadie@pastura.test'])->assertUnprocessable();
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_un_usuario_dado_de_baja_no_ingresa(): void
    {
        $this->juan->update(['activo' => false]);

        $this->ingresar()->assertForbidden()->assertJsonPath('message', __('auth.inactivo'));
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_sin_editar_en_recorridas_ingresa_igual_pero_no_puede_recorrer(): void
    {
        // P12: la app es también la puerta al panel; el permiso se mira al subir.
        $this->juan->asignarNivel('recorridas', Nivel::Ver);

        $token = $this->ingresar()->assertOk()
            ->assertJsonPath('usuario.puede_recorrer', false)
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/yo')->assertOk()->assertJsonPath('usuario.puede_recorrer', false);
        $this->withToken($token)->getJson('/api/v1/campos')->assertForbidden()->assertJsonPath('message', __('api.sin_permiso'));
        $this->withToken($token)->putJson('/api/v1/recorridas/6f1c2a7e-0b1d-4c3e-9f20-1a2b3c4d5e6f', [])->assertForbidden();
    }

    public function test_limita_los_intentos_de_ingreso(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->ingresar(['password' => 'otra'])->assertUnprocessable();
        }

        $this->ingresar()->assertTooManyRequests();
    }

    public function test_salir_revoca_el_token_de_ese_celular_y_no_los_otros(): void
    {
        $celular = $this->ingresar()->json('token');
        $tablet = $this->ingresar(['dispositivo' => 'Tablet'])->json('token');

        $this->withToken($celular)->postJson('/api/v1/salida')->assertNoContent();

        $this->assertSame(['Tablet'], $this->juan->tokens()->pluck('name')->all());
        $this->app['auth']->forgetGuards();
        $this->withToken($celular)->getJson('/api/v1/yo')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($tablet)->getJson('/api/v1/yo')->assertOk();
    }

    public function test_sin_token_o_con_uno_invalido_responde_401_en_json(): void
    {
        $this->getJson('/api/v1/yo')->assertUnauthorized()->assertJsonPath('message', __('api.sin_sesion'));
        $this->withToken('1|inventado')->getJson('/api/v1/campos')->assertUnauthorized();

        // Aunque el pedido no pida JSON: la API nunca redirige al login.
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Accept' => 'text/html'])->get('/api/v1/yo')->assertUnauthorized();
    }

    public function test_la_sesion_del_panel_no_abre_la_api(): void
    {
        $this->actingAs($this->juan, 'web')->getJson('/api/v1/yo')->assertUnauthorized();
    }

    public function test_un_usuario_dado_de_baja_despues_de_ingresar_pierde_el_acceso(): void
    {
        $token = $this->ingresar()->json('token');
        $this->juan->update(['activo' => false]);

        $this->withToken($token)->getJson('/api/v1/campos')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/v1/yo')->assertUnauthorized();
        $this->withToken($token)->postJson('/api/v1/enlace-panel')->assertUnauthorized();
    }

    public function test_si_le_quitan_editar_en_recorridas_recibe_403(): void
    {
        $token = $this->ingresar()->json('token');
        $this->juan->asignarNivel('recorridas', Nivel::Ver);

        $this->withToken($token)->getJson('/api/v1/campos')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/yo')->assertOk()->assertJsonPath('usuario.puede_recorrer', false);
    }
}
