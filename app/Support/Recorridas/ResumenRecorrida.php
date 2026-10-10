<?php

namespace App\Support\Recorridas;

use App\Models\Medicion;
use App\Models\TipoPastura;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * El resumen de la ficha de una recorrida, calculado de sus mediciones
 * activas (dominio.md, regla 1; regla dura 8): cantidad de puntos, altura
 * media, mínima y máxima, kg MS/ha medio (con la fórmula vigente del tipo
 * de pastura, P16) y la hora del primer y el último punto. Trabaja sobre las
 * mediciones ya cargadas, sin otra consulta.
 */
final class ResumenRecorrida
{
    private function __construct(
        public readonly int $puntos,
        public readonly ?float $alturaMedia,
        public readonly ?float $alturaMinima,
        public readonly ?float $alturaMaxima,
        public readonly ?float $kgMedio,
        public readonly ?CarbonInterface $primera,
        public readonly ?CarbonInterface $ultima,
    ) {}

    /** @param  Collection<int, Medicion>  $mediciones */
    public static function de(Collection $mediciones, ?TipoPastura $tipo): self
    {
        $activas = $mediciones->filter(fn (Medicion $m) => $m->activo);

        if ($activas->isEmpty()) {
            return new self(0, null, null, null, null, null, null);
        }

        $alturas = $activas->map(fn (Medicion $m) => (float) $m->altura_cm);
        $kgs = $activas->map(fn (Medicion $m) => $m->kgMsHaSegun($tipo))->filter(fn (?float $kg) => $kg !== null);
        $horas = $activas->pluck('tomada_en')->sort()->values();

        return new self(
            $activas->count(),
            $alturas->avg(),
            $alturas->min(),
            $alturas->max(),
            $kgs->isEmpty() ? null : (float) $kgs->avg(),
            $horas->first(),
            $horas->last(),
        );
    }
}
