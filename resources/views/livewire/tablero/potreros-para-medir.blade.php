{{-- Bloque "Potreros para medir" (P18): sin medir y nunca medidos, con
     contadores que filtran la lista (D76). --}}
@php
    use App\Support\Formato;

    $grupo = $filtro !== null ? __('tablero.para_medir.'.$filtro) : null;
@endphp

<div class="h-full">
    <x-ui.panel :titulo="__('tablero.bloques.para_medir')" icono="fa-ruler-vertical" class="h-full" data-bloque="para-medir">
        <div class="grid grid-cols-2 gap-2 p-4" data-contadores>
            @foreach($contadores as $item)
                <x-ui.indicador :rotulo="__('tablero.para_medir.'.$item['grupo'])" :valor="$item['cantidad']"
                                :color="$item['cantidad'] > 0 ? $item['color'] : 'gris'"
                                wire:click="filtrar('{{ $item['grupo'] }}')" :activo="$filtro === $item['grupo']"
                                data-grupo="{{ $item['grupo'] }}" />
            @endforeach
        </div>

        <p class="px-4 pb-1 text-xs font-medium uppercase tracking-wide text-gray-500">
            {{ $grupo === null ? __('tablero.para_medir.lista') : __('tablero.para_medir.filtrado', ['grupo' => $grupo]) }}
        </p>
        @if($lista->isEmpty())
            <x-ui.vacio icono="fa-circle-check" class="py-6">
                {{ $grupo === null ? __('tablero.para_medir.al_dia', ['dias' => $diasSinMedir]) : __('tablero.para_medir.sin_filtrados', ['grupo' => $grupo]) }}
            </x-ui.vacio>
        @else
            <ul @class(['divide-y divide-gray-100 border-t border-gray-100', 'max-h-[13.5rem] overflow-y-auto scroll-fino' => $filtro !== null]) data-lista>
                @foreach($lista as $fila)
                    <li wire:key="medir-{{ $fila->potrero->id }}" class="flex items-center justify-between gap-3 px-4 py-2">
                        <span class="min-w-0">
                            <span class="block truncate text-sm text-gray-800">{{ __('tablero.potrero', ['campo' => $fila->potrero->campo->nombre, 'potrero' => $fila->potrero->nombre]) }}</span>
                            @if($fila->potrero->tipoPastura)
                                <span class="block truncate text-xs text-gray-500">{{ $fila->potrero->tipoPastura->nombre }}</span>
                            @endif
                        </span>
                        <span class="shrink-0 text-right text-xs tabular-nums">
                            @if($fila->ultima === null)
                                <x-ui.badge color="rojo">{{ __('tablero.para_medir.nunca') }}</x-ui.badge>
                            @else
                                <span class="text-amber-800">{{ __('tablero.para_medir.ultima', [
                                    'dias' => trans_choice('tablero.dias', $fila->dias, ['count' => $fila->dias]),
                                    'altura' => __('recorridas.cm', ['valor' => Formato::numero($fila->alturaCm(), 1)]),
                                ]) }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.panel>
</div>
