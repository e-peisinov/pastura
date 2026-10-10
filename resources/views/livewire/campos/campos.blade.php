@php
    use App\Support\Formato;

    // Lo que comparten tabla y cards se calcula una sola vez (§5).
    $paginado = ! $buscando;
    $offset = $paginado ? ($campos->currentPage() - 1) * $campos->perPage() : 0;
    $mensajeVacio = $hayFiltro ? __('campos.sin_resultados') : __('campos.sin_registros');
    $mensajeBusqueda = $this->mensajeBusqueda();
    $filas = collect($campos instanceof \Illuminate\Contracts\Pagination\Paginator ? $campos->items() : $campos)
        ->mapWithKeys(fn ($campo) => [$campo->id => [
            'superficie' => ($ha = $campo->superficieHa()) === null ? __('campos.sin_superficie') : __('campos.hectareas', ['valor' => Formato::numero($ha, 1)]),
            'ultima' => $campo->ultima_recorrida ? Formato::fecha($campo->ultima_recorrida) : __('campos.nunca'),
        ]]);
@endphp

<div {{ $this->atributosPaginacionResponsive() }}>
    <x-ui.pagina :titulo="__('campos.titulo')" icono="fa-map-location-dot">
        @if($puedeEditar)
            <x-slot:acciones>
                <x-ui.boton icono="fa-plus" wire:click="nuevo">{{ __('campos.nuevo') }}</x-ui.boton>
            </x-slot:acciones>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg">
            {{-- Filtros: buscador server-side (§4b) + estado --}}
            <div class="flex flex-col gap-3 md:flex-row md:items-center p-4 border-b border-gray-200">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true"></i>
                    <x-ui.input wire:model.live.debounce.300ms="busqueda" class="pl-9 pr-9"
                                placeholder="{{ __('campos.buscar') }}" aria-label="{{ __('ui.acciones.buscar') }}" />
                    @if($busqueda !== '')
                        <button type="button" wire:click="limpiarBusqueda" aria-label="{{ __('ui.combobox.limpiar') }}"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    @endif
                </div>
                <div class="w-full md:w-44">
                    <x-ui.combobox wire:model.live="filtroEstado" :opciones="$estados"
                                   :placeholder="__('ui.filtros.estado')" :limpiable="false" :ordenar="false" />
                </div>
            </div>

            @if($buscando && $mensajeBusqueda === null)
                <p class="px-4 py-2 text-sm text-gray-500 border-b border-gray-200" data-resumen-busqueda>
                    {{ trans_choice('campos.resultados_busqueda', $campos->count(), ['texto' => trim($busqueda)]) }}
                    @if($campos->count() >= $this::LIMITE_BUSQUEDA)
                        <span class="text-amber-700">{{ __('campos.resultados_tope', ['limite' => $this::LIMITE_BUSQUEDA]) }}</span>
                    @endif
                </p>
            @endif

            {{-- Escritorio --}}
            <x-ui.tabla>
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <x-ui.th columna="nombre" :orden="$this->orden()">{{ __('campos.columnas.campo') }}</x-ui.th>
                        <x-ui.th columna="potreros" :orden="$this->orden()">{{ __('campos.columnas.potreros') }}</x-ui.th>
                        <x-ui.th derecha>{{ __('campos.columnas.superficie') }}</x-ui.th>
                        <x-ui.th columna="ultima_recorrida" :orden="$this->orden()">{{ __('campos.columnas.ultima_recorrida') }}</x-ui.th>
                        <x-ui.th columna="estado" :orden="$this->orden()">{{ __('campos.columnas.estado') }}</x-ui.th>
                        <x-ui.th derecha>{{ __('ui.acciones.acciones') }}</x-ui.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($campos as $campo)
                        <tr wire:key="fila-{{ $campo->id }}" class="hover:bg-gray-50" data-campo="{{ $campo->id }}">
                            <td class="px-4 py-3">
                                <button type="button" wire:click="verDetalle({{ $campo->id }})" class="font-medium text-gray-800 hover:underline text-left">{{ $campo->nombre }}</button>
                                @if($campo->localidad)
                                    <p class="text-xs text-gray-500">{{ $campo->localidad }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ trans_choice('campos.potreros_count', $campo->potreros_count) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-500">{{ $filas[$campo->id]['superficie'] }}</td>
                            <td class="px-4 py-3 text-gray-500 tabular-nums">{{ $filas[$campo->id]['ultima'] }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge :color="$campo->activo ? 'verde' : 'gris'">
                                    {{ $campo->activo ? __('ui.estado.activo') : __('ui.estado.inactivo') }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <x-ui.accion icono="fa-eye" :etiqueta="__('ui.acciones.ver')" wire:click="verDetalle({{ $campo->id }})" />
                                    @if($puedeEditar)
                                        <x-ui.accion icono="fa-pen" :etiqueta="__('ui.acciones.editar')" wire:click="editar({{ $campo->id }})" />
                                        @if($campo->activo)
                                            <x-ui.accion icono="fa-trash" color="peligro" :etiqueta="__('ui.acciones.eliminar')" wire:click="confirmarBaja({{ $campo->id }})" />
                                        @else
                                            <x-ui.accion icono="fa-rotate-left" :etiqueta="__('ui.acciones.reactivar')" wire:click="reactivar({{ $campo->id }})" />
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-ui.vacio icono="fa-map-location-dot">{{ $mensajeBusqueda ?? $mensajeVacio }}</x-ui.vacio>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.tabla>

            {{-- Mobile --}}
            <x-ui.cards>
                @forelse($campos as $campo)
                    <div wire:key="card-{{ $campo->id }}" class="bg-white border border-gray-200 rounded-lg p-4 space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <button type="button" wire:click="verDetalle({{ $campo->id }})" class="font-medium text-gray-800 text-left">{{ $campo->nombre }}</button>
                                @if($campo->localidad)
                                    <p class="text-sm text-gray-500">{{ $campo->localidad }}</p>
                                @endif
                            </div>
                            <x-ui.badge :color="$campo->activo ? 'verde' : 'gris'">
                                {{ $campo->activo ? __('ui.estado.activo') : __('ui.estado.inactivo') }}
                            </x-ui.badge>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-sm text-gray-500">
                            <span>{{ trans_choice('campos.potreros_count', $campo->potreros_count) }} · {{ $filas[$campo->id]['superficie'] }}</span>
                            <span>{{ __('campos.columnas.ultima_recorrida') }}: {{ $filas[$campo->id]['ultima'] }}</span>
                        </div>
                        <div class="flex justify-end gap-2 pt-1">
                            <x-ui.accion icono="fa-eye" :etiqueta="__('ui.acciones.ver')" wire:click="verDetalle({{ $campo->id }})" />
                            @if($puedeEditar)
                                <x-ui.accion icono="fa-pen" :etiqueta="__('ui.acciones.editar')" wire:click="editar({{ $campo->id }})" />
                                @if($campo->activo)
                                    <x-ui.accion icono="fa-trash" color="peligro" :etiqueta="__('ui.acciones.eliminar')" wire:click="confirmarBaja({{ $campo->id }})" />
                                @else
                                    <x-ui.accion icono="fa-rotate-left" :etiqueta="__('ui.acciones.reactivar')" wire:click="reactivar({{ $campo->id }})" />
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <x-ui.vacio icono="fa-map-location-dot">{{ $mensajeBusqueda ?? $mensajeVacio }}</x-ui.vacio>
                @endforelse
            </x-ui.cards>

            @if($paginado)
                <x-ui.paginacion :current-page="$campos->currentPage()" :total-pages="$campos->lastPage()"
                                 :offset="$offset" :per-page="$campos->perPage()" :total-records="$campos->total()"
                                 class="rounded-b-lg" />
            @endif
        </div>
    </x-ui.pagina>

    {{-- Alta / edición del campo (D57) --}}
    @if($mostrarFormulario)
        <livewire:campos.campo-formulario :campo-id="$campoId" :key="'campo-formulario-'.($campoId ?? 'nuevo')" />
    @endif

    @include('livewire.campos.partials.detalle')

    {{-- Potrero: formulario apilado sobre la ficha (D57) --}}
    @if($mostrarPotrero && $detalleId !== null)
        <livewire:campos.potrero-formulario :campo-id="$detalleId" :potrero-id="$potreroId" :z="60"
                                            :key="'potrero-formulario-'.$detalleId.'-'.($potreroId ?? 'nuevo')" />
    @endif

    {{-- Baja del campo: eliminar sin historia, desactivar con historia --}}
    @if($campoEnBaja)
        <x-ui.modal-confirmacion :tipo="$bajaDesactiva ? 'advertencia' : 'peligro'"
                                 :boton="$bajaDesactiva ? __('ui.acciones.desactivar') : __('ui.acciones.eliminar')"
                                 confirmar="baja" cancelar="cancelarBaja" :z="60">
            {{ __($bajaDesactiva ? 'campos.confirmar_desactivar' : 'campos.confirmar_eliminar', ['nombre' => $campoEnBaja->nombre]) }}
        </x-ui.modal-confirmacion>
    @endif

    {{-- Baja de un potrero, encima de la ficha --}}
    @if($potreroEnBaja)
        <x-ui.modal-confirmacion :tipo="$bajaPotreroDesactiva ? 'advertencia' : 'peligro'"
                                 :boton="$bajaPotreroDesactiva ? __('ui.acciones.desactivar') : __('ui.acciones.eliminar')"
                                 confirmar="bajaPotrero" cancelar="cancelarBajaPotrero" :z="60">
            {{ __($bajaPotreroDesactiva ? 'campos.potrero.confirmar_desactivar' : 'campos.potrero.confirmar_eliminar', ['nombre' => $potreroEnBaja->nombre]) }}
        </x-ui.modal-confirmacion>
    @endif
</div>
