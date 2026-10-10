<?php

namespace Tests\Unit\Support;

use App\Support\Geo\Contorno;
use PHPUnit\Framework\TestCase;

/**
 * Si un punto GPS cae dentro del contorno de su potrero (P18, "fuera del
 * contorno"). GeoJSON: coordenadas [lon, lat]; el primer anillo es el borde
 * y los siguientes, huecos.
 */
class ContornoTest extends TestCase
{
    /** Un cuadrado de 0,01° con un hueco de 0,002° en el medio. */
    private const CON_HUECO = [
        'type' => 'Polygon',
        'coordinates' => [
            [[-64.35, -33.12], [-64.34, -33.12], [-64.34, -33.13], [-64.35, -33.13], [-64.35, -33.12]],
            [[-64.346, -33.124], [-64.344, -33.124], [-64.344, -33.126], [-64.346, -33.126], [-64.346, -33.124]],
        ],
    ];

    public function test_dentro_y_fuera_de_un_poligono_con_hueco(): void
    {
        $this->assertTrue(Contorno::contiene(self::CON_HUECO, -33.121, -64.349));
        $this->assertFalse(Contorno::contiene(self::CON_HUECO, -33.125, -64.345), 'en el hueco');
        $this->assertFalse(Contorno::contiene(self::CON_HUECO, -33.125, -64.33), 'al este');
        $this->assertFalse(Contorno::contiene(self::CON_HUECO, -33.10, -64.345), 'al norte');
    }

    public function test_multipoligono_cualquiera_de_sus_partes(): void
    {
        $dos = [
            'type' => 'MultiPolygon',
            'coordinates' => [
                [[[-64.35, -33.12], [-64.34, -33.12], [-64.34, -33.13], [-64.35, -33.13], [-64.35, -33.12]]],
                [[[-64.30, -33.12], [-64.29, -33.12], [-64.29, -33.13], [-64.30, -33.13], [-64.30, -33.12]]],
            ],
        ];

        $this->assertTrue(Contorno::contiene($dos, -33.125, -64.345));
        $this->assertTrue(Contorno::contiene($dos, -33.125, -64.295));
        $this->assertFalse(Contorno::contiene($dos, -33.125, -64.32), 'entre las dos partes');
    }

    public function test_un_poligono_concavo(): void
    {
        // Una L: el rincón de arriba a la derecha queda afuera.
        $ele = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [2, 0], [2, 1], [1, 1], [1, 2], [0, 2], [0, 0]]]];

        $this->assertTrue(Contorno::contiene($ele, 0.5, 1.5));
        $this->assertTrue(Contorno::contiene($ele, 1.5, 0.5));
        $this->assertFalse(Contorno::contiene($ele, 1.5, 1.5));
    }

    public function test_sin_geometria_no_contiene_nada(): void
    {
        $this->assertFalse(Contorno::contiene(null, -33.125, -64.345));
        $this->assertFalse(Contorno::contiene(['type' => 'Point', 'coordinates' => [-64.345, -33.125]], -33.125, -64.345));
    }
}
