<!DOCTYPE html>
{{--
    Plantilla de las impresiones del sistema (estandares-ux.md §10): membrete
    de la organización arriba (logo y nombre), pie con la fecha de emisión y
    la página. Las páginas concretas extienden esta y completan `titulo`,
    `estilos`, `cabecera` y `contenido`.

    No es una pantalla del sistema: dompdf no procesa Tailwind, así que los
    estilos van en un <style> propio. El acento de la marca blanca llega en
    `$acento` (hexa) y el logo en `$logo` (data URI o null).
--}}
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('titulo')</title>
    <style>
        @page { margin: 12mm 12mm 18mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9pt; color: #1f2937; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        p { margin: 0; }
        h1 { font-size: 15pt; font-weight: bold; margin: 0; color: {{ $acento }}; }
        h2 { font-size: 10pt; font-weight: bold; margin: 0 0 4px; color: {{ $acento }}; text-transform: uppercase; letter-spacing: 0.04em; }
        .rotulo { color: #6b7280; }
        .num { text-align: right; white-space: nowrap; }
        .centro { text-align: center; }
        .negrita { font-weight: bold; }
        .gris { color: #6b7280; }

        /* Membrete: logo + nombre a la izquierda, título a la derecha. */
        /* El borde va en el div: dompdf no lo dibuja entero sobre la tabla. */
        .membrete { border-bottom: 2px solid {{ $acento }}; padding-bottom: 8px; margin-bottom: 10px; }
        .membrete td { vertical-align: top; }
        .membrete .logo { width: 64px; padding-right: 10px; }
        .membrete .logo img { width: 54px; height: 54px; object-fit: contain; }
        .membrete .organizacion { font-size: 14pt; font-weight: bold; color: {{ $acento }}; margin-bottom: 3px; }
        .membrete .titulo { text-align: right; }
        .membrete .titulo .sub { font-size: 8.5pt; color: #4b5563; margin-top: 3px; }

        /* Listados. */
        .listado { margin-top: 8px; }
        .listado th { background: #f3f4f6; color: #374151; text-align: left; padding: 4px 6px; font-size: 8pt; border-bottom: 1px solid #9ca3af; }
        .listado td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .listado tr { page-break-inside: avoid; }

        /* Pie fijo con la emisión y el número de página. */
        .pie { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5pt; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 3px; }
        .pie .pagina { float: right; }
        /* dompdf no resuelve counter(pages): va solo el número de página. */
        .pie .pagina:after { content: "{{ __('impresiones.membrete.pagina') }} " counter(page); }
    </style>
    @yield('estilos')
</head>
<body>
    <div class="pie">
        <span class="pagina"></span>
        <span>{{ $configuracion->nombre }} · {{ __('impresiones.membrete.emitido', ['fecha' => $emitido->format('d/m/Y'), 'hora' => $emitido->format('H:i')]) }}</span>
    </div>

    <div class="membrete">
        <table>
            <tr>
                @if($logo)
                    <td class="logo"><img src="{{ $logo }}" alt=""></td>
                @endif
                <td>
                    <p class="organizacion">{{ $configuracion->nombre }}</p>
                </td>
                <td class="titulo">
                    @yield('cabecera')
                </td>
            </tr>
        </table>
    </div>

    @yield('contenido')
</body>
</html>
