<?php

namespace Tests\Feature\Recorridas;

use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use App\Support\Recorridas\ExportadorRecorridas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El CSV de recorridas: una fila por punto, como lo abre Excel en Argentina
 * (UTF-8 con BOM, `;`, coma decimal), igual que el exportador del origen.
 */
class ExportadorRecorridasTest extends TestCase
{
    use RefreshDatabase;

    private function lineas(string $csv): array
    {
        return explode("\r\n", trim(substr($csv, 3)));
    }

    public function test_una_fila_por_punto_con_coma_decimal_y_su_estado(): void
    {
        $potrero = Potrero::factory()->for(Campo::factory()->create(['nombre' => 'La Esperanza']))->create(['nombre' => 'Lote 4']);
        $recorredor = User::factory()->create(['name' => 'Juan Pérez']);
        $recorrida = Recorrida::factory()->for($potrero)->for($recorredor)->create(['iniciada_en' => '2026-10-05 08:00:00']);

        Medicion::factory()->for($recorrida)->create([
            'tomada_en' => '2026-10-05 08:12:00', 'altura_cm' => 12.5, 'distancia_cm' => 107.5, 'kg_ms_ha' => 2150,
            'latitud' => -33.1234567, 'longitud' => -64.3456789, 'precision_m' => 4.2, 'inclinacion_grados' => 3.1,
            'imagenes' => 10, 'dispersion_cm' => 0.35,
        ]);
        Medicion::factory()->for($recorrida)->sinUbicacion()->create([
            'tomada_en' => '2026-10-05 08:05:00', 'altura_cm' => 40, 'kg_ms_ha' => null, 'activo' => false,
            'distancia_cm' => null, 'inclinacion_grados' => null, 'dispersion_cm' => null,
        ]);

        $csv = ExportadorRecorridas::csv(Recorrida::query());
        $lineas = $this->lineas($csv);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertCount(3, $lineas);
        $this->assertStringStartsWith('Fecha;Hora;Campo;Potrero;Recorredor;"Altura (cm)"', $lineas[0]);

        // Ordenadas por hora dentro de la recorrida: primero el descartado.
        $this->assertSame("05/10/2026;08:05;\"La Esperanza\";\"Lote 4\";\"Juan Pérez\";40,0;;;;;;;10;;Descartado;{$recorrida->id}", $lineas[1]);
        $this->assertSame("05/10/2026;08:12;\"La Esperanza\";\"Lote 4\";\"Juan Pérez\";12,5;107,5;2150;-33,1234567;-64,3456789;4,2;3,1;10;0,35;Válido;{$recorrida->id}", $lineas[2]);
    }

    public function test_los_puntos_de_una_recorrida_anulada_salen_marcados(): void
    {
        $recorrida = Recorrida::factory()->anulada()->create();
        Medicion::factory()->for($recorrida)->create();

        $lineas = $this->lineas(ExportadorRecorridas::csv(Recorrida::query()));

        $this->assertStringContainsString(';"Recorrida anulada";', $lineas[1]);
    }

    public function test_respeta_la_consulta_y_ordena_por_fecha(): void
    {
        $vieja = Recorrida::factory()->create(['iniciada_en' => '2026-09-01 10:00:00']);
        $nueva = Recorrida::factory()->create(['iniciada_en' => '2026-10-01 10:00:00']);
        $otra = Recorrida::factory()->create(['iniciada_en' => '2026-10-02 10:00:00']);
        foreach ([$vieja, $nueva, $otra] as $r) {
            Medicion::factory()->for($r)->create();
        }

        $consulta = Recorrida::query()->whereKeyNot($otra->id)->orderByDesc('iniciada_en');
        $lineas = $this->lineas(ExportadorRecorridas::csv($consulta));

        $this->assertCount(3, $lineas);
        $this->assertStringEndsWith(';'.$vieja->id, $lineas[1]);
        $this->assertStringEndsWith(';'.$nueva->id, $lineas[2]);
    }

    public function test_nombres_de_archivo(): void
    {
        $this->assertSame('recorridas.csv', ExportadorRecorridas::nombreArchivo(null, null));
        $this->assertSame('recorridas-2026-09-01-al-2026-09-30.csv', ExportadorRecorridas::nombreArchivo('2026-09-01', '2026-09-30'));
        $this->assertSame('recorridas-desde-2026-09-01.csv', ExportadorRecorridas::nombreArchivo('2026-09-01', null));

        $recorrida = Recorrida::factory()
            ->for(Potrero::factory()->create(['nombre' => 'Lote 4']))
            ->create(['iniciada_en' => '2026-10-05 08:00:00']);

        $this->assertSame("recorrida-{$recorrida->id}-lote-4-2026-10-05.csv", ExportadorRecorridas::nombreArchivoDe($recorrida));
    }
}
