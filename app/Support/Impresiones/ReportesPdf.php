<?php

namespace App\Support\Impresiones;

use App\Support\Reportes\ExportadorReportes;
use App\Support\Reportes\Reportes;
use Barryvdh\DomPDF\PDF as DocumentoPdf;

/**
 * Los reportes de un período impresos (P18; molde: D74 del kit): las mismas
 * tablas que el CSV (ExportadorReportes::secciones), con el membrete de la
 * organización y una barra al lado del valor en los bloques que en pantalla
 * son un gráfico. A4 vertical.
 */
final class ReportesPdf
{
    private const VISTA = 'pdf.reportes';

    /** Las secciones que en la pantalla son un gráfico: clave => índice de la columna que se dibuja. */
    private const CON_BARRA = ['evolucion' => 1, 'potreros' => 1, 'disponibilidad' => 1, 'recorredores' => 1];

    public static function generar(Reportes $reportes): DocumentoPdf
    {
        return Impresion::documento(self::VISTA, self::datos($reportes));
    }

    public static function html(Reportes $reportes): string
    {
        return Impresion::html(self::VISTA, self::datos($reportes));
    }

    public static function nombreArchivo(Reportes $reportes): string
    {
        return ExportadorReportes::nombreArchivo($reportes, 'pdf');
    }

    /** @return array<string, mixed> */
    private static function datos(Reportes $reportes): array
    {
        $secciones = array_map(function (array $seccion) {
            $columna = self::CON_BARRA[$seccion['clave']] ?? null;
            $maximo = $columna !== null ? max(array_map(fn (array $fila) => (float) $fila[$columna], $seccion['filas']) ?: [0]) : 0;

            return $seccion + ['barra' => $columna, 'maximo' => $maximo];
        }, ExportadorReportes::secciones($reportes));

        return [
            'reportes' => $reportes,
            'anterior' => $reportes->anterior(),
            'secciones' => $secciones,
        ];
    }
}
