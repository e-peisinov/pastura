{{--
    <x-ui.grafico-columnas> — estandares-ux.md §11. Columnas en el tiempo
    (por día o por mes) con una o dos series lado a lado. HTML y CSS, sin
    JavaScript: se redibuja con Livewire y entra en mobile sin scroll.

      <x-ui.grafico-columnas :tramos="$tramos" :series="['vendido' => 'Vendido', 'cobrado' => 'Cobrado']" />

    Props:
      tramos   Lista de arrays con `etiqueta` (el rótulo del eje: "03/09",
               "Sep 26") y un número por cada clave de `series`.
      series   clave => nombre. Hasta dos: la primera azul, la segunda
               naranja (el par está validado para daltonismo). El color es de
               la serie, no de su posición: no cambia si se muestra una sola.
      formato  Closure que formatea un valor para el globo y la tabla
               (por omisión, moneda).

    Un solo eje, con techo y marcas en números redondos (Escala). Con dos
    series hay leyenda; con una, el título del bloque alcanza. Pasar el mouse
    (o enfocar con Tab) muestra el globo con los valores del tramo, y "Ver
    tabla" los lista todos: nada queda solo en el color.
--}}
@props([
    'tramos' => [],
    'series' => [],
    'formato' => null,
])

@php
    use App\Support\Formato;
    use App\Support\Reportes\Escala;

    $formato ??= fn ($valor) => Formato::moneda($valor);

    // Clases literales: Tailwind no ve las que se arman concatenando.
    $rellenos = ['bg-[#2a78d6]', 'bg-[#eb6834]'];
    $claves = array_keys($series);

    $maximo = 0.0;
    foreach ($tramos as $tramo) {
        foreach ($claves as $clave) {
            $maximo = max($maximo, (float) $tramo[$clave]);
        }
    }

    $techo = Escala::techo($maximo);
    $marcas = Escala::marcas($techo);
    $cantidad = count($tramos);

    // Rótulos del eje: todos si entran (hasta 8); si no, uno cada N.
    $cada = max(1, (int) ceil($cantidad / 8));
@endphp

<figure {{ $attributes }} data-grafico-columnas>
    @if(count($series) > 1)
        <figcaption class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600" data-leyenda>
            @foreach(array_values($series) as $i => $nombre)
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-2.5 w-2.5 rounded-sm {{ $rellenos[$i] }}" aria-hidden="true"></span>{{ $nombre }}
                </span>
            @endforeach
        </figcaption>
    @endif

    <div class="flex gap-2">
        {{-- Eje: marcas en números redondos --}}
        <div class="flex h-48 w-12 shrink-0 flex-col justify-between text-right text-[11px] leading-none text-gray-400 tabular-nums" aria-hidden="true">
            @foreach($marcas as $marca)
                <span>{{ Escala::abreviar($marca) }}</span>
            @endforeach
        </div>

        <div class="min-w-0 flex-1">
            <div class="relative h-48">
                {{-- Líneas de referencia, discretas --}}
                <div class="absolute inset-0 flex flex-col justify-between" aria-hidden="true">
                    @foreach($marcas as $marca)
                        <span class="block h-px w-full {{ $loop->last ? 'bg-gray-300' : 'bg-gray-100' }}"></span>
                    @endforeach
                </div>

                <div class="absolute inset-0 flex items-end">
                    @foreach($tramos as $i => $tramo)
                        @php
                            // El globo se abre hacia adentro en los bordes para no salirse de la pantalla.
                            $lado = match (true) {
                                $i < $cantidad / 3 => 'left-0',
                                $i >= $cantidad * 2 / 3 => 'right-0',
                                default => 'left-1/2 -translate-x-1/2',
                            };
                        @endphp
                        <div class="group relative flex h-full min-w-0 flex-1 items-end justify-center gap-0.5 px-px outline-none hover:bg-gray-50 focus:bg-gray-50"
                             tabindex="0" data-tramo="{{ $tramo['etiqueta'] }}">
                            @foreach($claves as $s => $clave)
                                <div class="w-full max-w-[14px] rounded-t {{ $rellenos[$s] }}"
                                     style="height: {{ $techo > 0 ? round((float) $tramo[$clave] / $techo * 100, 2) : 0 }}%"></div>
                            @endforeach

                            <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden whitespace-nowrap rounded-md bg-gray-800 px-2.5 py-1.5 text-xs text-white shadow-lg group-hover:block group-focus:block {{ $lado }}" role="tooltip">
                                <span class="block font-medium">{{ $tramo['etiqueta'] }}</span>
                                @foreach($series as $clave => $nombre)
                                    <span class="block tabular-nums">{{ $nombre }}: {{ $formato($tramo[$clave]) }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-1 flex text-[11px] text-gray-500" aria-hidden="true">
                @foreach($tramos as $i => $tramo)
                    <span class="min-w-0 flex-1 overflow-visible whitespace-nowrap text-center">
                        {{ $i % $cada === 0 ? $tramo['etiqueta'] : '' }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- La tabla: los mismos números, sin depender del gráfico --}}
    <details class="mt-3 text-sm">
        <summary class="cursor-pointer text-gray-500 hover:text-gray-700">{{ __('ui.grafico.ver_tabla') }}</summary>
        <div class="mt-2 max-h-64 overflow-y-auto scroll-fino rounded-md border border-gray-200">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('ui.grafico.periodo') }}</th>
                        @foreach($series as $nombre)
                            <th class="px-3 py-2 text-right font-medium">{{ $nombre }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($tramos as $tramo)
                        <tr>
                            <td class="px-3 py-1.5 text-gray-800">{{ $tramo['etiqueta'] }}</td>
                            @foreach($claves as $clave)
                                <td class="px-3 py-1.5 text-right text-gray-800 tabular-nums">{{ $formato($tramo[$clave]) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
