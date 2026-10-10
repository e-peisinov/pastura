<?php

namespace App\Support\Geo;

/**
 * Superficie de un contorno GeoJSON (P5), en hectáreas.
 *
 * Área sobre la esfera de radio 6.378.137 m con la fórmula de Chamberlain y
 * Duquette, la misma que usa Leaflet (`L.GeometryUtil.geodesicArea`): el
 * número del panel coincide con el que se ve al dibujar en el mapa. Para un
 * potrero el error contra el elipsoide es despreciable.
 */
final class Superficie
{
    private const RADIO_M = 6378137.0;

    /**
     * @param  array<string, mixed>|null  $geometria  Polygon o MultiPolygon, coordenadas [lon, lat]
     */
    public static function hectareas(?array $geometria): ?float
    {
        $poligonos = match ($geometria['type'] ?? null) {
            'Polygon' => [$geometria['coordinates'] ?? []],
            'MultiPolygon' => $geometria['coordinates'] ?? [],
            default => [],
        };

        $total = 0.0;
        $hayAnillos = false;

        foreach ($poligonos as $anillos) {
            foreach (array_values($anillos) as $i => $anillo) {
                if (! is_array($anillo) || count($anillo) < 3) {
                    continue;
                }

                $hayAnillos = true;
                $area = abs(self::areaAnillo($anillo));
                // El primer anillo es el borde; los siguientes, huecos.
                $total += $i === 0 ? $area : -$area;
            }
        }

        return $hayAnillos ? max(0.0, $total) / 10000 : null;
    }

    /** @param  list<array{0: float|int, 1: float|int}>  $anillo */
    private static function areaAnillo(array $anillo): float
    {
        $n = count($anillo);
        $suma = 0.0;

        for ($i = 0; $i < $n; $i++) {
            [$lon1, $lat1] = $anillo[$i];
            [$lon2, $lat2] = $anillo[($i + 1) % $n];

            $suma += deg2rad($lon2 - $lon1) * (2 + sin(deg2rad($lat1)) + sin(deg2rad($lat2)));
        }

        return $suma * self::RADIO_M * self::RADIO_M / 2;
    }
}
