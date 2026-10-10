{{-- Auditoría (P18): período y filtros por qué, acción y usuario; tabla en
     escritorio y cards en mobile (§5). Se lee, no se edita. --}}
@php
    use App\Support\Formato;

    // Lo que comparten tabla y cards se calcula una sola vez (§5).
    $offset = ($registros->currentPage() - 1) * $registros->perPage();
    $mensajeVacio = $hayFiltro ? __('auditoria.sin_resultados') : __('auditoria.sin_registros');
    $filas = collect($registros->items())->map(fn ($r) => [
        'modelo' => $r,
        'fecha' => Formato::fecha($r->created_at),
        'hora' => $r->created_at->format('H:i'),
        'usuario' => $r->nombreUsuario(),
        'descripcion' => $r->descripcion(),
        'enlace' => $r->enlace(),
    ]);
@endphp

<div {{ $this->atributosPaginacionResponsive() }}>
    <x-ui.pagina :titulo="__('auditoria.titulo')" icono="fa-clipboard-check">
        <div class="bg-white border border-gray-200 rounded-lg">
            {{-- Período y filtros: qué, quién y acción --}}
            <div class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center p-4 border-b border-gray-200">
                <div class="flex items-center gap-2" data-selector-periodo>
                    <label for="campo-desde" class="text-sm text-gray-500">{{ __('auditoria.periodo.desde') }}</label>
                    <div class="w-40"><x-ui.input nombre="desde" type="date" wire:model.live="desde" /></div>
                    <label for="campo-hasta" class="text-sm text-gray-500">{{ __('auditoria.periodo.hasta') }}</label>
                    <div class="w-40"><x-ui.input nombre="hasta" type="date" wire:model.live="hasta" /></div>
                </div>
                <div class="w-full md:w-48">
                    <x-ui.combobox wire:model.live="filtroEntidad" :opciones="$entidades"
                                   :placeholder="__('auditoria.filtros.que')" :vacio="__('ui.filtros.todos')" :ordenar="false" />
                </div>
                <div class="w-full md:w-40">
                    <x-ui.combobox wire:model.live="filtroAccion" :opciones="$acciones"
                                   :placeholder="__('auditoria.filtros.accion')" :vacio="__('ui.filtros.todas')" :ordenar="false" />
                </div>
                <div class="w-full md:w-48">
                    <x-ui.combobox wire:model.live="filtroUsuario" :opciones="$usuarios"
                                   :placeholder="__('auditoria.filtros.usuario')" :vacio="__('ui.filtros.todos')" />
                </div>
                @if($hayFiltro)
                    <x-ui.boton variante="enlace" wire:click="limpiarFiltros">{{ __('ui.acciones.limpiar_filtros') }}</x-ui.boton>
                @endif
            </div>

            {{-- Escritorio --}}
            <x-ui.tabla>
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <x-ui.th>{{ __('auditoria.columnas.fecha') }}</x-ui.th>
                        <x-ui.th>{{ __('auditoria.columnas.usuario') }}</x-ui.th>
                        <x-ui.th>{{ __('auditoria.columnas.que') }}</x-ui.th>
                        <x-ui.th>{{ __('auditoria.columnas.detalle') }}</x-ui.th>
                        <x-ui.th derecha>{{ __('ui.acciones.acciones') }}</x-ui.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($filas as $fila)
                        <tr wire:key="fila-{{ $fila['modelo']->id }}" class="hover:bg-gray-50"
                            data-registro="{{ $fila['modelo']->entidad->value }}-{{ $fila['modelo']->accion->value }}">
                            <td class="px-4 py-3 tabular-nums whitespace-nowrap">
                                {{ $fila['fecha'] }}
                                <span class="block text-xs text-gray-500">{{ $fila['hora'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $fila['usuario'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <x-ui.badge :color="$fila['modelo']->accion->color()">{{ $fila['modelo']->accion->etiqueta() }}</x-ui.badge>
                                <span class="block text-xs text-gray-500 mt-1"><i class="fa-solid {{ $fila['modelo']->entidad->icono() }} mr-1" aria-hidden="true"></i>{{ $fila['modelo']->entidad->etiqueta() }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $fila['descripcion'] }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    @if($fila['enlace'])
                                        <x-ui.accion icono="fa-arrow-up-right-from-square" :etiqueta="__('auditoria.ir')" :href="$fila['enlace']" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-ui.vacio icono="fa-clipboard-check">{{ $mensajeVacio }}</x-ui.vacio>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.tabla>

            {{-- Mobile --}}
            <x-ui.cards>
                @forelse($filas as $fila)
                    <div wire:key="card-{{ $fila['modelo']->id }}" class="bg-white border border-gray-200 rounded-lg p-4 space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-xs text-gray-500 tabular-nums">{{ $fila['fecha'] }} {{ $fila['hora'] }} · {{ $fila['usuario'] }}</p>
                            <x-ui.badge :color="$fila['modelo']->accion->color()">{{ $fila['modelo']->accion->etiqueta() }}</x-ui.badge>
                        </div>
                        <p class="text-gray-800">{{ $fila['descripcion'] }}</p>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs text-gray-500"><i class="fa-solid {{ $fila['modelo']->entidad->icono() }} mr-1" aria-hidden="true"></i>{{ $fila['modelo']->entidad->etiqueta() }}</span>
                            @if($fila['enlace'])
                                <x-ui.accion icono="fa-arrow-up-right-from-square" :etiqueta="__('auditoria.ir')" :href="$fila['enlace']" />
                            @endif
                        </div>
                    </div>
                @empty
                    <x-ui.vacio icono="fa-clipboard-check">{{ $mensajeVacio }}</x-ui.vacio>
                @endforelse
            </x-ui.cards>

            <x-ui.paginacion :current-page="$registros->currentPage()" :total-pages="$registros->lastPage()"
                             :offset="$offset" :per-page="$registros->perPage()" :total-records="$registros->total()"
                             class="rounded-b-lg" />
        </div>
    </x-ui.pagina>
</div>
