<?php

namespace App\Support\Impresiones;

use App\Enums\ColorAcento;
use App\Livewire\Configuracion\DatosOrganizacion;
use App\Models\Configuracion;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentoPdf;
use Illuminate\Support\Facades\Storage;

/**
 * Lo que comparten las impresiones del sistema (estandares-ux.md §10; molde:
 * D51 del kit): salen con el membrete de la organización (logo y nombre) y
 * el acento de la marca blanca, sobre la plantilla `pdf.plantilla`. Por
 * ahora, los reportes (P18).
 */
final class Impresion
{
    /**
     * Arma el documento con las variables comunes ya cargadas: `configuracion`,
     * `logo` (data URI o null), `acento` (color hexa) y `emitido` (ahora).
     *
     * @param  array<string, mixed>  $datos
     */
    public static function documento(string $vista, array $datos, string $papel = 'a4', string $orientacion = 'portrait'): DocumentoPdf
    {
        return Pdf::loadView($vista, self::variables($datos))->setPaper($papel, $orientacion);
    }

    /**
     * El HTML que va al PDF, para revisarlo en los tests sin renderizar el documento.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function html(string $vista, array $datos): string
    {
        return view($vista, self::variables($datos))->render();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function variables(array $datos): array
    {
        $configuracion = $datos['configuracion'] ?? Configuracion::instancia();

        return $datos + [
            'configuracion' => $configuracion,
            'logo' => self::logoDataUri($configuracion),
            'acento' => self::colorAcento($configuracion),
            'emitido' => now(),
        ];
    }

    /**
     * El logo embebido en el HTML: dompdf no sale a buscar archivos por URL,
     * así que va como data URI leído del disco. Sin logo (o si el archivo
     * ya no está) devuelve null y el membrete va solo con el nombre.
     */
    public static function logoDataUri(Configuracion $configuracion): ?string
    {
        if (blank($configuracion->logo_path)) {
            return null;
        }

        $disco = Storage::disk(DatosOrganizacion::DISCO);

        if (! $disco->exists($configuracion->logo_path)) {
            return null;
        }

        $mime = $disco->mimeType($configuracion->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($disco->get($configuracion->logo_path));
    }

    /** El acento de la paleta elegida (D8), en hexa: dompdf no procesa Tailwind. */
    public static function colorAcento(Configuracion $configuracion): string
    {
        return match ($configuracion->color_acento) {
            ColorAcento::Zinc => '#3f3f46',
            ColorAcento::Blue => '#1d4ed8',
            ColorAcento::Teal => '#0f766e',
            ColorAcento::Green => '#166534',
            default => '#334155',
        };
    }
}
