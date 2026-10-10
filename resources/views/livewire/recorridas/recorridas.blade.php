@php
    use App\Support\Formato;

    // Lo que comparten tabla y cards se calcula una sola vez (§5).
    $offset = ($recorridas->currentPage() - 1) * $recorridas->perPage();
    $mensajeVacio = $hayFiltro ? __('recorridas.sin_resultados') : __('recorridas.sin_registros');
    $cm = fn ($valor) => $valor === null ? __('recorridas.detalle.sin_dato') : __('recorridas.cm', ['valor' => Formato::numero($valor, 1)]);
    $filas = collect($recorridas->items())->mapWithKeys(fn ($r) => [$r->id => [
        'fecha' => Formato::fecha($r->iniciada_en).' '.Formato::hora($r->iniciada_en),
        'lugar' => $r->potrero->campo->nombre.' · '.$r->potrero->nombre,
        'puntos' => trans_choice('recorridas.puntos_count', $r->puntos),
        'altura' => $cm($r->altura_media),
        'kg' => $r->kg_ms_ha_medio === null ? __('recorridas.detalle.sin_dato') : Formato::numero($r->kg_ms_ha_medio),
        'estado' => $r->activo ? ['verde', __('recorridas.valida')] : ['gris', __('recorridas.anulada')],
    ]]);
@endphp

<div {{ $this->atributosPaginacionResponsive() }}>
    <x-ui.pagina :titulo="__('recorridas.titulo')" icono="fa-route">
        @if($puedeEditar)
            <x-slot:acciones>
                <x-ui.boton variante="secundario" icono="fa-file-csv" wire:click="exportar" wire:loading.attr="disabled" wire:target="exportar" data-exportar>
                    {{ __('recorridas.exportar') }}
                </x-ui.boton>
            </x-slot:acciones>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg">
            {{-- Filtros: campo, potrero (depende del campo), recorredor, estado y período --}}
            <div class="flex flex-col gap-3 p-4 border-b border-gray-200">
                <div class="grid grid-cols-2 gap-3 md:flex md:gap-2">
                    <div class="min-w-0 md:w-48">
                        <x-ui.combobox wire:model.live="filtroCampo" :opciones="$campos"
                                       :placeholder="__('recorridas.filtros.campo')" :vacio="__('ui.filtros.todos')" />
                    </div>
                    <div class="min-w-0 md:w-48">
                        <x-ui.combobox wire:model.live="filtroPotrero" :opciones="$potreros" :disabled="$filtroCampo === null"
                                       :placeholder="__('recorridas.filtros.potrero')" :vacio="__('ui.filtros.todos')" />
                    </div>
                    @if($esAdministrador)
                        <div class="min-w-0 md:w-48">
                            <x-ui.combobox wire:model.live="filtroRecorredor" :opciones="$recorredores"
                                           :placeholder="__('recorridas.filtros.recorredor')" :vacio="__('ui.filtros.todos')" />
                        </div>
                    @endif
                    <div class="min-w-0 md:w-40">
                        <x-ui.combobox wire:model.live="filtroEstado" :opciones="$estados"
                                       :placeholder="__('recorridas.filtros.estado')" :limpiable="false" :ordenar="false" />
                    </div>
                </div>
                <div class="flex flex-col gap-3 md:flex-row md:items-end">
                    {{-- Rótulo arriba y dos columnas: en mobile los dos date lado a lado desbordan (§7, trampa 8). --}}
                    <div class="grid grid-cols-2 gap-3 md:flex md:items-end md:gap-2">
                        <div class="min-w-0">
                            <label for="campo-desde" class="block text-xs uppercase tracking-wide text-gray-500 mb-1">{{ __('recorridas.filtros.desde') }}</label>
                            <x-ui.input nombre="desde" type="date" wire:model.live="desde" class="md:w-40" />
                        </div>
                        <div class="min-w-0">
                            <label for="campo-hasta" class="block text-xs uppercase tracking-wide text-gray-500 mb-1">{{ __('recorridas.filtros.hasta') }}</label>
                            <x-ui.input nombre="hasta" type="date" wire:model.live="hasta" class="md:w-40" />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1" data-atajos-periodo>
                        <x-ui.boton variante="enlace" wire:click="ultimos30Dias">{{ __('recorridas.filtros.ultimos_30') }}</x-ui.boton>
                        <x-ui.boton variante="enlace" wire:click="esteMes">{{ __('recorridas.filtros.este_mes') }}</x-ui.boton>
                        <x-ui.boton variante="enlace" wire:click="todo">{{ __('recorridas.filtros.todo') }}</x-ui.boton>
                        @if($hayFiltro)
                            <x-ui.boton variante="enlace" icono="fa-filter-circle-xmark" wire:click="limpiarFiltros">{{ __('ui.acciones.limpiar_filtros') }}</x-ui.boton>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Escritorio --}}
            <x-ui.tabla>
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <x-ui.th columna="fecha" :orden="$this->orden()">{{ __('recorridas.columnas.fecha') }}</x-ui.th>
                        <x-ui.th>{{ __('recorridas.columnas.potrero') }}</x-ui.th>
                        <x-ui.th>{{ __('recorridas.columnas.recorredor') }}</x-ui.th>
                        <x-ui.th derecha>{{ __('recorridas.columnas.puntos') }}</x-ui.th>
                        <x-ui.th columna="altura" :orden="$this->orden()" derecha>{{ __('recorridas.columnas.altura') }}</x-ui.th>
                        <x-ui.th derecha>{{ __('recorridas.columnas.kg') }}</x-ui.th>
                        <x-ui.th>{{ __('recorridas.columnas.estado') }}</x-ui.th>
                        <x-ui.th derecha>{{ __('ui.acciones.acciones') }}</x-ui.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($recorridas as $recorrida)
                        @php($fila = $filas[$recorrida->id])
                        <tr wire:key="fila-{{ $recorrida->id }}" class="hover:bg-gray-50" data-recorrida="{{ $recorrida->id }}">
                            <td class="px-4 py-3 tabular-nums">
                                <button type="button" wire:click="verDetalle({{ $recorrida->id }})" class="font-medium text-gray-800 hover:underline text-left">{{ $fila['fecha'] }}</button>
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $fila['lugar'] }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $recorrida->user->name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-500">{{ $recorrida->puntos }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-800">{{ $fila['altura'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-500">{{ $fila['kg'] }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge :color="$fila['estado'][0]">{{ $fila['estado'][1] }}</x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <x-ui.accion icono="fa-eye" :etiqueta="__('ui.acciones.ver')" wire:click="verDetalle({{ $recorrida->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-ui.vacio icono="fa-route">{{ $mensajeVacio }}</x-ui.vacio>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.tabla>

            {{-- Mobile --}}
            <x-ui.cards>
                @forelse($recorridas as $recorrida)
                    @php($fila = $filas[$recorrida->id])
                    <div wire:key="card-{{ $recorrida->id }}" class="bg-white border border-gray-200 rounded-lg p-4 space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <button type="button" wire:click="verDetalle({{ $recorrida->id }})" class="font-medium text-gray-800 text-left">{{ $fila['lugar'] }}</button>
                                <p class="text-sm text-gray-500 tabular-nums">{{ $fila['fecha'] }} · {{ $recorrida->user->name }}</p>
                            </div>
                            <x-ui.badge :color="$fila['estado'][0]">{{ $fila['estado'][1] }}</x-ui.badge>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-sm">
                            <span class="text-gray-500">{{ $fila['puntos'] }}</span>
                            <span class="tabular-nums text-gray-800">{{ $fila['altura'] }}@if($recorrida->kg_ms_ha_medio !== null) · {{ $fila['kg'] }} {{ __('recorridas.columnas.kg') }}@endif</span>
                        </div>
                        <div class="flex justify-end gap-2 pt-1">
                            <x-ui.accion icono="fa-eye" :etiqueta="__('ui.acciones.ver')" wire:click="verDetalle({{ $recorrida->id }})" />
                        </div>
                    </div>
                @empty
                    <x-ui.vacio icono="fa-route">{{ $mensajeVacio }}</x-ui.vacio>
                @endforelse
            </x-ui.cards>

            <x-ui.paginacion :current-page="$recorridas->currentPage()" :total-pages="$recorridas->lastPage()"
                             :offset="$offset" :per-page="$recorridas->perPage()" :total-records="$recorridas->total()"
                             class="rounded-b-lg" />
        </div>
    </x-ui.pagina>

    {{-- Ficha: hijo que se monta abierto (D57, regla 7) --}}
    @if($detalleId !== null)
        <livewire:recorridas.recorrida-detalle :recorrida-id="$detalleId" :key="'recorrida-detalle-'.$detalleId" />
    @endif
</div>
