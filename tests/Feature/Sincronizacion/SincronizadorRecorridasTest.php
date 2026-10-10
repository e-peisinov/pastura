<?php

namespace Tests\Feature\Sincronizacion;

use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use App\Support\Recorridas\SincronizadorRecorridas;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * La subida idempotente de una recorrida (P4, P11; dominio.md, reglas 2 a 4).
 * Los datos llegan ya validados en su forma; acá se prueban las reglas de
 * negocio: upsert por uuid, pertenencia, potrero activo y que `activo` lo
 * decide el panel.
 */
class SincronizadorRecorridasTest extends TestCase
{
    use RefreshDatabase;

    private const RECORRIDA = '6f1c2a7e-0b1d-4c3e-9f20-1a2b3c4d5e6f';

    private const PUNTO_1 = 'a0000000-0000-4000-8000-000000000001';

    private const PUNTO_2 = 'a0000000-0000-4000-8000-000000000002';

    private const PUNTO_3 = 'a0000000-0000-4000-8000-000000000003';

    private User $juan;

    private Potrero $lote4;

    private SincronizadorRecorridas $sincronizador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->juan = User::factory()->create(['name' => 'Juan Pérez']);
        $this->lote4 = Potrero::factory()->for(Campo::factory()->create(['nombre' => 'La Esperanza']))->create(['nombre' => 'Lote 4']);
        $this->sincronizador = new SincronizadorRecorridas;
    }

    /** @param  list<array<string, mixed>>  $mediciones */
    private function datos(array $mediciones, array $recorrida = []): array
    {
        return [
            'potrero_id' => $this->lote4->id,
            'iniciada_en' => '2026-10-10T09:12:00-03:00',
            'dispositivo' => 'iPhone · Safari 18',
            'observaciones' => null,
            ...$recorrida,
            'mediciones' => $mediciones,
        ];
    }

    private function punto(string $uuid, float $altura, array $extra = []): array
    {
        return [
            'uuid' => $uuid,
            'tomada_en' => '2026-10-10T09:13:05-03:00',
            'altura_cm' => $altura,
            'distancia_cm' => 41.0,
            'kg_ms_ha' => 2150,
            'latitud' => -34.6512345,
            'longitud' => -59.4301234,
            'precision_m' => 4.0,
            'inclinacion_grados' => 3.5,
            'imagenes' => 8,
            'dispersion_cm' => 0.42,
            'calibracion' => ['a' => 1234.5, 'c' => -2.1, 'r2' => 0.998, 'grad' => 0.01],
            ...$extra,
        ];
    }

    public function test_crea_la_recorrida_con_sus_mediciones_a_nombre_del_usuario(): void
    {
        $resultado = $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $this->datos([
            $this->punto(self::PUNTO_1, 12.3),
            $this->punto(self::PUNTO_2, 15.1),
        ]));

        $this->assertSame(['creada' => true, 'mediciones' => ['creadas' => 2, 'actualizadas' => 0, 'total' => 2]], $resultado);

        $recorrida = Recorrida::where('uuid', self::RECORRIDA)->firstOrFail();
        $this->assertSame($this->juan->id, $recorrida->user_id);
        $this->assertSame($this->lote4->id, $recorrida->potrero_id);
        $this->assertSame('iPhone · Safari 18', $recorrida->dispositivo);
        $this->assertTrue($recorrida->activo);
        $this->assertSame(2, $recorrida->puntos);
        $this->assertEqualsWithDelta(13.7, $recorrida->altura_media, 0.001);

        $punto = Medicion::where('uuid', self::PUNTO_1)->firstOrFail();
        $this->assertSame($recorrida->id, $punto->recorrida_id);
        $this->assertSame(12.3, $punto->altura_cm);
        $this->assertSame(2150, $punto->kg_ms_ha);
        $this->assertSame(-34.6512345, $punto->latitud);
        $this->assertSame(8, $punto->imagenes);
        $this->assertSame(['a' => 1234.5, 'c' => -2.1, 'r2' => 0.998, 'grad' => 0.01], $punto->calibracion);
        $this->assertTrue($punto->activo);
    }

    public function test_subir_dos_veces_lo_mismo_no_duplica_nada(): void
    {
        $datos = $this->datos([$this->punto(self::PUNTO_1, 12.3), $this->punto(self::PUNTO_2, 15.1)]);

        $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $datos);
        $resultado = $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $datos);

        $this->assertSame(['creada' => false, 'mediciones' => ['creadas' => 0, 'actualizadas' => 2, 'total' => 2]], $resultado);
        $this->assertSame(1, Recorrida::count());
        $this->assertSame(2, Medicion::count());
    }

    public function test_las_mediciones_nuevas_se_suman_y_las_que_no_vienen_se_conservan(): void
    {
        $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $this->datos([
            $this->punto(self::PUNTO_1, 10.0),
            $this->punto(self::PUNTO_2, 20.0),
        ]));

        // La segunda subida trae solo un punto nuevo: el 1 y el 2 quedan.
        $resultado = $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $this->datos([
            $this->punto(self::PUNTO_3, 30.0),
        ]));

        $this->assertSame(['creadas' => 1, 'actualizadas' => 0, 'total' => 3], $resultado['mediciones']);
        $this->assertSame(3, Medicion::count());
        $this->assertEqualsWithDelta(20.0, Recorrida::firstOrFail()->altura_media, 0.001);
    }

    public function test_una_medicion_con_el_mismo_uuid_se_reemplaza(): void
    {
        $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $this->datos([$this->punto(self::PUNTO_1, 10.0)]));

        $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $this->datos([
            $this->punto(self::PUNTO_1, 11.5, ['latitud' => null, 'longitud' => null, 'precision_m' => null]),
        ]));

        $punto = Medicion::where('uuid', self::PUNTO_1)->sole();
        $this->assertSame(11.5, $punto->altura_cm);
        $this->assertNull($punto->latitud);
        $this->assertFalse($punto->tieneUbicacion());
    }

    public function test_actualiza_los_datos_de_la_recorrida(): void
    {
        $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $this->datos([$this->punto(self::PUNTO_1, 10.0)]));
        $bajo = Potrero::factory()->for($this->lote4->campo)->create(['nombre' => 'El Bajo']);

        $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $this->datos([], [
            'potrero_id' => $bajo->id,
            'observaciones' => 'Me equivoqué de potrero',
        ]));

        $recorrida = Recorrida::sole();
        $this->assertSame($bajo->id, $recorrida->potrero_id);
        $this->assertSame('Me equivoqué de potrero', $recorrida->observaciones);
        $this->assertSame(1, $recorrida->puntos);
    }

    public function test_no_resucita_lo_que_el_panel_anulo_o_descarto(): void
    {
        $datos = $this->datos([$this->punto(self::PUNTO_1, 10.0), $this->punto(self::PUNTO_2, 40.0)]);
        $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $datos);

        Recorrida::sole()->update(['activo' => false]);
        Medicion::where('uuid', self::PUNTO_2)->update(['activo' => false]);

        // La app reintenta (o manda `activo`, que no le corresponde): no cambia nada.
        $datos['activo'] = true;
        $datos['mediciones'][1]['activo'] = true;
        $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $datos);

        $this->assertFalse(Recorrida::sole()->activo);
        $this->assertFalse(Medicion::where('uuid', self::PUNTO_2)->sole()->activo);
        $this->assertTrue(Medicion::where('uuid', self::PUNTO_1)->sole()->activo);
    }

    public function test_no_se_puede_pisar_la_recorrida_de_otro_usuario(): void
    {
        $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $this->datos([$this->punto(self::PUNTO_1, 10.0)]));
        $admin = User::factory()->admin()->create();

        try {
            $this->sincronizador->sincronizar($admin, self::RECORRIDA, $this->datos([$this->punto(self::PUNTO_1, 99.0)]));
            $this->fail('Tenía que rechazar la subida.');
        } catch (AuthorizationException) {
        }

        $this->assertSame($this->juan->id, Recorrida::sole()->user_id);
        $this->assertSame(10.0, Medicion::sole()->altura_cm);
    }

    public function test_rechaza_un_potrero_inactivo(): void
    {
        $this->lote4->update(['activo' => false]);

        $this->assertRechazado('potrero_id', $this->datos([$this->punto(self::PUNTO_1, 10.0)]));
        $this->assertSame(0, Recorrida::count());
    }

    public function test_rechaza_un_potrero_de_un_campo_inactivo(): void
    {
        $this->lote4->campo->update(['activo' => false]);

        $this->assertRechazado('potrero_id', $this->datos([$this->punto(self::PUNTO_1, 10.0)]));
    }

    public function test_al_actualizar_tambien_exige_potrero_activo(): void
    {
        $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $this->datos([$this->punto(self::PUNTO_1, 10.0)]));
        $this->lote4->update(['activo' => false]);

        $this->assertRechazado('potrero_id', $this->datos([$this->punto(self::PUNTO_2, 12.0)]));
        $this->assertSame(1, Medicion::count());
    }

    public function test_una_medicion_de_otra_recorrida_rechaza_todo_el_pedido(): void
    {
        $ajena = Medicion::factory()->create(['uuid' => self::PUNTO_2, 'altura_cm' => 5.0]);

        $this->assertRechazado('mediciones.1.uuid', $this->datos([
            $this->punto(self::PUNTO_1, 10.0),
            $this->punto(self::PUNTO_2, 20.0),
        ]));

        // Transacción: ni la recorrida nueva ni el primer punto quedaron guardados.
        $this->assertFalse(Recorrida::where('uuid', self::RECORRIDA)->exists());
        $this->assertFalse(Medicion::where('uuid', self::PUNTO_1)->exists());
        $this->assertSame(5.0, $ajena->fresh()->altura_cm);
    }

    public function test_las_fechas_se_guardan_en_la_hora_del_servidor(): void
    {
        $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $this->datos(
            [$this->punto(self::PUNTO_1, 10.0, ['tomada_en' => '2026-10-10T12:13:05Z'])],
            ['iniciada_en' => '2026-10-10T12:12:00Z'],
        ));

        // APP_TIMEZONE = America/Argentina/Buenos_Aires (UTC−3).
        $this->assertSame('2026-10-10 09:12:00', Recorrida::sole()->getRawOriginal('iniciada_en'));
        $this->assertSame('2026-10-10 09:13:05', Medicion::sole()->getRawOriginal('tomada_en'));
    }

    private function assertRechazado(string $dato, array $datos): void
    {
        try {
            $this->sincronizador->sincronizar($this->juan, self::RECORRIDA, $datos);
            $this->fail("Tenía que rechazar «{$dato}».");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($dato, $e->errors());
        }
    }
}
