<?php

namespace Tests\Unit\Support;

use App\Models\Medicion;
use App\Support\Biomasa\RectaBiomasa;
use App\Support\Recorridas\ResumenRecorrida;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * El resumen de la ficha sale de las mediciones activas (dominio.md, regla 1;
 * regla dura 8): puntos, media, mínima–máxima, kg medio y horario.
 */
class ResumenRecorridaTest extends TestCase
{
    private function punto(float $altura, ?int $kg, string $hora, bool $activo = true): Medicion
    {
        return new Medicion([
            'altura_cm' => $altura,
            'kg_ms_ha' => $kg,
            'tomada_en' => Carbon::parse('2026-10-05 '.$hora),
            'activo' => $activo,
        ]);
    }

    /**
     * Activos: 10, 12,5 y 14 cm → media 36,5 / 3 = 12,17; rango 10–14.
     * kg con la recta 150 × h + 300 (P23): 1800, 2175 y 2400 → 2125; lo que mandó el
     * celular no cuenta. El descartado (40 cm, 07:50) no cuenta en nada,
     * tampoco en el horario.
     */
    public function test_resume_solo_los_puntos_activos(): void
    {
        $resumen = ResumenRecorrida::de(collect([
            $this->punto(40, 9000, '07:50', activo: false),
            $this->punto(12.5, null, '08:10'),
            $this->punto(10, 1800, '08:05'),
            $this->punto(14, 2400, '08:42'),
        ]), self::recta());

        $this->assertSame(3, $resumen->puntos);
        $this->assertEqualsWithDelta(12.1667, $resumen->alturaMedia, 0.0001);
        $this->assertSame(10.0, $resumen->alturaMinima);
        $this->assertSame(14.0, $resumen->alturaMaxima);
        $this->assertSame(2125.0, $resumen->kgMedio);
        $this->assertSame('08:05', $resumen->primera->format('H:i'));
        $this->assertSame('08:42', $resumen->ultima->format('H:i'));
    }

    public function test_sin_puntos_activos_no_hay_promedios(): void
    {
        $resumen = ResumenRecorrida::de(collect([$this->punto(20, 3000, '09:00', activo: false)]), self::recta());

        $this->assertSame(0, $resumen->puntos);
        $this->assertNull($resumen->alturaMedia);
        $this->assertNull($resumen->alturaMinima);
        $this->assertNull($resumen->alturaMaxima);
        $this->assertNull($resumen->kgMedio);
        $this->assertNull($resumen->primera);
        $this->assertNull($resumen->ultima);
    }

    /** Sin curva (sin recta) el kg medio queda vacío, aunque el celular haya mandado kg. */
    public function test_sin_recta_el_kg_medio_queda_vacio(): void
    {
        $puntos = collect([$this->punto(8, 1500, '10:00'), $this->punto(9, 1600, '10:01')]);

        $this->assertSame(8.5, ResumenRecorrida::de($puntos, null)->alturaMedia);
        $this->assertNull(ResumenRecorrida::de($puntos, null)->kgMedio);
    }

    /** kg = 150 × h + 300, ajustada sobre puntos exactos. */
    private static function recta(): RectaBiomasa
    {
        return RectaBiomasa::ajustar([[10, 1800], [20, 3300], [30, 4800]]);
    }
}
