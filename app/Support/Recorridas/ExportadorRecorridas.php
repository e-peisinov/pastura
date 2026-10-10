<?php

namespace App\Support\Recorridas;

use App\Models\Medicion;
use App\Models\Recorrida;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Las recorridas en CSV, una fila por punto (molde: ExportadorComprobantes
 * del origen, D41). Sale como lo abre Excel en Argentina: UTF-8 con BOM,
 * separador `;` y coma decimal. Van también los puntos descartados y los de
 * las recorridas anuladas, marcados en la columna Estado: el CSV es el dato
 * crudo, el filtro lo hace quien lo abre.
 */
final class ExportadorRecorridas
{
    public const SEPARADOR = ';';

    /** @return list<string> */
    public static function columnas(): array
    {
        return array_values(__('recorridas.csv.columnas'));
    }

    /** @param  Builder<Recorrida>  $consulta  las recorridas ya filtradas */
    public static function csv(Builder $consulta): string
    {
        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, self::columnas(), self::SEPARADOR, '"', '\\', "\r\n");

        $recorridas = $consulta->clone()
            ->with(['potrero.campo', 'user', 'mediciones' => fn ($q) => $q->orderBy('tomada_en')->orderBy('id')])
            ->reorder()
            ->orderBy('recorridas.iniciada_en')
            ->orderBy('recorridas.id')
            ->lazy(100);

        foreach ($recorridas as $recorrida) {
            foreach ($recorrida->mediciones as $medicion) {
                fputcsv($salida, self::fila($recorrida, $medicion), self::SEPARADOR, '"', '\\', "\r\n");
            }
        }

        rewind($salida);
        $csv = stream_get_contents($salida);
        fclose($salida);

        return $csv;
    }

    /** `recorridas-2026-09-01-al-2026-09-30.csv`, `recorridas-desde-2026-09-01.csv` o `recorridas.csv`. */
    public static function nombreArchivo(?string $desde, ?string $hasta): string
    {
        $periodo = match (true) {
            $desde !== null && $hasta !== null => $desde.'-al-'.$hasta,
            $desde !== null => 'desde-'.$desde,
            $hasta !== null => 'hasta-'.$hasta,
            default => '',
        };

        return 'recorridas'.($periodo !== '' ? '-'.$periodo : '').'.csv';
    }

    /** `recorrida-12-lote-4-2026-10-05.csv`. */
    public static function nombreArchivoDe(Recorrida $recorrida): string
    {
        return 'recorrida-'.$recorrida->id.'-'.Str::slug($recorrida->potrero->nombre).'-'.$recorrida->iniciada_en->toDateString().'.csv';
    }

    /** @return list<string> */
    private static function fila(Recorrida $recorrida, Medicion $medicion): array
    {
        $estado = match (true) {
            ! $recorrida->activo => __('recorridas.csv.anulada'),
            ! $medicion->activo => __('recorridas.csv.descartado'),
            default => __('recorridas.csv.valido'),
        };

        return [
            Formato::fecha($medicion->tomada_en),
            Formato::hora($medicion->tomada_en),
            $recorrida->potrero->campo->nombre,
            $recorrida->potrero->nombre,
            $recorrida->user->name,
            self::numero($medicion->altura_cm, 1),
            self::numero($medicion->distancia_cm, 1),
            self::numero($medicion->kg_ms_ha, 0),
            self::numero($medicion->latitud, 7),
            self::numero($medicion->longitud, 7),
            self::numero($medicion->precision_m, 1),
            self::numero($medicion->inclinacion_grados, 1),
            (string) $medicion->imagenes,
            self::numero($medicion->dispersion_cm, 2),
            $estado,
            (string) $recorrida->id,
        ];
    }

    /** Coma decimal y sin punto de miles: Excel en español lo lee como número. Vacío si no hay dato. */
    private static function numero(float|int|null $valor, int $decimales): string
    {
        return $valor === null ? '' : number_format($valor, $decimales, ',', '');
    }
}
