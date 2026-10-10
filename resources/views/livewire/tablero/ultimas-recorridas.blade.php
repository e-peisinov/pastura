{{-- Bloque "Últimas recorridas" / "Mis recorridas" (P18): hoy y últimos 7 días,
     con enlace al listado filtrado, y las más recientes; una fila abre la ficha (D78). --}}
@php
    use App\Support\Formato;
@endphp

<div class="h-full">
    <x-ui.panel :titulo="__($esRecorredor ? 'tablero.bloques.mias' : 'tablero.bloques.ultimas')" icono="fa-route"
                :href="route('recorridas')" class="h-full" data-bloque="recorridas">
        <div class="grid grid-cols-2 gap-2 p-4" data-contadores>
            @foreach($contadores as $item)
                <x-ui.indicador :rotulo="__('tablero.recorridas.'.$item['clave'])" :valor="$item['cantidad']" :href="$item['href']"
                                data-recorridas="{{ $item['clave'] }}" />
            @endforeach
        </div>

        <p class="px-4 pb-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('tablero.recorridas.lista') }}</p>
        @if($recorridas->isEmpty())
            <x-ui.vacio icono="fa-route" class="py-6">{{ __($esRecorredor ? 'tablero.recorridas.sin_mias' : 'tablero.recorridas.sin_recorridas') }}</x-ui.vacio>
        @else
            <ul class="divide-y divide-gray-100 border-t border-gray-100" data-lista>
                @foreach($recorridas as $recorrida)
                    <li wire:key="ultima-{{ $recorrida->id }}">
                        <button type="button" wire:click="verDetalle({{ $recorrida->id }})"
                                class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left hover:bg-gray-50 focus:outline-none focus:bg-gray-50">
                            <span class="min-w-0">
                                <span class="block truncate text-sm text-gray-800">{{ __('tablero.potrero', ['campo' => $recorrida->potrero->campo->nombre, 'potrero' => $recorrida->potrero->nombre]) }}</span>
                                <span class="block truncate text-xs text-gray-500 tabular-nums">
                                    {{ Formato::fecha($recorrida->iniciada_en) }} {{ Formato::hora($recorrida->iniciada_en) }}
                                    @unless($esRecorredor) · {{ $recorrida->user->name }} @endunless
                                    · {{ trans_choice('tablero.recorridas.puntos', $recorrida->puntos, ['count' => $recorrida->puntos]) }}
                                </span>
                            </span>
                            <span class="shrink-0 text-sm font-medium text-gray-800 tabular-nums">
                                {{ $recorrida->altura_media === null ? __('recorridas.detalle.sin_dato') : __('recorridas.cm', ['valor' => Formato::numero($recorrida->altura_media, 1)]) }}
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.panel>

    @include('livewire.tablero.partials.recorrida-detalle')
</div>
