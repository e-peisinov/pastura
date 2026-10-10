<?php

namespace App\Support\Geo;

/**
 * Si un punto cae dentro de un contorno GeoJSON (P5): Polygon o
 * MultiPolygon, coordenadas [lon, lat], el primer anillo de cada polígono es
 * el borde y los siguientes, huecos. Se usa para las recorridas para revisar
 * (P18): un punto fuera del contorno de su potrero.
 *
 * Trazado de rayos en el plano lon/lat: a la escala de un potrero la
 * curvatura no cambia la respuesta.
 */
final class Contorno
{
    /** @param  array<string, mixed>|null  $geometria */
    public static function contiene(?array $geometria, float $latitud, float $longitud): bool
    {
        $poligonos = match ($geometria['type'] ?? null) {
            'Polygon' => [$geometria['coordinates'] ?? []],
            'MultiPolygon' => $geometria['coordinates'] ?? [],
            default => [],
        };

        foreach ($poligonos as $anillos) {
            $anillos = array_values((array) $anillos);

            if ($anillos === [] || ! self::enAnillo($anillos[0], $longitud, $latitud)) {
                continue;
            }

            $enHueco = false;
            foreach (array_slice($anillos, 1) as $hueco) {
                if (self::enAnillo($hueco, $longitud, $latitud)) {
                    $enHueco = true;
                    break;
                }
            }

            if (! $enHueco) {
                return true;
            }
        }

        return false;
    }

    /** @param  mixed  $anillo  lista de [lon, lat] */
    private static function enAnillo(mixed $anillo, float $x, float $y): bool
    {
        if (! is_array($anillo) || count($anillo) < 3) {
            return false;
        }

        $anillo = array_values($anillo);
        $n = count($anillo);
        $dentro = false;

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $anillo[$i];
            [$xj, $yj] = $anillo[$j];

            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }
}
