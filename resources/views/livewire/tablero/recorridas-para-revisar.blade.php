{{-- Bloque "Recorridas para revisar" (P18): un contador por motivo que filtra
     la lista (D76); una fila abre la ficha para descartar puntos o anular (D78). --}}
@php
    use App\Support\Formato;

    $subtitulo = $filtrado === null
        ? __('tablero.revisar.lista', ['dias' => $dias])
        : __('tablero.revisar.filtrado', ['motivo' => $filtrado->etiqueta(), 'dias' => $dias]);
    $vacio = $filtrado === null
        ? __('tablero.revisar.sin_revisar', ['dias' => $dias])
        : __('tablero.revisar.sin_filtradas', ['motivo' => $filtrado->etiqueta(), 'dias' => $dias]);
@endphp

<div class="h-full">
    <x-ui.panel :titulo="__('tablero.bloques.revisar')" icono="fa-magnifying-glass" :href="route('recorridas')" class="h-full" data-bloque="revisar">
        <div class="grid grid-cols-3 gap-2 p-4" data-contadores>
            @foreach($contadores as $item)
                <x-ui.indicador :rotulo="$item['motivo']->etiqueta()" :valor="$item['cantidad']"
                                :color="$item['cantidad'] > 0 ? 'ambar' : 'gris'"
                                wire:click="filtrar('{{ $item['motivo']->value }}')" :activo="$filtrado === $item['motivo']"
                                data-motivo="{{ $item['motivo']->value }}" />
            @endforeach
        </div>

        <p class="px-4 pb-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ $subtitulo }}</p>
        @if($lista->isEmpty())
            <x-ui.vacio icono="fa-circle-check" class="py-6">{{ $vacio }}</x-ui.vacio>
        @else
            <ul @class(['divide-y divide-gray-100 border-t border-gray-100', 'max-h-[13.5rem] overflow-y-auto scroll-fino' => $filtrado !== null]) data-lista>
                @foreach($lista as $fila)
                    @php $recorrida = $fila->recorrida; @endphp
                    <li wire:key="revisar-{{ $recorrida->id }}">
                        <button type="button" wire:click="verDetalle({{ $recorrida->id }})"
                                class="flex w-full flex-col gap-1 px-4 py-2 text-left hover:bg-gray-50 focus:outline-none focus:bg-gray-50 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                            <span class="min-w-0">
                                <span class="block truncate text-sm text-gray-800">{{ __('tablero.potrero', ['campo' => $recorrida->potrero->campo->nombre, 'potrero' => $recorrida->potrero->nombre]) }}</span>
                                <span class="block truncate text-xs text-gray-500 tabular-nums">
                                    {{ Formato::fecha($recorrida->iniciada_en) }} · {{ $recorrida->user->name }}
                                    · {{ trans_choice('tablero.recorridas.puntos', $recorrida->puntos, ['count' => $recorrida->puntos]) }}
                                </span>
                            </span>
                            <span class="flex shrink-0 flex-wrap gap-1">
                                @foreach($fila->motivos as $motivo)
                                    <x-ui.badge color="ambar">{{ $motivo->etiqueta() }}</x-ui.badge>
                                @endforeach
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.panel>

    @include('livewire.tablero.partials.recorrida-detalle')
</div>
