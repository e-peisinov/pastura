<?php

namespace App\Support\Tablero;

use App\Enums\MotivoRevision;
use App\Models\Configuracion;
use App\Models\Medicion;
use App\Models\Recorrida;
use App\Support\Geo\Contorno;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Las recorridas para revisar (P18): activas, de los últimos DIAS días, con
 * menos puntos activos que el mínimo de Configuración, con algún punto
 * activo sin GPS o con algún punto activo fuera del contorno de su potrero
 * (sin contorno no hay "fuera"). Los puntos descartados no cuentan: ya
 * están revisados.
 *
 * Se mira una ventana y no toda la historia porque no hay una marca de
 * "revisada": una recorrida vieja que nadie va a tocar no tiene que seguir
 * apareciendo para siempre.
 */
final class RevisionRecorridas
{
    public const DIAS = 30;

    /** @return Collection<int, FilaRevision> la más nueva primero */
    public static function filas(): Collection
    {
        $minimo = Configuracion::instancia()->puntos_minimos;

        return Recorrida::query()
            ->where('recorridas.activo', true)
            ->where('recorridas.iniciada_en', '>=', Carbon::today()->subDays(self::DIAS - 1))
            ->conResumen()
            ->with(['potrero.campo', 'user', 'mediciones' => fn ($q) => $q->where('activo', true)])
            ->orderByDesc('recorridas.iniciada_en')
            ->orderByDesc('recorridas.id')
            ->get()
            ->map(fn (Recorrida $recorrida) => new FilaRevision($recorrida, self::motivos($recorrida, $minimo)))
            ->filter(fn (FilaRevision $fila) => $fila->motivos !== [])
            ->values();
    }

    /** @return list<MotivoRevision> */
    private static function motivos(Recorrida $recorrida, int $minimo): array
    {
        $puntos = $recorrida->mediciones;
        $contorno = $recorrida->potrero->contorno;
        $motivos = [];

        if ($puntos->count() < $minimo) {
            $motivos[] = MotivoRevision::PocosPuntos;
        }

        if ($puntos->contains(fn (Medicion $m) => ! $m->tieneUbicacion())) {
            $motivos[] = MotivoRevision::SinGps;
        }

        if ($contorno !== null && $puntos->contains(
            fn (Medicion $m) => $m->tieneUbicacion() && ! Contorno::contiene($contorno, $m->latitud, $m->longitud),
        )) {
            $motivos[] = MotivoRevision::FueraDelContorno;
        }

        return $motivos;
    }
}
