<?php

namespace App\Support\Reportes;

/**
 * El eje de un gráfico (D73): un techo "redondo" por encima del máximo
 * (1, 2, 2,5 o 5 por una potencia de 10) y sus marcas, para que las líneas
 * de referencia caigan en números que se leen (0 / 50.000 / 100.000).
 */
final class Escala
{
    public const DIVISIONES = 4;

    public static function techo(float $maximo): float
    {
        if ($maximo <= 0) {
            return 1.0;
        }

        $potencia = 10 ** floor(log10($maximo));

        foreach ([1, 2, 2.5, 5, 10] as $paso) {
            if ($maximo <= $paso * $potencia) {
                return $paso * $potencia;
            }
        }

        return 10 * $potencia;
    }

    /** @return list<float> de mayor a menor, del techo al cero */
    public static function marcas(float $techo): array
    {
        return array_map(fn (int $i) => $techo * $i / self::DIVISIONES, range(self::DIVISIONES, 0));
    }

    /** 1.250.000 → "1,25 M"; 85.000 → "85 mil"; 900 → "900". Para los rótulos del eje. */
    public static function abreviar(float $valor): string
    {
        return match (true) {
            $valor >= 1_000_000 => rtrim(rtrim(number_format($valor / 1_000_000, 2, ',', '.'), '0'), ',').' M',
            $valor >= 1_000 => rtrim(rtrim(number_format($valor / 1_000, 1, ',', '.'), '0'), ',').' mil',
            default => number_format($valor, 0, ',', '.'),
        };
    }
}
