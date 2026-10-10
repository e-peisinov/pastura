{{-- Reportes (P18, P21): período arriba, resumen contra el período anterior y los bloques con gráfico. Solo lectura. --}}
@php
    use App\Support\Formato;

    $cm = fn ($valor) => __('recorridas.cm', ['valor' => Formato::numero($valor, 1)]);
@endphp

<div>
    <x-ui.pagina :titulo="__('reportes.titulo')" icono="fa-chart-column">
        <x-slot:acciones>
            <x-ui.boton variante="secundario" icono="fa-file-csv" wire:click="exportar" wire:loading.attr="disabled" wire:target="exportar" data-reportes-csv>
                {{ __('reportes.acciones.csv') }}
            </x-ui.boton>
            <x-ui.boton variante="secundario" icono="fa-file-pdf" :href="$urlPdf" target="_blank" :navegar="false" data-reportes-pdf>{{ __('reportes.acciones.pdf') }}</x-ui.boton>
        </x-slot:acciones>

        <div class="space-y-6">
            {{-- Período: fechas y atajos; manda sobre todos los bloques. --}}
            <div class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center bg-white border border-gray-200 rounded-lg p-4" data-selector-periodo>
                <div class="flex flex-wrap items-center gap-2">
                    <label for="campo-desde" class="text-sm text-gray-500">{{ __('reportes.periodo.desde') }}</label>
                    <div class="w-40"><x-ui.input nombre="desde" type="date" wire:model.live="desde" /></div>
                    <label for="campo-hasta" class="text-sm text-gray-500">{{ __('reportes.periodo.hasta') }}</label>
                    <div class="w-40"><x-ui.input nombre="hasta" type="date" wire:model.live="hasta" /></div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($atajos as $atajo)
                        <x-ui.boton :variante="$atajoActivo === $atajo ? 'primario' : 'secundario'" wire:click="atajo('{{ $atajo }}')" data-atajo="{{ $atajo }}">
                            {{ __('reportes.periodo.atajos.'.$atajo) }}
                        </x-ui.boton>
                    @endforeach
                </div>
            </div>

            {{-- Resumen: cada número contra el período anterior del mismo largo. Más es mejor en todos. --}}
            <div>
                <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-resumen>
                    @foreach($resumen as $clave => $dato)
                        @php
                            $valor = fn ($n) => $clave === 'hectareas' ? Formato::numero($n, 1) : Formato::numero($n);
                            $variacion = $dato['variacion'];
                            $sube = $variacion !== null && $variacion > 0;
                            $baja = $variacion !== null && $variacion < 0;
                            $claseVariacion = match (true) {
                                $sube => 'text-emerald-700',
                                $baja => 'text-red-700',
                                default => 'text-gray-500',
                            };
                        @endphp
                        <div class="bg-white border border-gray-200 rounded-lg p-4" data-dato="{{ $clave }}">
                            <dt class="text-sm text-gray-500">{{ __('reportes.resumen.'.$clave) }}</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-800 tabular-nums">{{ $valor($dato['actual']) }}</dd>
                            <dd class="text-xs text-gray-400">{{ __('reportes.resumen.ayuda.'.$clave) }}</dd>
                            {{-- Nunca solo color: flecha, palabras y el valor de antes. --}}
                            <dd class="mt-2 flex flex-wrap items-center gap-x-2 text-xs" data-variacion="{{ $variacion ?? 'sin-base' }}">
                                <span class="inline-flex items-center gap-1 font-medium {{ $claseVariacion }}">
                                    @if($sube)
                                        <i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i>{{ __('reportes.comparacion.sube', ['porcentaje' => Formato::numero($variacion, 1)]) }}
                                    @elseif($baja)
                                        <i class="fa-solid fa-arrow-trend-down" aria-hidden="true"></i>{{ __('reportes.comparacion.baja', ['porcentaje' => Formato::numero(abs($variacion), 1)]) }}
                                    @elseif($variacion === null)
                                        {{ __('reportes.comparacion.sin_base') }}
                                    @else
                                        {{ __('reportes.comparacion.igual') }}
                                    @endif
                                </span>
                                @if($variacion !== null)
                                    <span class="text-gray-400 tabular-nums">{{ __('reportes.comparacion.antes', ['valor' => $valor($dato['anterior'])]) }}</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-2 text-xs text-gray-500" data-periodo-anterior>
                    {{ __('reportes.comparacion.periodo', ['desde' => Formato::fecha($anterior->desde), 'hasta' => Formato::fecha($anterior->hasta)]) }}
                </p>
            </div>

            {{-- Campo y potrero: mandan sobre la evolución y los potreros del campo. --}}
            <div class="flex flex-col gap-3 sm:flex-row bg-white border border-gray-200 rounded-lg p-4" data-eleccion>
                <div class="w-full sm:w-64">
                    <x-ui.combobox wire:model.live="campo" :opciones="$campos" :placeholder="__('reportes.filtros.campo')" :limpiable="false" />
                </div>
                <div class="w-full sm:w-64">
                    <x-ui.combobox wire:model.live="potrero" :opciones="$potreros" :placeholder="__('reportes.filtros.potrero')" :limpiable="false" :disabled="$potreros === []" />
                </div>
            </div>

            {{-- Evolución del potrero: una columna por recorrida; el crecimiento, en la lista de abajo. --}}
            <x-ui.panel :titulo="__('reportes.evolucion.titulo')" icono="fa-chart-column" data-bloque="evolucion">
                <div class="p-4 space-y-4">
                    @if($evolucion === null)
                        <x-ui.vacio icono="fa-chart-column" class="py-6">{{ __('reportes.evolucion.elegir') }}</x-ui.vacio>
                    @elseif($evolucion === [])
                        <x-ui.vacio icono="fa-chart-column" class="py-6">{{ __('reportes.evolucion.sin_datos') }}</x-ui.vacio>
                    @else
                        <p class="text-xs text-gray-500">{{ __('reportes.evolucion.ayuda') }}</p>
                        <x-ui.grafico-columnas :tramos="array_map(fn ($f) => ['etiqueta' => $f['etiqueta'], 'altura' => $f['altura']], $evolucion)"
                                               :series="['altura' => __('reportes.evolucion.serie')]" :formato="$cm" />
                        <ul class="divide-y divide-gray-100 border-t border-gray-100 text-sm" data-crecimiento>
                            @foreach($evolucion as $fila)
                                <li class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 py-2" wire:key="evolucion-{{ $loop->index }}">
                                    <span class="tabular-nums text-gray-800">
                                        {{ Formato::fecha($fila['fecha']) }} · {{ $cm($fila['altura']) }}
                                        @if($fila['kg'] !== null)
                                            <span class="text-gray-500">· {{ __('reportes.kg', ['valor' => Formato::numero($fila['kg'])]) }}</span>
                                        @endif
                                    </span>
                                    <span class="text-xs tabular-nums text-gray-500">
                                        @if($fila['tasa_cm'] === null)
                                            {{ __('reportes.evolucion.sin_tasa') }}
                                        @else
                                            <i class="fa-solid {{ $fila['tasa_cm'] >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}" aria-hidden="true"></i>
                                            {{ __($fila['tasa_cm'] >= 0 ? 'reportes.evolucion.subio' : 'reportes.evolucion.bajo', ['valor' => Formato::numero(abs($fila['tasa_cm']), 2)]) }}
                                            @if($fila['tasa_kg'] !== null)
                                                · {{ __('reportes.evolucion.kg_dia', ['valor' => Formato::numero($fila['tasa_kg'], 1)]) }}
                                            @endif
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-ui.panel>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Potreros del campo, por su última altura --}}
                <x-ui.panel :titulo="__('reportes.potreros.titulo')" icono="fa-draw-polygon" data-bloque="potreros">
                    <div class="p-4 space-y-3">
                        @if($potrerosCampo === null)
                            <x-ui.vacio icono="fa-draw-polygon" class="py-6">{{ __('reportes.potreros.elegir') }}</x-ui.vacio>
                        @elseif($potrerosCampo['filas'] === [])
                            <x-ui.vacio icono="fa-draw-polygon" class="py-6">{{ __('reportes.sin_datos') }}</x-ui.vacio>
                        @else
                            <p class="text-xs text-gray-500">{{ __('reportes.potreros.ayuda') }}</p>
                            <x-ui.grafico-barras :filas="$potrerosCampo['filas']" />
                        @endif
                        @if($potrerosCampo !== null && $potrerosCampo['sin_datos'] > 0)
                            <p class="text-xs text-gray-500">{{ trans_choice('reportes.potreros.sin_datos', $potrerosCampo['sin_datos'], ['count' => $potrerosCampo['sin_datos']]) }}</p>
                        @endif
                    </div>
                </x-ui.panel>

                {{-- Disponibilidad forrajera por campo --}}
                <x-ui.panel :titulo="__('reportes.disponibilidad.titulo')" icono="fa-wheat-awn" data-bloque="disponibilidad">
                    <div class="p-4 space-y-3">
                        <p class="text-xs text-gray-500">{{ __('reportes.disponibilidad.ayuda') }}</p>
                        @if($disponibilidad === [])
                            <x-ui.vacio icono="fa-wheat-awn" class="py-6">{{ __('reportes.disponibilidad.sin_datos') }}</x-ui.vacio>
                        @else
                            <x-ui.grafico-barras :filas="$disponibilidad" />
                        @endif
                    </div>
                </x-ui.panel>

                {{-- Actividad por recorredor --}}
                <x-ui.panel :titulo="__('reportes.recorredores.titulo')" icono="fa-person-walking" :href="route('recorridas', ['desde' => $desde, 'hasta' => $hasta])" data-bloque="recorredores">
                    <div class="p-4">
                        @if($recorredores === [])
                            <x-ui.vacio icono="fa-person-walking" class="py-6">{{ __('reportes.sin_datos') }}</x-ui.vacio>
                        @else
                            <x-ui.grafico-barras :filas="$recorredores" />
                        @endif
                    </div>
                </x-ui.panel>
            </div>
        </div>
    </x-ui.pagina>
</div>
