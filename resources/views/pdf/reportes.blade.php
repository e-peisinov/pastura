{{-- Los reportes de un período, A4 vertical (P18). Las mismas tablas que el CSV. --}}
@extends('pdf.plantilla')

@php
    use App\Support\Formato;
    use App\Support\Reportes\ExportadorReportes;

    $periodo = __('impresiones.reportes.periodo', ['desde' => Formato::fecha($reportes->desde), 'hasta' => Formato::fecha($reportes->hasta)]);
@endphp

@section('titulo', __('impresiones.reportes.titulo').' · '.$periodo)

@section('estilos')
    <style>
        .seccion { margin-top: 12px; page-break-inside: avoid; }
        .seccion.larga { page-break-inside: auto; }
        .listado th, .listado td { font-size: 8.5pt; }
        .listado { margin-top: 3px; }
        .listado th.num { text-align: right; }
        .listado td.barra { width: 32%; vertical-align: middle; }
        .listado th.barra { width: 32%; }
        .barra div { height: 7px; background: #2a78d6; border-radius: 0 2px 2px 0; }
    </style>
@endsection

@section('cabecera')
    <h1>{{ __('impresiones.reportes.titulo') }}</h1>
    <p class="sub">{{ $periodo }}</p>
    <p class="sub">{{ __('impresiones.reportes.anterior', ['desde' => Formato::fecha($anterior->desde), 'hasta' => Formato::fecha($anterior->hasta)]) }}</p>
@endsection

@section('contenido')
    @foreach($secciones as $seccion)
        <div class="seccion {{ count($seccion['filas']) > 20 ? 'larga' : '' }}" data-seccion="{{ $seccion['clave'] }}">
            <h2>{{ $seccion['clave'] === 'resumen' ? __('impresiones.reportes.resumen') : $seccion['titulo'] }}</h2>

            <table class="listado">
                <thead>
                    <tr>
                        @foreach($seccion['columnas'] as $i => $columna)
                            <th class="{{ $i > 0 ? 'num' : '' }}">{{ $columna }}</th>
                        @endforeach
                        @if($seccion['barra'] !== null)
                            <th class="barra"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($seccion['filas'] as $fila)
                        <tr>
                            @foreach($fila as $i => $valor)
                                @php
                                    $texto = ExportadorReportes::celda($valor, $seccion['formatos'][$i]);
                                    // La variación del resumen va con signo: nunca solo en color.
                                    if ($seccion['clave'] === 'resumen' && $i === 3 && $valor !== null && $valor > 0) {
                                        $texto = '+'.$texto;
                                    }
                                @endphp
                                <td class="{{ $i > 0 ? 'num' : '' }}">{{ $texto !== '' ? $texto : '—' }}</td>
                            @endforeach
                            @if($seccion['barra'] !== null)
                                <td class="barra">
                                    <div style="width: {{ $seccion['maximo'] > 0 ? ((float) $fila[$seccion['barra']] > 0 ? max(round((float) $fila[$seccion['barra']] / $seccion['maximo'] * 100), 1) : 0) : 0 }}%"></div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($seccion['columnas']) + ($seccion['barra'] !== null ? 1 : 0) }}" class="centro gris">{{ __('reportes.sin_datos') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
@endsection
