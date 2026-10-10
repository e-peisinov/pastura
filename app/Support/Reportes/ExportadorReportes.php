<?php

namespace App\Support\Reportes;

use App\Models\Campo;
use App\Models\Potrero;
use App\Support\Formato;

/**
 * Los reportes de un período como tablas (P18; molde: D74 del kit): en CSV,
 * para seguir trabajándolos en una planilla, y en PDF (ReportesPdf), para
 * imprimirlos o mandarlos. Los dos salen de `secciones()`, así dicen lo
 * mismo que la pantalla y lo mismo entre sí.
 *
 * El CSV es un solo archivo con una sección por bloque (título, encabezados,
 * filas y una línea en blanco), como el de recorridas (P8): punto y coma,
 * BOM para que Excel lea las tildes y números con coma decimal sin
 * separador de miles, así la planilla los toma como números.
 */
final class ExportadorReportes
{
    public const SEPARADOR = ';';

    public static function csv(Reportes $reportes): string
    {
        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");

        foreach (self::secciones($reportes) as $seccion) {
            self::linea($salida, [$seccion['titulo']]);
            self::linea($salida, $seccion['columnas']);

            foreach ($seccion['filas'] as $fila) {
                self::linea($salida, array_map(
                    fn ($valor, string $formato) => self::celda($valor, $formato, paraPlanilla: true),
                    $fila,
                    $seccion['formatos'],
                ));
            }

            self::linea($salida, []);
        }

        rewind($salida);
        $csv = stream_get_contents($salida);
        fclose($salida);

        return $csv;
    }

    public static function nombreArchivo(Reportes $reportes, string $extension = 'csv'): string
    {
        return sprintf('reportes-%s-al-%s.%s', $reportes->desde, $reportes->hasta, $extension);
    }

    /**
     * Cada bloque de la pantalla como una tabla: las filas traen los valores
     * crudos y `formatos` dice cómo se escribe cada columna (texto | entero |
     * decimal1 | decimal2 | porcentaje | fecha | auto). La evolución y los
     * potreros del campo van solo si se eligió un potrero y un campo.
     *
     * @return list<array{clave: string, titulo: string, columnas: list<string>, formatos: list<string>, filas: list<list<mixed>>}>
     */
    public static function secciones(Reportes $reportes): array
    {
        $anterior = $reportes->anterior();
        $columnas = fn (string $cuales) => array_values(__('reportes.exportar.columnas.'.$cuales));

        $resumen = [];
        foreach ($reportes->comparacion() as $clave => $dato) {
            $resumen[] = [__('reportes.resumen.'.$clave), $dato['actual'], $dato['anterior'], $dato['variacion']];
        }

        $secciones = [[
            'clave' => 'resumen',
            'titulo' => __('reportes.exportar.resumen', [
                'desde' => Formato::fecha($reportes->desde), 'hasta' => Formato::fecha($reportes->hasta),
                'antes_desde' => Formato::fecha($anterior->desde), 'antes_hasta' => Formato::fecha($anterior->hasta),
            ]),
            'columnas' => $columnas('resumen'),
            // auto: las cantidades son enteros; las hectáreas, con un decimal.
            'formatos' => ['texto', 'auto', 'auto', 'porcentaje'],
            'filas' => $resumen,
        ]];

        if ($reportes->potreroId !== null && ($potrero = Potrero::query()->with('campo')->find($reportes->potreroId))) {
            $secciones[] = [
                'clave' => 'evolucion',
                'titulo' => __('reportes.exportar.evolucion', ['potrero' => $potrero->nombreCompleto()]),
                'columnas' => $columnas('evolucion'),
                'formatos' => ['fecha', 'decimal1', 'entero', 'decimal2', 'decimal1'],
                'filas' => array_map(fn (array $f) => [$f['fecha'], $f['altura'], $f['kg'], $f['tasa_cm'], $f['tasa_kg']], $reportes->evolucion($potrero->id)),
            ];
        }

        if ($reportes->campoId !== null && ($campo = Campo::query()->find($reportes->campoId))) {
            $secciones[] = [
                'clave' => 'potreros',
                'titulo' => __('reportes.exportar.potreros', ['campo' => $campo->nombre]),
                'columnas' => $columnas('potreros'),
                'formatos' => ['texto', 'decimal1', 'entero', 'fecha'],
                'filas' => array_map(fn (array $f) => [$f['nombre'], $f['altura'], $f['kg'], $f['fecha']], $reportes->potrerosDelCampo($campo->id)['filas']),
            ];
        }

        $secciones[] = [
            'clave' => 'disponibilidad',
            'titulo' => __('reportes.disponibilidad.titulo'),
            'columnas' => $columnas('disponibilidad'),
            'formatos' => ['texto', 'entero', 'entero', 'entero'],
            'filas' => array_map(fn (array $f) => [$f['nombre'], $f['kg'], $f['con_dato'], $f['potreros']], $reportes->disponibilidad()),
        ];

        $secciones[] = [
            'clave' => 'recorredores',
            'titulo' => __('reportes.recorredores.titulo'),
            'columnas' => $columnas('recorredores'),
            'formatos' => ['texto', 'entero', 'entero'],
            'filas' => array_map(fn (array $f) => [$f['nombre'], $f['recorridas'], $f['puntos']], $reportes->porRecorredor()),
        ];

        return $secciones;
    }

    /**
     * Un valor escrito según su formato. Para la planilla, los números van
     * con coma decimal y sin separador de miles; para leer (PDF), con el
     * formato de siempre.
     */
    public static function celda(mixed $valor, string $formato, bool $paraPlanilla = false): string
    {
        if ($valor === null) {
            return '';
        }

        if ($formato === 'auto') {
            $formato = is_int($valor) ? 'entero' : 'decimal1';
        }

        $numero = fn (int $decimales) => $paraPlanilla
            ? number_format((float) $valor, $decimales, ',', '')
            : Formato::numero($valor, $decimales);

        return match ($formato) {
            'entero' => $numero(0),
            'decimal1', 'porcentaje' => $numero(1),
            'decimal2' => $numero(2),
            'fecha' => Formato::fecha($valor),
            default => (string) $valor,
        };
    }

    /** @param  resource  $salida */
    private static function linea($salida, array $celdas): void
    {
        fputcsv($salida, $celdas, self::SEPARADOR, '"', '\\', "\r\n");
    }
}
