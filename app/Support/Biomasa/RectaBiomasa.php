<?php

namespace App\Support\Biomasa;

use App\Support\Formato;

/**
 * La recta de una curva de biomasa (P23): kg MS/ha = max(0, A × altura + B),
 * ajustada por mínimos cuadrados sobre las muestras (altura del disco, kg
 * MS/ha del corte). Se calcula, no se guarda (regla 8). La misma cuenta, en
 * SQL, está en Recorrida::subconsultaKg().
 */
final class RectaBiomasa
{
    private function __construct(
        /** kg MS/ha que suma cada centímetro. */
        public readonly float $a,
        /** kg MS/ha a altura cero (puede ser negativo). */
        public readonly float $b,
        /** Qué tan bien explica la recta las muestras (0 a 1); null si todos los kilos son iguales. */
        public readonly ?float $r2,
        public readonly int $muestras,
    ) {}

    /** 1 g/m² = 10 kg/ha: el peso seco del corte sobre el área del disco. */
    public static function kgMsHaDeMuestra(float $pesoSecoG, float $areaDiscoM2): float
    {
        return $pesoSecoG / $areaDiscoM2 * 10;
    }

    /**
     * @param  iterable<array{0: int|float, 1: int|float}>  $puntos  [altura cm, kg MS/ha]
     * @return self|null null sin dos alturas distintas (la pendiente no está determinada)
     */
    public static function ajustar(iterable $puntos): ?self
    {
        $n = 0;
        $sx = $sy = $sxx = $sxy = $syy = 0.0;

        foreach ($puntos as [$x, $y]) {
            $n++;
            $sx += $x;
            $sy += $y;
            $sxx += $x * $x;
            $sxy += $x * $y;
            $syy += $y * $y;
        }

        $denominador = $n * $sxx - $sx * $sx;

        if ($n < 2 || abs($denominador) < 1e-9) {
            return null;
        }

        $a = ($n * $sxy - $sx * $sy) / $denominador;
        $b = ($sy - $a * $sx) / $n;

        // R² = 1 − SSres / SStot, con SSres = SStot − A × Sxy_centrado.
        $ssTot = $syy - $sy * $sy / $n;
        $ssXy = $sxy - $sx * $sy / $n;
        $r2 = $ssTot < 1e-9 ? null : max(0.0, min(1.0, $a * $ssXy / $ssTot));

        return new self($a, $b, $r2, $n);
    }

    public function kgMsHa(float $alturaCm): float
    {
        return max(0.0, $this->a * $alturaCm + $this->b);
    }

    /** "kg MS/ha = 108 × cm + 250", para la pantalla. */
    public function texto(): string
    {
        return __('curvas.recta', [
            'a' => Formato::numero($this->a, 1),
            'signo' => $this->b < 0 ? '−' : '+',
            'b' => Formato::numero(abs($this->b)),
        ]);
    }

    /** "0,97"; sin R², "—". */
    public function textoR2(): string
    {
        return $this->r2 === null ? '—' : Formato::numero($this->r2, 2);
    }
}
