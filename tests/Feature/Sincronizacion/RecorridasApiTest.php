<?php

namespace Tests\Feature\Sincronizacion;

use App\Enums\Nivel;
use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Los endpoints de datos de la app de campo: el catálogo de campos y
 * potreros, y la subida idempotente de una recorrida (P11). Las reglas
 * finas del upsert están en SincronizadorRecorridasTest.
 */
class RecorridasApiTest extends TestCase
{
    use RefreshDatabase;

    private const RECORRIDA = '6f1c2a7e-0b1d-4c3e-9f20-1a2b3c4d5e6f';

    private User $juan;

    private Potrero $lote4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->juan = User::factory()->create(['name' => 'Juan Pérez']);
        $this->lote4 = Potrero::factory()->conContorno()
            ->for(Campo::factory()->create(['nombre' => 'La Esperanza', 'localidad' => 'Río Cuarto']))
            ->create(['nombre' => 'Lote 4', 'tipo_pastura' => 'Alfalfa']);
    }

    private function cuerpo(?array $mediciones = null, array $extra = []): array
    {
        return [
            'potrero_id' => $this->lote4->id,
            'iniciada_en' => '2026-10-10T09:12:00-03:00',
            'dispositivo' => 'iPhone · Safari 18',
            'observaciones' => null,
            'mediciones' => $mediciones ?? [[
                'uuid' => 'a0000000-0000-4000-8000-000000000001',
                'tomada_en' => '2026-10-10T09:13:05-03:00',
                'altura_cm' => 12.3,
                'distancia_cm' => 41.0,
                'kg_ms_ha' => null,
                'latitud' => -33.1234567,
                'longitud' => -64.3456789,
                'precision_m' => 4.0,
                'inclinacion_grados' => 3.5,
                'imagenes' => 8,
                'dispersion_cm' => 0.42,
                'calibracion' => ['a' => 1234.5, 'c' => -2.1, 'r2' => 0.998, 'grad' => 0.01],
            ]],
            ...$extra,
        ];
    }

    // ----- Catálogo ---------------------------------------------------------

    public function test_el_catalogo_trae_campos_y_potreros_activos_con_su_contorno(): void
    {
        $esperanza = $this->lote4->campo;
        Potrero::factory()->for($esperanza)->create(['nombre' => 'El Bajo']);
        Potrero::factory()->for($esperanza)->inactivo()->create(['nombre' => 'Viejo']);
        Potrero::factory()->for(Campo::factory()->create(['nombre' => 'Dado de baja', 'activo' => false]))->create();
        Potrero::factory()->for(Campo::factory()->create(['nombre' => 'Sin potreros activos']))->inactivo()->create();
        Sanctum::actingAs($this->juan);

        $respuesta = $this->getJson('/api/v1/campos')->assertOk();

        $this->assertSame(['La Esperanza'], array_column($respuesta->json('campos'), 'nombre'));
        $this->assertSame(['El Bajo', 'Lote 4'], array_column($respuesta->json('campos.0.potreros'), 'nombre'));
        $respuesta->assertJsonPath('campos.0.localidad', 'Río Cuarto')
            ->assertJsonPath('campos.0.potreros.1.id', $this->lote4->id)
            ->assertJsonPath('campos.0.potreros.1.tipo_pastura', 'Alfalfa')
            ->assertJsonPath('campos.0.potreros.1.contorno.type', 'Polygon')
            ->assertJsonPath('campos.0.potreros.0.contorno', null)
            ->assertJsonPath('campos.0.potreros.0.superficie_ha', null);
        $this->assertEqualsWithDelta(round($this->lote4->superficieHa(), 2), $respuesta->json('campos.0.potreros.1.superficie_ha'), 0.001);
    }

    // ----- Subida -----------------------------------------------------------

    public function test_sube_una_recorrida_nueva_y_responde_201(): void
    {
        Sanctum::actingAs($this->juan);

        $this->putJson('/api/v1/recorridas/'.self::RECORRIDA, $this->cuerpo())
            ->assertCreated()
            ->assertExactJson([
                'uuid' => self::RECORRIDA,
                'creada' => true,
                'mediciones' => ['creadas' => 1, 'actualizadas' => 0, 'total' => 1],
            ]);

        $recorrida = Recorrida::sole();
        $this->assertSame($this->juan->id, $recorrida->user_id);
        $this->assertSame(1, $recorrida->puntos);
    }

    public function test_el_reintento_responde_200_sin_duplicar(): void
    {
        Sanctum::actingAs($this->juan);

        $this->putJson('/api/v1/recorridas/'.self::RECORRIDA, $this->cuerpo())->assertCreated();
        $this->putJson('/api/v1/recorridas/'.self::RECORRIDA, $this->cuerpo())
            ->assertOk()
            ->assertJsonPath('creada', false)
            ->assertJsonPath('mediciones.actualizadas', 1);

        $this->assertSame(1, Recorrida::count());
        $this->assertSame(1, Medicion::count());
    }

    public function test_el_dueno_sale_del_token_aunque_el_json_diga_otro(): void
    {
        $otro = User::factory()->create();
        Sanctum::actingAs($this->juan);

        $this->putJson('/api/v1/recorridas/'.self::RECORRIDA, $this->cuerpo(extra: ['user_id' => $otro->id]))->assertCreated();

        $this->assertSame($this->juan->id, Recorrida::sole()->user_id);
    }

    public function test_la_recorrida_de_otro_usuario_da_403(): void
    {
        Recorrida::factory()->for($this->lote4)->create(['uuid' => self::RECORRIDA]);
        Sanctum::actingAs($this->juan);

        $this->putJson('/api/v1/recorridas/'.self::RECORRIDA, $this->cuerpo())
            ->assertForbidden()
            ->assertJsonPath('message', __('api.recorrida_ajena'));
    }

    public function test_un_potrero_inactivo_da_422(): void
    {
        $this->lote4->update(['activo' => false]);
        Sanctum::actingAs($this->juan);

        $this->putJson('/api/v1/recorridas/'.self::RECORRIDA, $this->cuerpo())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['potrero_id' => __('api.potrero_inactivo')]);
    }

    public function test_valida_la_forma_del_json(): void
    {
        Sanctum::actingAs($this->juan);
        $punto = $this->cuerpo()['mediciones'][0];

        $this->putJson('/api/v1/recorridas/'.self::RECORRIDA, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['potrero_id', 'iniciada_en', 'mediciones']);

        $this->putJson('/api/v1/recorridas/'.self::RECORRIDA, $this->cuerpo([
            ['uuid' => 'no-es-uuid', 'altura_cm' => 1000] + $punto,
            ['latitud' => 91, 'longitud' => null] + $punto,
            $punto,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'mediciones.0.uuid', 'mediciones.0.altura_cm',
                'mediciones.1.latitud', 'mediciones.1.longitud',
                'mediciones.2.uuid', // repetido con el 1
            ]);

        $this->assertSame(0, Recorrida::count());
    }

    public function test_una_recorrida_sin_mediciones_es_valida(): void
    {
        Sanctum::actingAs($this->juan);

        $this->putJson('/api/v1/recorridas/'.self::RECORRIDA, $this->cuerpo([]))
            ->assertCreated()
            ->assertJsonPath('mediciones.total', 0);
    }

    public function test_la_ruta_solo_acepta_un_uuid(): void
    {
        Sanctum::actingAs($this->juan);

        $this->putJson('/api/v1/recorridas/123', $this->cuerpo())->assertNotFound();
    }

    public function test_sin_editar_en_recorridas_no_sube(): void
    {
        $this->juan->asignarNivel('recorridas', Nivel::Ver);
        Sanctum::actingAs($this->juan);

        $this->putJson('/api/v1/recorridas/'.self::RECORRIDA, $this->cuerpo())->assertForbidden();
        $this->assertSame(0, Recorrida::count());
    }
}
