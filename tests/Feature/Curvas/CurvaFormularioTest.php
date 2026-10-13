<?php

namespace Tests\Feature\Curvas;

use App\Livewire\Curvas\CurvaFormulario;
use App\Models\Auditoria;
use App\Models\Campo;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\Potrero;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alta y edición de una curva de biomasa (P23): las muestras con coma
 * decimal, la recta en vivo, el mínimo de muestras y que una nueva reemplaza
 * a la vigente de ese potrero y ese estado.
 */
class CurvaFormularioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Potrero $lote4;

    private EstadoFenologico $rebrote;

    /** (5, 8 g), (10, 14 g), (15, 17 g), (20, 25 g) → A = 108, B = 250 (RectaBiomasaTest). */
    private const MUESTRAS = [
        ['altura' => '5', 'peso' => '8'],
        ['altura' => '10', 'peso' => '14'],
        ['altura' => '15', 'peso' => '17'],
        ['altura' => '20', 'peso' => '25'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->lote4 = Potrero::factory()->for(Campo::factory()->create(['nombre' => 'La Esperanza']))->create(['nombre' => 'Lote 4']);
        $this->rebrote = EstadoFenologico::factory()->create(['nombre' => 'Rebrote']);
    }

    private function formulario(array $parametros = [])
    {
        return Livewire::actingAs($this->admin)->test(CurvaFormulario::class, $parametros)
            ->set('potrero_id', $this->lote4->id)
            ->set('estado_fenologico_id', $this->rebrote->id)
            ->set('fecha', '2026-09-12');
    }

    public function test_alta_con_muestras_y_coma_decimal(): void
    {
        $this->formulario()
            ->set('muestras', [
                ['altura' => '5', 'peso' => '8,0'],
                ['altura' => '10,0', 'peso' => '14'],
                ['altura' => '', 'peso' => ''],
                ['altura' => '15', 'peso' => '17'],
                ['altura' => '20', 'peso' => '25'],
            ])
            ->assertSee('kg MS/ha = 108,0 × cm + 250')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'exito')
            ->assertDispatched('curva-guardada');

        $curva = CurvaBiomasa::query()->sole();
        $this->assertSame($this->lote4->id, $curva->potrero_id);
        $this->assertSame($this->rebrote->id, $curva->estado_fenologico_id);
        $this->assertSame('2026-09-12', $curva->fecha->toDateString());
        $this->assertTrue($curva->activo);
        // La fila vacía no se guarda.
        $this->assertSame([[5.0, 8.0], [10.0, 14.0], [15.0, 17.0], [20.0, 25.0]], $curva->muestras->map(fn ($m) => [$m->altura_cm, $m->peso_seco_g])->all());
        $this->assertEqualsWithDelta(108.0, $curva->recta()->a, 0.0001);
    }

    public function test_arranca_con_el_potrero_y_el_estado_del_listado_y_cinco_filas(): void
    {
        Livewire::actingAs($this->admin)->test(CurvaFormulario::class, ['potreroId' => $this->lote4->id, 'estadoId' => $this->rebrote->id])
            ->assertSet('potrero_id', $this->lote4->id)
            ->assertSet('estado_fenologico_id', $this->rebrote->id)
            ->assertCount('muestras', CurvaFormulario::FILAS_INICIALES)
            ->call('agregarMuestra')
            ->assertCount('muestras', CurvaFormulario::FILAS_INICIALES + 1)
            ->call('quitarMuestra', 0)
            ->assertCount('muestras', CurvaFormulario::FILAS_INICIALES);
    }

    public function test_hacen_falta_tres_muestras_y_dos_alturas_distintas(): void
    {
        $this->formulario()
            ->set('muestras', [['altura' => '5', 'peso' => '8'], ['altura' => '10', 'peso' => '14']])
            ->call('guardar')
            ->assertHasErrors(['muestras'])
            ->assertSee('Hacen falta al menos 3 muestras completas.');

        $this->formulario()
            ->set('muestras', [['altura' => '12', 'peso' => '8'], ['altura' => '12', 'peso' => '14'], ['altura' => '12', 'peso' => '15']])
            ->call('guardar')
            ->assertHasErrors(['muestras']);

        $this->assertSame(0, CurvaBiomasa::count());
    }

    public function test_una_fila_a_medias_o_fuera_de_rango_marca_esa_fila(): void
    {
        $this->formulario()
            ->set('muestras', [...self::MUESTRAS, ['altura' => '12', 'peso' => ''], ['altura' => 'doce', 'peso' => '3'], ['altura' => '8', 'peso' => '0']])
            ->call('guardar')
            ->assertHasErrors(['muestras.4.peso', 'muestras.5.altura', 'muestras.6.peso']);

        $this->assertSame(0, CurvaBiomasa::count());
    }

    public function test_potrero_estado_y_fecha_son_obligatorios_y_la_fecha_no_es_futura(): void
    {
        Livewire::actingAs($this->admin)->test(CurvaFormulario::class)
            ->set('fecha', now()->addDay()->toDateString())
            ->set('muestras', self::MUESTRAS)
            ->call('guardar')
            ->assertHasErrors(['potrero_id', 'estado_fenologico_id', 'fecha']);
    }

    public function test_no_ofrece_estados_ni_potreros_dados_de_baja(): void
    {
        $viejo = EstadoFenologico::factory()->inactivo()->create();
        $cerrado = Potrero::factory()->inactivo()->create();

        $this->formulario()
            ->set('estado_fenologico_id', $viejo->id)
            ->set('potrero_id', $cerrado->id)
            ->set('muestras', self::MUESTRAS)
            ->call('guardar')
            ->assertHasErrors(['potrero_id', 'estado_fenologico_id']);
    }

    /** La nueva queda vigente; el formulario avisa antes y el toast dice a cuál reemplazó. */
    public function test_una_curva_nueva_reemplaza_a_la_vigente(): void
    {
        $vieja = CurvaBiomasa::factory()->recta(50, 0)->create([
            'potrero_id' => $this->lote4->id,
            'estado_fenologico_id' => $this->rebrote->id,
            'fecha' => '2026-03-01',
        ]);

        $this->formulario()
            ->assertSee('Al guardar, esta curva reemplaza a la vigente del 01/03/2026')
            ->set('muestras', self::MUESTRAS)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', mensaje: 'Curva guardada. Reemplaza a la del 01/03/2026.');

        $this->assertFalse($vieja->fresh()->activo);
        $this->assertTrue(CurvaBiomasa::query()->latest('id')->first()->activo);
    }

    /** Editar reemplaza las muestras, recalcula la recta y deja en la auditoría la recta de antes y la de después. */
    public function test_editar_cambia_las_muestras_y_audita_la_recta(): void
    {
        $curva = CurvaBiomasa::factory()->recta(100, 0)->create([
            'potrero_id' => $this->lote4->id,
            'estado_fenologico_id' => $this->rebrote->id,
        ]);

        Livewire::actingAs($this->admin)->test(CurvaFormulario::class, ['curvaId' => $curva->id])
            ->assertSet('muestras', [['altura' => '10', 'peso' => '10'], ['altura' => '20', 'peso' => '20'], ['altura' => '30', 'peso' => '30']])
            ->set('muestras', self::MUESTRAS)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', mensaje: 'Curva modificada: los kg MS/ha se recalcularon.');

        $curva->refresh();
        $this->assertCount(4, $curva->muestras);
        $this->assertTrue($curva->activo);
        $this->assertStringContainsString(
            'recta de kg MS/ha = 100,0 × cm + 0 a kg MS/ha = 108,0 × cm + 250',
            Auditoria::query()->latest('id')->first()->descripcion(),
        );
    }

    public function test_cerrar_con_cambios_pide_confirmacion(): void
    {
        $this->formulario()
            ->set('muestras.0.altura', '12')
            ->call('intentarCerrar')
            ->assertSet('confirmandoDescarte', true)
            ->assertNotDispatched('cerrar-curva-formulario')
            ->call('descartarCambios')
            ->assertDispatched('cerrar-curva-formulario');
    }

    public function test_pide_editar_en_curvas(): void
    {
        Livewire::actingAs(User::factory()->create())->test(CurvaFormulario::class)->assertForbidden();
    }
}
