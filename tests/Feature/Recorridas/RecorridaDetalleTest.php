<?php

namespace Tests\Feature\Recorridas;

use App\Enums\Nivel;
use App\Enums\Rol;
use App\Livewire\Recorridas\RecorridaDetalle;
use App\Livewire\Recorridas\Recorridas;
use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\TipoPastura;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La ficha de una recorrida: resumen calculado de los puntos activos, mapa,
 * tabla de puntos y las acciones (descartar un punto, anular, CSV), que piden
 * Editar en Recorridas y respetan la pertenencia.
 */
class RecorridaDetalleTest extends TestCase
{
    use RefreshDatabase;

    private const CUADRADO = [
        'type' => 'Polygon',
        'coordinates' => [[[-64.35, -33.12], [-64.34, -33.12], [-64.34, -33.13], [-64.35, -33.13], [-64.35, -33.12]]],
    ];

    private User $admin;

    private User $juan;

    private Recorrida $recorrida;

    private EstadoFenologico $rebrote;

    /** @var list<Medicion> */
    private array $puntos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->juan = User::factory()->create(['name' => 'Juan Pérez', 'rol' => Rol::Recorredor]);

        $potrero = Potrero::factory()
            ->for(Campo::factory()->create(['nombre' => 'La Esperanza']))
            ->create([
                'nombre' => 'Lote 4', 'contorno' => self::CUADRADO,
                'tipo_pastura_id' => TipoPastura::factory()->create()->id,
            ]);
        $this->rebrote = EstadoFenologico::factory()->create(['nombre' => 'Rebrote']);
        CurvaBiomasa::factory()->recta(150, 300)->create(['potrero_id' => $potrero->id, 'estado_fenologico_id' => $this->rebrote->id]);

        $this->recorrida = Recorrida::factory()->for($potrero)->for($this->juan)
            ->create(['iniciada_en' => '2026-10-05 08:00:00', 'dispositivo' => 'iPhone 13']);

        // Activos: 10, 12,5 y 14 cm → media 12,2; rango 10,0–14,0. Los kg salen de
        // la curva 150 × h + 300 del potrero para su estado (P23), no de lo que mandó el celular:
        // 1800, 2175 y 2400 → 2125.
        $this->puntos = [
            $this->punto('08:05:10', 10, 1800, ['precision_m' => 4.2, 'imagenes' => 10, 'dispersion_cm' => 0.35]),
            $this->punto('08:10:00', 12.5, null),
            $this->punto('08:42:30', 14, 2400),
            $this->punto('08:50:00', 40, 9000, ['activo' => false]),
        ];
    }

    private function punto(string $hora, float $altura, ?int $kg, array $mas = []): Medicion
    {
        return Medicion::factory()->for($this->recorrida)->create([
            'tomada_en' => '2026-10-05 '.$hora,
            'altura_cm' => $altura,
            'kg_ms_ha' => $kg,
            ...$mas,
        ]);
    }

    private function ficha(User $usuario)
    {
        return Livewire::actingAs($usuario)->test(RecorridaDetalle::class, ['recorridaId' => $this->recorrida->id]);
    }

    public function test_el_resumen_sale_de_los_puntos_activos(): void
    {
        $this->ficha($this->admin)
            ->assertSee('La Esperanza · Lote 4 · 05/10/2026')
            ->assertSeeInOrder([
                'Puntos', '3',
                'Altura media', '12,2 cm',
                'Mínima – máxima', '10,0 – 14,0 cm',
                'kg MS/ha medio', '2.125',
                'Horario', '08:05 – 08:42',
                'Recorredor', 'Juan Pérez',
                'Dispositivo', 'iPhone 13',
            ]);
    }

    public function test_la_tabla_de_puntos_muestra_hora_altura_kg_gps_y_lectura(): void
    {
        $this->ficha($this->admin)
            ->assertSeeInOrder(['08:05:10', '10,0 cm', '1.800', '±4,2 m', '10 imágenes · ±0,35 cm', '08:10:00', '12,5 cm', '2.175'])
            ->assertSeeHtml('data-punto="'.$this->puntos[3]->id.'"')
            ->assertSee('Descartado');
    }

    public function test_el_mapa_lleva_el_contorno_y_los_puntos_con_su_escala(): void
    {
        Medicion::factory()->for($this->recorrida)->sinUbicacion()->create(['altura_cm' => 11, 'tomada_en' => '2026-10-05 09:00:00']);

        $componente = $this->ficha($this->admin)->assertSeeHtml('data-mapa-recorrida');
        $mapa = $componente->viewData('mapa');

        $this->assertSame([['nombre' => 'Lote 4', 'contorno' => self::CUADRADO, 'resaltado' => true]], $mapa['potreros']);
        // Los cuatro con GPS (el descartado también, en gris); el que no tiene GPS no va.
        $this->assertCount(4, $mapa['puntos']);
        $this->assertSame(['min' => 10.0, 'max' => 14.0], $mapa['escala']);
        $this->assertFalse($mapa['puntos'][3]['activo']);
        $this->assertSame('08:05 · 10,0 cm', $mapa['puntos'][0]['etiqueta']);
    }

    public function test_sin_gps_no_hay_mapa(): void
    {
        Medicion::query()->update(['latitud' => null, 'longitud' => null]);

        $this->ficha($this->admin)
            ->assertDontSeeHtml('data-mapa-recorrida')
            ->assertSee(__('recorridas.detalle.sin_ubicacion'));
    }

    public function test_descartar_y_restaurar_un_punto_cambia_el_resumen(): void
    {
        $punto = $this->puntos[2]; // 14 cm

        $this->ficha($this->juan)
            ->call('descartarPunto', $punto->id)
            ->assertDispatched('toast', tipo: 'aviso')
            ->assertDispatched('recorrida-actualizada')
            ->assertSee('11,3 cm') // (10 + 12,5) / 2 = 11,25
            ->call('restaurarPunto', $punto->id)
            ->assertDispatched('toast', tipo: 'exito')
            ->assertSee('12,2 cm');

        $this->assertTrue($punto->fresh()->activo);
    }

    public function test_un_punto_de_otra_recorrida_no_se_toca(): void
    {
        $ajeno = Medicion::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        $this->ficha($this->admin)->call('descartarPunto', $ajeno->id);
    }

    public function test_anular_pide_confirmacion_y_se_puede_restaurar(): void
    {
        $this->ficha($this->juan)
            ->call('confirmarAnular')
            ->assertSet('confirmandoAnular', true)
            ->assertSee(__('recorridas.detalle.confirmar_anular'))
            ->call('cancelarAnular')
            ->assertSet('confirmandoAnular', false)
            ->call('confirmarAnular')
            ->call('anular')
            ->assertSet('confirmandoAnular', false)
            ->assertDispatched('toast', tipo: 'aviso')
            ->assertDispatched('recorrida-actualizada')
            ->assertSee('Anulada')
            ->assertSee(__('recorridas.detalle.restaurar_recorrida'));

        $this->assertFalse($this->recorrida->fresh()->activo);

        $this->ficha($this->juan)->call('restaurar')->assertDispatched('toast', tipo: 'exito');
        $this->assertTrue($this->recorrida->fresh()->activo);
    }

    public function test_anular_saca_la_recorrida_de_las_validas_del_listado(): void
    {
        $listado = Livewire::actingAs($this->admin)->test(Recorridas::class)
            ->assertSeeHtml('data-recorrida="'.$this->recorrida->id.'"');

        $this->ficha($this->admin)->call('anular');

        $listado->dispatch('recorrida-actualizada')
            ->assertDontSeeHtml('data-recorrida="'.$this->recorrida->id.'"');
    }

    public function test_exporta_la_recorrida_en_csv(): void
    {
        $componente = $this->ficha($this->juan)
            ->call('exportar')
            ->assertFileDownloaded('recorrida-'.$this->recorrida->id.'-lote-4-2026-10-05.csv');

        $lineas = explode("\r\n", trim(base64_decode($componente->effects['download']['content'])));
        $this->assertCount(5, $lineas); // cabecera + 4 puntos (también el descartado, marcado)
    }

    public function test_el_recorredor_no_abre_las_recorridas_de_otro(): void
    {
        $otro = User::factory()->create(['rol' => Rol::Recorredor]);

        $this->expectException(ModelNotFoundException::class);
        $this->ficha($otro);
    }

    public function test_con_ver_solamente_consulta_pero_no_cambia_nada(): void
    {
        $lector = User::factory()->admin()->conNivel('recorridas', Nivel::Ver)->create();

        $this->ficha($lector)
            ->assertSee('12,2 cm')
            ->assertDontSee(__('recorridas.detalle.anular'))
            ->assertDontSee(__('recorridas.exportar'))
            ->assertDontSeeHtml('descartarPunto(')
            ->call('descartarPunto', $this->puntos[0]->id)->assertForbidden();

        $this->ficha($lector)->call('confirmarAnular')->assertForbidden();
        $this->ficha($lector)->call('anular')->assertForbidden();
        $this->ficha($lector)->call('restaurar')->assertForbidden();
        $this->ficha($lector)->call('exportar')->assertForbidden();

        $this->assertTrue($this->puntos[0]->fresh()->activo);
        $this->assertTrue($this->recorrida->fresh()->activo);
    }

    /**
     * P23: cambiar el estado desde la ficha cambia la curva. Con la de pleno
     * crecimiento (100 × h): (1000 + 1250 + 1400) / 3 = 1216,67 → 1.217. Un
     * estado sin curva deja la recorrida sin kilos y lo dice.
     */
    public function test_cambiar_el_estado_recalcula_los_kg(): void
    {
        $primavera = EstadoFenologico::factory()->create(['nombre' => 'Pleno crecimiento']);
        $floracion = EstadoFenologico::factory()->create(['nombre' => 'Floración']);
        CurvaBiomasa::factory()->recta(100, 0)->create(['potrero_id' => $this->recorrida->potrero_id, 'estado_fenologico_id' => $primavera->id]);

        $ficha = $this->ficha($this->admin)
            ->assertSet('estadoId', $this->rebrote->id)
            ->assertSee('2.125')
            ->set('estadoId', $primavera->id)
            ->assertDispatched('toast', mensaje: __('recorridas.toasts.estado_con_curva'))
            ->assertDispatched('recorrida-actualizada')
            ->assertSee('1.217');
        $this->assertEqualsWithDelta(1216.67, $ficha->viewData('resumen')->kgMedio, 0.01);
        $this->assertSame($primavera->id, $this->recorrida->fresh()->estado_fenologico_id);

        $ficha->set('estadoId', $floracion->id)
            ->assertDispatched('toast', mensaje: __('recorridas.toasts.estado_sin_curva'))
            ->assertSee(__('recorridas.detalle.sin_curva'))
            ->assertSeeHtml('data-sin-curva');
        $this->assertNull($this->recorrida->fresh()->kg_ms_ha_medio);

        $ficha->set('estadoId', null)->assertSee(__('recorridas.detalle.sin_estado'));
        $this->assertNull($this->recorrida->fresh()->estado_fenologico_id);
    }

    public function test_con_ver_no_se_cambia_el_estado(): void
    {
        $primavera = EstadoFenologico::factory()->create(['nombre' => 'Pleno crecimiento']);
        $lector = User::factory()->admin()->conNivel('recorridas', Nivel::Ver)->create();

        $this->ficha($lector)
            ->assertSee('Rebrote')
            ->assertDontSeeHtml('wire:model.live="estadoId"')
            ->set('estadoId', $primavera->id)
            ->assertForbidden();

        $this->assertSame($this->rebrote->id, $this->recorrida->fresh()->estado_fenologico_id);
    }

    public function test_cerrar_avisa_al_listado(): void
    {
        $this->ficha($this->admin)->call('cerrar')->assertDispatched('cerrar-recorrida-detalle');
    }
}
