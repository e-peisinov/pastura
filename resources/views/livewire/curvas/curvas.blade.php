@php
    use App\Support\Formato;
    use App\Support\Biomasa\RectaBiomasa;
    use App\Support\Tema;

    $areaTexto = rtrim(rtrim(Formato::numero($area, 4), '0'), ',');

    // Lo que comparten tabla y cards se calcula una sola vez (§5).
    $offset = ($curvas->currentPage() - 1) * $curvas->perPage();
    $mensajeVacio = $hayFiltro ? __('curvas.sin_resultados') : __('curvas.sin_registros');
    $filas = collect($curvas->items())->mapWithKeys(function ($c) use ($area) {
        $recta = $c->recta($area);

        return [$c->id => [
            'potrero' => $c->potrero->nombreCompleto(),
            'estado' => $c->estadoFenologico->nombre,
            'fecha' => Formato::fecha($c->fecha),
            'muestras' => trans_choice('curvas.muestras_count', $c->muestras->count()),
            'recta' => $recta?->texto() ?? __('curvas.sin_recta'),
            'r2' => $recta?->textoR2() ?? '—',
            'vigencia' => $c->activo ? ['verde', __('curvas.vigente')] : ['gris', __('curvas.reemplazada')],
        ]];
    });
@endphp

<div {{ $this->atributosPaginacionResponsive() }}>
    <x-ui.pagina :titulo="__('curvas.titulo')" icono="fa-chart-line">
        @if($puedeEditar)
            <x-slot:acciones>
                <x-ui.boton icono="fa-plus" wire:click="nueva" data-nueva-curva>{{ __('curvas.nueva') }}</x-ui.boton>
            </x-slot:acciones>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg">
            <div class="flex flex-col gap-3 p-4 border-b border-gray-200">
                <p class="text-sm text-gray-500">{{ __('curvas.intro') }}</p>
                {{-- Filtros: campo, potrero (depende del campo), estado y vigencia --}}
                <div class="grid grid-cols-2 gap-3 md:flex md:items-center md:gap-2">
                    <div class="min-w-0 md:w-48">
                        <x-ui.combobox wire:model.live="filtroCampo" :opciones="$campos"
                                       :placeholder="__('curvas.filtros.campo')" :vacio="__('ui.filtros.todos')" />
                    </div>
                    <div class="min-w-0 md:w-48">
                        <x-ui.combobox wire:model.live="filtroPotrero" :opciones="$potreros" :disabled="$filtroCampo === null"
                                       :placeholder="__('curvas.filtros.potrero')" :vacio="__('ui.filtros.todos')" />
                    </div>
                    <div class="min-w-0 md:w-52">
                        <x-ui.combobox wire:model.live="filtroEstado" :opciones="$estados" :ordenar="false"
                                       :placeholder="__('curvas.filtros.estado')" :vacio="__('ui.filtros.todos')" />
                    </div>
                    <div class="min-w-0 md:w-40">
                        <x-ui.combobox wire:model.live="filtroVigencia" :opciones="$vigencias"
                                       :placeholder="__('curvas.filtros.vigencia')" :limpiable="false" :ordenar="false" />
                    </div>
                    @if($hayFiltro)
                        <x-ui.boton variante="enlace" icono="fa-filter-circle-xmark" wire:click="limpiarFiltros" class="col-span-2 justify-self-start">{{ __('ui.acciones.limpiar_filtros') }}</x-ui.boton>
                    @endif
                </div>
            </div>

            {{-- Escritorio --}}
            <x-ui.tabla>
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <x-ui.th>{{ __('curvas.columnas.potrero') }}</x-ui.th>
                        <x-ui.th>{{ __('curvas.columnas.estado') }}</x-ui.th>
                        <x-ui.th>{{ __('curvas.columnas.fecha') }}</x-ui.th>
                        <x-ui.th derecha>{{ __('curvas.columnas.muestras') }}</x-ui.th>
                        <x-ui.th>{{ __('curvas.columnas.recta') }}</x-ui.th>
                        <x-ui.th derecha>{{ __('curvas.columnas.r2') }}</x-ui.th>
                        <x-ui.th>{{ __('curvas.columnas.vigencia') }}</x-ui.th>
                        <x-ui.th derecha>{{ __('ui.acciones.acciones') }}</x-ui.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($curvas as $curva)
                        @php($fila = $filas[$curva->id])
                        <tr wire:key="fila-curva-{{ $curva->id }}" class="hover:bg-gray-50" data-curva="{{ $curva->id }}">
                            <td class="px-4 py-3">
                                <button type="button" wire:click="verDetalle({{ $curva->id }})" class="font-medium text-gray-800 hover:underline text-left">{{ $fila['potrero'] }}</button>
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $fila['estado'] }}</td>
                            <td class="px-4 py-3 tabular-nums text-gray-500">{{ $fila['fecha'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-500">{{ $curva->muestras->count() }}</td>
                            <td class="px-4 py-3 tabular-nums whitespace-nowrap text-gray-800">{{ $fila['recta'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-800">{{ $fila['r2'] }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge :color="$fila['vigencia'][0]">{{ $fila['vigencia'][1] }}</x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    @include('livewire.curvas.partials.acciones', ['curva' => $curva])
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-ui.vacio icono="fa-chart-line">{{ $mensajeVacio }}</x-ui.vacio>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.tabla>

            {{-- Mobile --}}
            <x-ui.cards>
                @forelse($curvas as $curva)
                    @php($fila = $filas[$curva->id])
                    <div wire:key="card-curva-{{ $curva->id }}" class="bg-white border border-gray-200 rounded-lg p-4 space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <button type="button" wire:click="verDetalle({{ $curva->id }})" class="font-medium text-gray-800 text-left">{{ $fila['potrero'] }}</button>
                                <p class="text-sm text-gray-500">{{ $fila['estado'] }} · <span class="tabular-nums">{{ $fila['fecha'] }}</span></p>
                            </div>
                            <x-ui.badge :color="$fila['vigencia'][0]">{{ $fila['vigencia'][1] }}</x-ui.badge>
                        </div>
                        <p class="text-sm tabular-nums text-gray-800">{{ $fila['recta'] }}</p>
                        <p class="text-sm text-gray-500">{{ $fila['muestras'] }} · {{ __('curvas.columnas.r2') }} {{ $fila['r2'] }}</p>
                        <div class="flex justify-end gap-2 pt-1">
                            @include('livewire.curvas.partials.acciones', ['curva' => $curva])
                        </div>
                    </div>
                @empty
                    <x-ui.vacio icono="fa-chart-line">{{ $mensajeVacio }}</x-ui.vacio>
                @endforelse
            </x-ui.cards>

            <x-ui.paginacion :current-page="$curvas->currentPage()" :total-pages="$curvas->lastPage()"
                             :offset="$offset" :per-page="$curvas->perPage()" :total-records="$curvas->total()"
                             class="rounded-b-lg" />
        </div>

        {{-- Lo que usan las curvas (P24): los estados de la pastura y el área del disco --}}
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
            <div class="min-w-0 lg:col-span-2">
                <livewire:curvas.estados-fenologicos />
            </div>

            <section class="bg-white border border-gray-200 rounded-lg" data-bloque-area-disco>
                <header class="flex items-start justify-between gap-3 px-4 py-3 border-b border-gray-200">
                    <div>
                        <h2 class="flex items-center gap-2 font-semibold text-gray-800">
                            <i class="fa-solid fa-circle-dot {{ Tema::acentoTexto() }}" aria-hidden="true"></i>
                            {{ __('curvas.area_disco.titulo') }}
                        </h2>
                        <p class="text-sm text-gray-500">{{ __('curvas.area_disco.intro') }}</p>
                    </div>
                    @if($puedeEditar)
                        <x-ui.boton variante="secundario" icono="fa-pen" wire:click="abrirAreaDisco" class="shrink-0">{{ __('ui.acciones.editar') }}</x-ui.boton>
                    @endif
                </header>
                <div class="p-4 text-sm">
                    <p class="text-2xl font-semibold text-gray-800 tabular-nums" data-area-disco>{{ __('curvas.area_disco.metros', ['valor' => $areaTexto]) }}</p>
                    <p class="mt-1 text-gray-500">{{ __('curvas.area_disco.equivalencia', ['kg' => Formato::numero(RectaBiomasa::kgMsHaDeMuestra(1, $area))]) }}</p>
                </div>
            </section>
        </div>
    </x-ui.pagina>

    @if($mostrarAreaDisco)
        <livewire:curvas.area-disco :key="'area-disco'" />
    @endif

    @include('livewire.curvas.partials.detalle')

    {{-- Alta / edición: hijo que se monta abierto (D57) --}}
    @if($formularioId !== null)
        <livewire:curvas.curva-formulario :curva-id="$formularioId ?: null" :potrero-id="$filtroPotrero" :estado-id="$filtroEstado"
                                          :key="'curva-formulario-'.$formularioId" />
    @endif

    @if($enBaja)
        <x-ui.modal-confirmacion tipo="peligro" :boton="__('ui.acciones.eliminar')" confirmar="baja" cancelar="cancelarBaja" :z="60">
            {{ __('curvas.confirmar_eliminar', [
                'potrero' => $enBaja->potrero->nombreCompleto(),
                'estado' => $enBaja->estadoFenologico->nombre,
                'fecha' => Formato::fecha($enBaja->fecha),
            ]) }}
        </x-ui.modal-confirmacion>
    @endif
</div>
