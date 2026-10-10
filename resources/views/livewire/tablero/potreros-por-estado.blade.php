{{-- Bloque "Estado de los potreros" (P18): contadores que filtran la lista (D76)
     y la lista de los listos; una fila abre la última recorrida (D78). --}}
@php
    use App\Support\Formato;

    $subtitulo = $filtrado === null
        ? __('tablero.estado.listos')
        : __('tablero.estado.filtrado', ['estado' => $filtrado->etiqueta()]);
    $vacio = $filtrado === null
        ? __('tablero.estado.sin_listos')
        : __('tablero.estado.sin_filtrados', ['estado' => $filtrado->etiqueta()]);
@endphp

<div class="h-full">
    <x-ui.panel :titulo="__('tablero.bloques.estado')" icono="fa-wheat-awn" :href="route('campos')" :enlace="__('tablero.estado.ver_campos')"
                class="h-full" data-bloque="estado">
        <div class="grid grid-cols-3 gap-2 p-4" data-contadores>
            @foreach($contadores as $item)
                <x-ui.indicador :rotulo="$item['estado']->etiqueta()" :valor="$item['cantidad']"
                                :color="$item['cantidad'] > 0 ? $item['estado']->color() : 'gris'"
                                wire:click="filtrar('{{ $item['estado']->value }}')" :activo="$filtrado === $item['estado']"
                                data-estado="{{ $item['estado']->value }}" />
            @endforeach
        </div>

        @if($sinAlturas > 0)
            <p class="flex flex-wrap items-center gap-x-2 px-4 pb-3 text-xs text-amber-800" data-sin-alturas>
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span>{{ trans_choice('tablero.estado.sin_alturas', $sinAlturas, ['count' => $sinAlturas]) }}</span>
                @if($veConfiguracion)
                    <a href="{{ route('configuracion') }}" wire:navigate class="font-medium underline">{{ __('tablero.estado.cargar_alturas') }}</a>
                @endif
            </p>
        @endif

        <p class="px-4 pb-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ $subtitulo }}</p>
        @if($lista->isEmpty())
            <x-ui.vacio icono="fa-wheat-awn" class="py-6">{{ $vacio }}</x-ui.vacio>
        @else
            <ul @class(['divide-y divide-gray-100 border-t border-gray-100', 'max-h-[13.5rem] overflow-y-auto scroll-fino' => $filtrado !== null]) data-lista>
                @foreach($lista as $fila)
                    <li wire:key="estado-{{ $fila->potrero->id }}">
                        <button type="button" wire:click="verDetalle({{ $fila->ultima->id }})"
                                class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left hover:bg-gray-50 focus:outline-none focus:bg-gray-50">
                            <span class="min-w-0">
                                <span class="block truncate text-sm text-gray-800">{{ __('tablero.potrero', ['campo' => $fila->potrero->campo->nombre, 'potrero' => $fila->potrero->nombre]) }}</span>
                                <span class="block truncate text-xs text-gray-500">
                                    {{ $fila->potrero->tipoPastura?->nombre }}
                                    · {{ trans_choice('tablero.dias', $fila->dias, ['count' => $fila->dias]) }}
                                </span>
                            </span>
                            <span class="shrink-0 text-right tabular-nums">
                                <span class="block text-sm font-medium text-gray-800">{{ __('recorridas.cm', ['valor' => Formato::numero($fila->alturaCm(), 1)]) }}</span>
                                @if($fila->kgMsHa() !== null)
                                    <span class="block text-xs text-gray-500">{{ Formato::numero($fila->kgMsHa()) }} {{ __('recorridas.columnas.kg') }}</span>
                                @endif
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.panel>

    @include('livewire.tablero.partials.recorrida-detalle')
</div>
