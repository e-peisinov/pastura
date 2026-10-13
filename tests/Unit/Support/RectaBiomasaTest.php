<?php

namespace Tests\Unit\Support;

use App\Support\Biomasa\RectaBiomasa;
use PHPUnit\Framework\TestCase;

/**
 * La recta de una curva de biomasa (P23): mínimos cuadrados sobre las
 * muestras (altura del disco, kg MS/ha del corte), su R² y los kg de un
 * punto. Los números de cada caso están hechos a mano en el docblock.
 */
class RectaBiomasaTest extends TestCase
{
    /**
     * 1 g/m² = 10 kg/ha: 15 g en el disco de 0,10 m² = 150 g/m² = 1.500 kg/ha
     * (lo que dijo Mateo: gramos × 100). Con un aro de 0,25 m², 25 g = 1.000.
     */
    public function test_los_kg_de_una_muestra_salen_del_peso_seco_y_el_area_del_disco(): void
    {
        $this->assertEqualsWithDelta(1500.0, RectaBiomasa::kgMsHaDeMuestra(15.0, 0.10), 0.0001);
        $this->assertEqualsWithDelta(1000.0, RectaBiomasa::kgMsHaDeMuestra(25.0, 0.25), 0.0001);
        $this->assertEqualsWithDelta(1234.5, RectaBiomasa::kgMsHaDeMuestra(12.345, 0.10), 0.0001);
    }

    /**
     * (5, 800), (10, 1400), (15, 1700), (20, 2500):
     * n = 4, Σx = 50, Σy = 6400, Σx² = 750, Σxy = 93500.
     * A = (4 × 93500 − 50 × 6400) / (4 × 750 − 50²) = 54000 / 500 = 108.
     * B = (6400 − 108 × 50) / 4 = 250.
     * Residuos 10, 70, −170, 90 → SSres = 42000; SStot = 1500000; R² = 0,972.
     */
    public function test_ajusta_la_recta_de_minimos_cuadrados_con_su_r2(): void
    {
        $recta = RectaBiomasa::ajustar([[5, 800], [10, 1400], [15, 1700], [20, 2500]]);

        $this->assertNotNull($recta);
        $this->assertEqualsWithDelta(108.0, $recta->a, 0.0001);
        $this->assertEqualsWithDelta(250.0, $recta->b, 0.0001);
        $this->assertEqualsWithDelta(0.972, $recta->r2, 0.0001);
        $this->assertSame(4, $recta->muestras);
    }

    /** 108 × 12 + 250 = 1546; 108 × 1 + 250 = 358. */
    public function test_los_kg_de_un_punto_salen_de_la_recta(): void
    {
        $recta = RectaBiomasa::ajustar([[5, 800], [10, 1400], [15, 1700], [20, 2500]]);

        $this->assertEqualsWithDelta(1546.0, $recta->kgMsHa(12.0), 0.0001);
        $this->assertEqualsWithDelta(358.0, $recta->kgMsHa(1.0), 0.0001);
    }

    /**
     * (10, 500), (20, 1500), (30, 2500): A = 100, B = −500, R² = 1.
     * A 3 cm da 300 − 500 = −200: los kilos nunca son negativos.
     */
    public function test_los_kg_nunca_son_negativos(): void
    {
        $recta = RectaBiomasa::ajustar([[10, 500], [20, 1500], [30, 2500]]);

        $this->assertEqualsWithDelta(100.0, $recta->a, 0.0001);
        $this->assertEqualsWithDelta(-500.0, $recta->b, 0.0001);
        $this->assertEqualsWithDelta(1.0, $recta->r2, 0.0001);
        $this->assertSame(0.0, $recta->kgMsHa(3.0));
        $this->assertEqualsWithDelta(0.0, $recta->kgMsHa(5.0), 0.0001);
    }

    public function test_sin_dos_alturas_distintas_no_hay_recta(): void
    {
        $this->assertNull(RectaBiomasa::ajustar([]));
        $this->assertNull(RectaBiomasa::ajustar([[12, 1500]]));
        // Todas a la misma altura: la pendiente no está determinada.
        $this->assertNull(RectaBiomasa::ajustar([[12, 1500], [12, 1700], [12, 1600]]));
    }

    /** Todos los kilos iguales: la recta es horizontal y el R² no dice nada (SStot = 0). */
    public function test_con_todos_los_kilos_iguales_la_recta_es_horizontal_sin_r2(): void
    {
        $recta = RectaBiomasa::ajustar([[5, 1200], [10, 1200], [15, 1200]]);

        $this->assertEqualsWithDelta(0.0, $recta->a, 0.0001);
        $this->assertEqualsWithDelta(1200.0, $recta->b, 0.0001);
        $this->assertNull($recta->r2);
    }
}
