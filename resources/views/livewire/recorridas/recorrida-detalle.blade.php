{{-- Ficha de una recorrida: resumen, mapa de los puntos y la tabla de puntos. Hijo de livewire.recorridas.recorridas. --}}
@php
    use App\Support\Formato;

    $sinDato = __('recorridas.detalle.sin_dato');
    $cm = fn ($valor) => $valor === null ? $sinDato : __('recorridas.cm', ['valor' => Formato::numero($valor, 1)]);
    $potrero = $recorrida->potrero;
    $titulo = __('recorridas.detalle.titulo', [
        'potrero' => $potrero->campo->nombre.' · '.$potrero->nombre,
        'fecha' => Formato::fecha($recorrida->iniciada_en),
    ]);
    $horario = $resumen->primera === null
        ? Formato::hora($recorrida->iniciada_en)
        : Formato::hora($resumen->primera).' – '.Formato::hora($resumen->ultima);
    $rango = $resumen->alturaMinima === null
        ? $sinDato
        : Formato::numero($resumen->alturaMinima, 1).' – '.$cm($resumen->alturaMaxima);

    // Lo que comparten tabla y cards de los puntos se calcula una sola vez (§5).
    $puntos = $recorrida->mediciones->map(fn ($m) => [
        'id' => $m->id,
        'activo' => $m->activo,
        'hora' => $m->tomada_en->format('H:i:s'),
        'altura' => $cm($m->altura_cm),
        'kg' => ($kg = $m->kgMsHaSegun($potrero->tipoPastura)) === null ? $sinDato : Formato::numero($kg),
        'gps' => $m->tieneUbicacion()
            ? ($m->precision_m === null ? __('recorridas.detalle.con_gps') : __('recorridas.detalle.gps', ['valor' => Formato::numero($m->precision_m, 1)]))
            : __('recorridas.detalle.sin_gps'),
        'calidad' => $m->dispersion_cm === null
            ? __('recorridas.detalle.calidad_sin_dispersion', ['imagenes' => $m->imagenes])
            : __('recorridas.detalle.calidad', ['imagenes' => $m->imagenes, 'dispersion' => Formato::numero($m->dispersion_cm, 2)]),
    ]);

    // Cuando cambia un punto, el mapa se vuelve a montar (wire:ignore no se redibuja).
    $claveMapa = $mapa === null ? null : md5(json_encode($mapa));
@endphp

<div>
    <x-ui.modal :titulo="$titulo" icono="fa-route" cerrar="cerrar" ancho="4xl" :z="$z" data-detalle-recorrida>
        @unless($recorrida->activo)
            <x-slot:encabezado>
                <x-ui.badge color="gris">{{ __('recorridas.anulada') }}</x-ui.badge>
            </x-slot:encabezado>
        @endunless

        {{-- Resumen (regla 8: calculado de los puntos activos) --}}
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm" data-resumen>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('recorridas.detalle.resumen.puntos') }}</dt>
                <dd class="text-gray-800 tabular-nums">{{ $resumen->puntos }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('recorridas.detalle.resumen.altura_media') }}</dt>
                <dd class="text-gray-800 tabular-nums font-semibold">{{ $cm($resumen->alturaMedia) }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('recorridas.detalle.resumen.rango') }}</dt>
                <dd class="text-gray-800 tabular-nums">{{ $rango }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('recorridas.detalle.resumen.kg') }}</dt>
                <dd class="text-gray-800 tabular-nums">{{ $resumen->kgMedio === null ? $sinDato : Formato::numero($resumen->kgMedio) }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('recorridas.detalle.resumen.horario') }}</dt>
                <dd class="text-gray-800 tabular-nums">{{ $horario }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('recorridas.detalle.resumen.recorredor') }}</dt>
                <dd class="text-gray-800">{{ $recorrida->user->name }}</dd>
            </div>
            @if($recorrida->dispositivo)
                <div class="col-span-2 sm:col-span-3">
                    <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('recorridas.detalle.dispositivo') }}</dt>
                    <dd class="text-gray-800">{{ $recorrida->dispositivo }}</dd>
                </div>
            @endif
            @if($recorrida->observaciones)
                <div class="col-span-2 sm:col-span-3">
                    <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('recorridas.detalle.observaciones') }}</dt>
                    <dd class="text-gray-800 whitespace-pre-line">{{ $recorrida->observaciones }}</dd>
                </div>
            @endif
        </dl>

        {{-- Mapa de los puntos, coloreados por altura --}}
        <div>
            <h3 class="text-sm font-semibold text-gray-800 mb-2">{{ __('recorridas.detalle.mapa') }}</h3>
            @if($mapa !== null)
                <div wire:key="mapa-recorrida-{{ $recorrida->id }}-{{ $claveMapa }}" wire:ignore
                     x-data="mapaVista(@js($mapa))" data-mapa-recorrida>
                    <div class="h-64 sm:h-80 rounded-md overflow-hidden border border-gray-200">
                        <div x-ref="mapa" class="h-full w-full"></div>
                    </div>
                    {{-- Referencia: los tramos de la escala de colores. El color viene de
                         COLORES_ALTURA (mapa.js), la misma fuente que pinta los puntos. --}}
                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500" data-referencia>
                        <span>{{ __('recorridas.detalle.referencia') }}</span>
                        <template x-for="tramo in referencia" :key="tramo.color">
                            <span class="inline-flex items-center gap-1 tabular-nums">
                                <span class="inline-block h-3 w-3 rounded-full border border-gray-300" :style="{ backgroundColor: tramo.color }"></span>
                                <span x-text="tramo.texto"></span>
                            </span>
                        </template>
                        <span class="inline-flex items-center gap-1">
                            <span class="inline-block h-3 w-3 rounded-full border border-dashed border-gray-400 bg-gray-300"></span>
                            {{ __('recorridas.detalle.descartado') }}
                        </span>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500" data-sin-ubicacion>{{ __('recorridas.detalle.sin_ubicacion') }}</p>
            @endif
        </div>

        {{-- Puntos --}}
        <div>
            <h3 class="text-sm font-semibold text-gray-800 mb-2">{{ __('recorridas.detalle.puntos') }}</h3>
            @if($puntos->isEmpty())
                <x-ui.vacio icono="fa-location-dot">{{ __('recorridas.detalle.sin_puntos') }}</x-ui.vacio>
            @else
                {{-- Escritorio --}}
                <x-ui.tabla class="border border-gray-200 rounded-md">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <x-ui.th>{{ __('recorridas.detalle.columnas.hora') }}</x-ui.th>
                            <x-ui.th derecha>{{ __('recorridas.detalle.columnas.altura') }}</x-ui.th>
                            <x-ui.th derecha>{{ __('recorridas.detalle.columnas.kg') }}</x-ui.th>
                            <x-ui.th>{{ __('recorridas.detalle.columnas.gps') }}</x-ui.th>
                            <x-ui.th>{{ __('recorridas.detalle.columnas.calidad') }}</x-ui.th>
                            @if($puedeEditar)
                                <x-ui.th derecha>{{ __('ui.acciones.acciones') }}</x-ui.th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($puntos as $punto)
                            <tr wire:key="punto-{{ $punto['id'] }}" data-punto="{{ $punto['id'] }}" @class(['text-gray-400' => ! $punto['activo']])>
                                <td class="px-4 py-2 tabular-nums">{{ $punto['hora'] }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    <span @class(['line-through' => ! $punto['activo']])>{{ $punto['altura'] }}</span>
                                    @unless($punto['activo'])
                                        <x-ui.badge color="gris" class="ml-1">{{ __('recorridas.detalle.descartado') }}</x-ui.badge>
                                    @endunless
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $punto['kg'] }}</td>
                                <td class="px-4 py-2 tabular-nums">{{ $punto['gps'] }}</td>
                                <td class="px-4 py-2 tabular-nums">{{ $punto['calidad'] }}</td>
                                @if($puedeEditar)
                                    <td class="px-4 py-1">
                                        <div class="flex justify-end">
                                            @if($punto['activo'])
                                                <x-ui.accion icono="fa-ban" color="peligro" :etiqueta="__('recorridas.detalle.descartar')" wire:click="descartarPunto({{ $punto['id'] }})" />
                                            @else
                                                <x-ui.accion icono="fa-rotate-left" :etiqueta="__('recorridas.detalle.restaurar')" wire:click="restaurarPunto({{ $punto['id'] }})" />
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.tabla>

                {{-- Mobile --}}
                <x-ui.cards>
                    @foreach($puntos as $punto)
                        <div wire:key="card-punto-{{ $punto['id'] }}" @class(['border border-gray-200 rounded-lg px-3 py-2 text-sm', 'text-gray-400 bg-gray-50' => ! $punto['activo']])>
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="tabular-nums">
                                        <span class="text-gray-500">{{ $punto['hora'] }}</span>
                                        · <span @class(['font-medium', 'line-through' => ! $punto['activo']])>{{ $punto['altura'] }}</span>
                                        @if($punto['kg'] !== $sinDato) · {{ $punto['kg'] }} {{ __('recorridas.detalle.columnas.kg') }}@endif
                                    </p>
                                    <p class="text-xs text-gray-500 tabular-nums">{{ $punto['gps'] }} · {{ $punto['calidad'] }}</p>
                                </div>
                                @if($puedeEditar)
                                    @if($punto['activo'])
                                        <x-ui.accion icono="fa-ban" color="peligro" :etiqueta="__('recorridas.detalle.descartar')" wire:click="descartarPunto({{ $punto['id'] }})" />
                                    @else
                                        <x-ui.accion icono="fa-rotate-left" :etiqueta="__('recorridas.detalle.restaurar')" wire:click="restaurarPunto({{ $punto['id'] }})" />
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </x-ui.cards>
            @endif
        </div>

        <x-slot:footer>
            @if($puedeEditar)
                <x-ui.boton variante="secundario" icono="fa-file-csv" wire:click="exportar" wire:loading.attr="disabled" wire:target="exportar" data-exportar-recorrida>
                    {{ __('recorridas.exportar') }}
                </x-ui.boton>
                @if($recorrida->activo)
                    <x-ui.boton variante="peligro" icono="fa-ban" wire:click="confirmarAnular" data-anular>{{ __('recorridas.detalle.anular') }}</x-ui.boton>
                @else
                    <x-ui.boton variante="secundario" icono="fa-rotate-left" wire:click="restaurar" data-restaurar>{{ __('recorridas.detalle.restaurar_recorrida') }}</x-ui.boton>
                @endif
            @endif
            <x-ui.boton wire:click="cerrar">{{ __('ui.acciones.cerrar') }}</x-ui.boton>
        </x-slot:footer>
    </x-ui.modal>

    @if($confirmandoAnular)
        <x-ui.modal-confirmacion tipo="advertencia" :boton="__('recorridas.detalle.anular')"
                                 confirmar="anular" cancelar="cancelarAnular" :z="$z + 10">
            {{ __('recorridas.detalle.confirmar_anular') }}
        </x-ui.modal-confirmacion>
    @endif
</div>
