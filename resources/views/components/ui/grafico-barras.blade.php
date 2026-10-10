{{--
    <x-ui.grafico-barras> — estandares-ux.md §11. Barras horizontales para
    comparar magnitudes de una lista corta (lo más vendido, egresos por
    categoría, cobros por método). HTML y CSS, sin JavaScript: se redibuja
    con Livewire como cualquier otra vista y entra en mobile sin scroll.

      <x-ui.grafico-barras :filas="$filas" color="azul" />

    Props:
      filas   Lista de arrays: `etiqueta`, `valor` (número, define el largo),
              `texto` (el valor ya formateado, va en la punta) y `detalle`
              opcional (texto chico bajo la etiqueta: "12 unidades", "34 %").
      color   azul | naranja. Una sola serie = un solo color; el título del
              bloque dice qué es, no hace falta leyenda.

    Cada fila muestra su valor, así que el gráfico es también su tabla. El
    largo es proporcional al mayor de la lista. El texto nunca va del color
    de la barra.
--}}
@props([
    'filas' => [],
    'color' => 'azul',
])

@php
    // Clases literales: Tailwind no ve las que se arman concatenando.
    $relleno = match ($color) {
        'naranja' => 'bg-[#eb6834]',
        default => 'bg-[#2a78d6]',
    };

    $maximo = max(array_map(fn ($fila) => (float) $fila['valor'], $filas) ?: [0]);
@endphp

<ul {{ $attributes->class(['space-y-3']) }} data-grafico-barras>
    @foreach($filas as $fila)
        @php $ancho = $maximo > 0 ? max(round((float) $fila['valor'] / $maximo * 100, 1), 0.5) : 0; @endphp
        <li class="group" title="{{ $fila['etiqueta'] }}: {{ $fila['texto'] }}" data-barra="{{ $fila['etiqueta'] }}">
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="min-w-0 truncate text-gray-800">
                    {{ $fila['etiqueta'] }}
                    @if(filled($fila['detalle'] ?? null))
                        <span class="text-xs text-gray-500">· {{ $fila['detalle'] }}</span>
                    @endif
                </span>
                <span class="shrink-0 font-medium text-gray-800 tabular-nums">{{ $fila['texto'] }}</span>
            </div>
            <div class="mt-1 h-3 w-full rounded-r bg-gray-100">
                <div class="h-3 rounded-r {{ $relleno }} opacity-90 group-hover:opacity-100" style="width: {{ $ancho }}%"></div>
            </div>
        </li>
    @endforeach
</ul>
