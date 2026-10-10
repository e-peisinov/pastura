<?php

namespace App\Http\Controllers;

use App\Support\Impresiones\ReportesPdf;
use App\Support\Reportes\Reportes;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los reportes en PDF (P18), con lo mismo que la pantalla: la URL del PDF
 * lleva `desde`, `hasta`, `campo` y `potrero` como /reportes. La ruta pide
 * Ver en Reportes.
 */
class ReportesPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $reportes = Reportes::desdeParametros($request->query());

        return ReportesPdf::generar($reportes)->stream(ReportesPdf::nombreArchivo($reportes));
    }
}
