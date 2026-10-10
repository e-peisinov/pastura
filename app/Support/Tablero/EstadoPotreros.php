<?php

namespace App\Support\Tablero;

use App\Enums\EstadoPotrero;
use App\Models\Configuracion;
use App\Models\Potrero;
use App\Models\Recorrida;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * El estado de cada potrero activo de un campo activo (P18), calculado al
 * leer (regla 8). La referencia es la última recorrida activa que tiene al
 * menos un punto activo: una anulada o con todos los puntos descartados no
 * dice nada de la altura.
 *
 * Son dos consultas para todos los potreros, no una por potrero: los
 * potreros con el id de su última recorrida como subconsulta, y esas
 * recorridas con su resumen.
 */
final class EstadoPotreros
{
    /** @return Collection<int, FilaPotrero> */
    public static function filas(): Collection
    {
        $potreros = Potrero::query()
            ->where('potreros.activo', true)
            ->whereHas('campo', fn ($q) => $q->where('activo', true))
            ->addSelect(['ultima_recorrida_id' => Recorrida::query()
                ->select('recorridas.id')
                ->whereColumn('recorridas.potrero_id', 'potreros.id')
                ->where('recorridas.activo', true)
                ->whereHas('mediciones', fn ($q) => $q->where('activo', true))
                ->orderByDesc('recorridas.iniciada_en')
                ->orderByDesc('recorridas.id')
                ->limit(1)])
            ->with(['campo', 'tipoPastura'])
            ->get();

        $ultimas = Recorrida::query()
            ->conResumen()
            ->whereIn('recorridas.id', $potreros->pluck('ultima_recorrida_id')->filter())
            ->get()
            ->keyBy('id');

        $hoy = Carbon::today();
        $diasSinMedir = Configuracion::instancia()->dias_sin_medir;

        return $potreros->map(function (Potrero $potrero) use ($ultimas, $hoy, $diasSinMedir) {
            $ultima = $ultimas->get($potrero->ultima_recorrida_id);
            $dias = $ultima === null ? null : (int) $ultima->iniciada_en->copy()->startOfDay()->diffInDays($hoy);

            return new FilaPotrero(
                $potrero,
                $ultima,
                $dias,
                EstadoPotrero::de($ultima?->altura_media, $potrero->tipoPastura),
                $dias !== null && $dias > $diasSinMedir,
            );
        })->values();
    }
}
