<?php

namespace Tests\Feature\Reportes;

use App\Enums\Nivel;
use App\Livewire\Reportes\Reportes as PantallaReportes;
use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\TipoPastura;
use App\Models\User;
use App\Support\Impresiones\ReportesPdf;
use App\Support\Reportes\ExportadorReportes;
use App\Support\Reportes\Reportes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de Reportes (P18, P21): permisos, período, campo y potrero en
 * la URL, los bloques, y la salida en CSV y en PDF.
 */
class ReportesPantallaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Campo $esperanza;

    private Potrero $lote4;

    private Potrero $bajo;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 15:00:00');
        $this->admin = User::factory()->admin()->create();
        $juan = User::factory()->create(['name' => 'Juan Pérez']);
        $alfalfa = TipoPastura::factory()->create();

        $this->esperanza = Campo::factory()->create(['nombre' => 'La Esperanza']);
        Campo::factory()->create(['nombre' => 'San José']);
        $this->lote4 = Potrero::factory()->conContorno()->for($this->esperanza)->create(['nombre' => 'Lote 4', 'tipo_pastura_id' => $alfalfa->id]);
        $this->bajo = Potrero::factory()->for($this->esperanza)->create(['nombre' => 'El Bajo', 'tipo_pastura_id' => $alfalfa->id]);
        // kg = 100 × h (P23): una curva vigente en cada potrero, antes que sus recorridas.
        $estado = EstadoFenologico::factory()->create();
        foreach ([$this->lote4, $this->bajo] as $potrero) {
            CurvaBiomasa::factory()->recta(100, 0)->create(['potrero_id' => $potrero->id, 'estado_fenologico_id' => $estado->id]);
        }

        foreach (['2026-09-05 08:00' => 11, '2026-09-15 08:00' => 17] as $fecha => $altura) {
            $recorrida = Recorrida::factory()->for($this->lote4)->for($juan)->create(['iniciada_en' => $fecha]);
            Medicion::factory()->for($recorrida)->create(['altura_cm' => $altura, 'tomada_en' => $fecha]);
        }
        $recorrida = Recorrida::factory()->for($this->bajo)->for($juan)->create(['iniciada_en' => '2026-09-10 08:00']);
        Medicion::factory()->for($recorrida)->create(['altura_cm' => 8, 'tomada_en' => '2026-09-10 08:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_es_del_administrador_y_con_ver_alcanza(): void
    {
        $this->actingAs($this->admin)->get(route('reportes'))->assertOk()->assertSee('Reportes');
        $this->actingAs(User::factory()->create())->get(route('reportes'))->assertForbidden();
        $this->actingAs(User::factory()->conNivel('reportes', Nivel::Ver)->create())->get(route('reportes'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('reportes.pdf'))->assertForbidden();
    }

    public function test_abre_en_los_ultimos_tres_meses_con_el_primer_campo_y_su_potrero_mas_recorrido(): void
    {
        Livewire::actingAs($this->admin)->test(PantallaReportes::class)
            ->assertSet('desde', '2026-08-01')
            ->assertSet('hasta', '2026-10-10')
            ->assertSet('campo', $this->esperanza->id)
            ->assertSet('potrero', $this->lote4->id)
            ->assertSeeHtml('data-atajo="trimestre"')
            ->assertSeeInOrder(['Recorridas', '3', 'Puntos', '3', 'Potreros medidos', '2'])
            ->assertSeeInOrder(['Evolución de un potrero', '05/09/2026 · 11,0 cm', '1.100 kg MS/ha', 'primera del período', '15/09/2026 · 17,0 cm', 'creció 0,60 cm/día', '60,0 kg MS/ha/día'])
            ->assertSeeInOrder(['Potreros del campo', 'Lote 4', '17,0 cm', 'El Bajo', '8,0 cm'])
            ->assertSeeInOrder(['Disponibilidad forrajera', 'La Esperanza', '1 de 2 potreros con dato'])
            ->assertSeeInOrder(['Actividad por recorredor', 'Juan Pérez', '3 puntos']);
    }

    public function test_cambiar_de_campo_elige_su_potrero_y_todo_viaja_en_la_url(): void
    {
        $sanJose = Campo::query()->where('nombre', 'San José')->sole();
        $norte = Potrero::factory()->for($sanJose)->create(['nombre' => 'Norte']);

        $componente = Livewire::actingAs($this->admin)
            ->withQueryParams(['desde' => '2026-09-01', 'hasta' => '2026-09-30', 'campo' => $this->esperanza->id, 'potrero' => $this->bajo->id])
            ->test(PantallaReportes::class)
            ->assertSet('potrero', $this->bajo->id)
            ->assertSeeHtml(e(route('reportes.pdf', ['desde' => '2026-09-01', 'hasta' => '2026-09-30', 'campo' => $this->esperanza->id, 'potrero' => $this->bajo->id])))
            ->set('campo', $sanJose->id)
            ->assertSet('potrero', $norte->id)
            ->assertSee('Este potrero no tiene recorridas en el período.');

        // Un potrero que no es del campo elegido se reemplaza por uno del campo.
        $componente->set('potrero', $this->lote4->id)->assertSet('potrero', $norte->id);
    }

    public function test_el_csv_tiene_una_seccion_por_bloque(): void
    {
        $csv = ExportadorReportes::csv(new Reportes('2026-09-01', '2026-09-30', $this->esperanza->id, $this->lote4->id));
        $lineas = explode("\r\n", substr($csv, 3));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame('"Resumen del 01/09/2026 al 30/09/2026 (comparado con el 02/08/2026 al 31/08/2026)"', $lineas[0]);
        $this->assertContains('Recorridas;3;0;', $lineas);
        $this->assertContains('"Evolución de La Esperanza · Lote 4"', $lineas);
        $this->assertContains('05/09/2026;11,0;1100;;', $lineas);
        $this->assertContains('15/09/2026;17,0;1700;0,60;60,0', $lineas);
        $this->assertContains('"Potreros de La Esperanza"', $lineas);
        $this->assertContains('"Juan Pérez";3;3', $lineas);

        Livewire::actingAs($this->admin)->test(PantallaReportes::class)
            ->call('exportar')
            ->assertFileDownloaded('reportes-2026-08-01-al-2026-10-10.csv');
    }

    public function test_el_pdf_dice_lo_mismo_que_el_csv(): void
    {
        $reportes = new Reportes('2026-09-01', '2026-09-30', $this->esperanza->id, $this->lote4->id);
        $html = ReportesPdf::html($reportes);

        $this->assertStringContainsString('Del 01/09/2026 al 30/09/2026', $html);
        $this->assertStringContainsString('data-seccion="evolucion"', $html);
        $this->assertStringContainsString('Evolución de La Esperanza · Lote 4', $html);
        $this->assertStringContainsString('1.700', $html);

        $respuesta = $this->actingAs($this->admin)->get(route('reportes.pdf', ['desde' => '2026-09-01', 'hasta' => '2026-09-30']));
        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('reportes-2026-09-01-al-2026-09-30.pdf', $respuesta->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }
}
