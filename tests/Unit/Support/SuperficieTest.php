<?php

namespace Tests\Unit\Support;

use App\Support\Geo\Superficie;
use PHPUnit\Framework\TestCase;

/**
 * Superficie de un contorno GeoJSON en hectáreas (P5). Esfera de radio
 * 6.378.137 m, la misma que usa Leaflet para medir en el mapa, así el panel
 * y el mapa dan el mismo número.
 */
class SuperficieTest extends TestCase
{
    /** Cuadrado de 0,01° × 0,01° sobre el ecuador: (6378137 · π/180 · 0,01)² m² = 123,92 ha. */
    public function test_cuadrado_sobre_el_ecuador(): void
    {
        $poligono = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [0.01, 0], [0.01, 0.01], [0, 0.01], [0, 0]]]];

        $this->assertEqualsWithDelta(123.92, Superficie::hectareas($poligono), 0.01);
    }

    /** El mismo cuadrado en grados a la latitud de Río Cuarto se achica con el coseno: 123,92 · cos(33,125°) = 103,79 ha. */
    public function test_a_otra_latitud_se_achica_con_el_coseno(): void
    {
        $poligono = ['type' => 'Polygon', 'coordinates' => [[
            [-64.35, -33.12], [-64.34, -33.12], [-64.34, -33.13], [-64.35, -33.13], [-64.35, -33.12],
        ]]];

        $this->assertEqualsWithDelta(103.79, Superficie::hectareas($poligono), 0.05);
    }

    /** El sentido en que se dibujó el anillo no cambia el resultado. */
    public function test_no_importa_el_sentido_del_anillo(): void
    {
        $horario = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [0, 0.01], [0.01, 0.01], [0.01, 0], [0, 0]]]];

        $this->assertEqualsWithDelta(123.92, Superficie::hectareas($horario), 0.01);
    }

    /** Un hueco (un bajo, un monte) se descuenta: 123,92 − 30,98 (cuadrado de 0,005°) = 92,94 ha. */
    public function test_los_huecos_se_descuentan(): void
    {
        $poligono = ['type' => 'Polygon', 'coordinates' => [
            [[0, 0], [0.01, 0], [0.01, 0.01], [0, 0.01], [0, 0]],
            [[0.0025, 0.0025], [0.0025, 0.0075], [0.0075, 0.0075], [0.0075, 0.0025], [0.0025, 0.0025]],
        ]];

        $this->assertEqualsWithDelta(92.94, Superficie::hectareas($poligono), 0.01);
    }

    /** Un potrero partido en dos por un camino: se suman las partes. */
    public function test_un_multipoligono_suma_sus_partes(): void
    {
        $multi = ['type' => 'MultiPolygon', 'coordinates' => [
            [[[0, 0], [0.01, 0], [0.01, 0.01], [0, 0.01], [0, 0]]],
            [[[0.02, 0], [0.03, 0], [0.03, 0.01], [0.02, 0.01], [0.02, 0]]],
        ]];

        $this->assertEqualsWithDelta(247.84, Superficie::hectareas($multi), 0.02);
    }

    public function test_sin_contorno_o_con_otra_geometria_no_hay_superficie(): void
    {
        $this->assertNull(Superficie::hectareas(null));
        $this->assertNull(Superficie::hectareas(['type' => 'Point', 'coordinates' => [0, 0]]));
        $this->assertNull(Superficie::hectareas(['type' => 'Polygon', 'coordinates' => []]));
    }
}
