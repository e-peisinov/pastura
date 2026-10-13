{{-- Estados de la pastura (P23): bloque de Configuración. Tabla en escritorio, cards en mobile (regla 4). --}}
@php
    use App\Support\Tema;

    $filasEstados = $estadosFenologicos->map(fn ($e) => [
        'modelo' => $e,
        'uso' => __('configuracion.estados.uso', [
            'curvas' => trans_choice('configuracion.estados.curvas', $e->curvas_count, ['count' => $e->curvas_count]),
            'recorridas' => trans_choice('configuracion.estados.recorridas', $e->recorridas_count, ['count' => $e->recorridas_count]),
        ]),
    ]);
@endphp

<section class="bg-white border border-gray-200 rounded-lg" data-bloque-estados>
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-b border-gray-200">
        <div>
            <h2 class="flex items-center gap-2 font-semibold text-gray-800">
                <i class="fa-solid fa-seedling {{ Tema::acentoTexto() }}" aria-hidden="true"></i>
                {{ __('configuracion.estados.titulo') }}
            </h2>
            <p class="text-sm text-gray-500">{{ __('configuracion.estados.intro') }}</p>
        </div>
        @if($puedeEditar)
            <x-ui.boton icono="fa-plus" wire:click="nuevoEstado" class="shrink-0">{{ __('configuracion.estados.nuevo') }}</x-ui.boton>
        @endif
    </header>

    {{-- Escritorio --}}
    <x-ui.tabla>
        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-4 py-3">{{ __('configuracion.estados.columnas.nombre') }}</th>
                <th class="px-4 py-3">{{ __('configuracion.estados.columnas.uso') }}</th>
                <th class="px-4 py-3">{{ __('configuracion.estados.columnas.estado') }}</th>
                @if($puedeEditar)
                    <th class="px-4 py-3 text-right">{{ __('ui.acciones.acciones') }}</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($filasEstados as $fila)
                <tr wire:key="fila-estado-{{ $fila['modelo']->id }}" class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $fila['modelo']->nombre }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $fila['uso'] }}</td>
                    <td class="px-4 py-3">
                        <x-ui.badge :color="$fila['modelo']->activo ? 'verde' : 'gris'">
                            {{ $fila['modelo']->activo ? __('ui.estado.activo') : __('ui.estado.inactivo') }}
                        </x-ui.badge>
                    </td>
                    @if($puedeEditar)
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                @include('livewire.configuracion.partials.acciones-estado', ['estado' => $fila['modelo']])
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <x-ui.vacio icono="fa-seedling">{{ __('configuracion.estados.sin_registros') }}</x-ui.vacio>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.tabla>

    {{-- Mobile --}}
    <x-ui.cards>
        @forelse($filasEstados as $fila)
            <div wire:key="card-estado-{{ $fila['modelo']->id }}" class="bg-white border border-gray-200 rounded-lg p-4 space-y-2">
                <div class="flex items-start justify-between gap-3">
                    <p class="font-medium text-gray-800">{{ $fila['modelo']->nombre }}</p>
                    <x-ui.badge :color="$fila['modelo']->activo ? 'verde' : 'gris'">
                        {{ $fila['modelo']->activo ? __('ui.estado.activo') : __('ui.estado.inactivo') }}
                    </x-ui.badge>
                </div>
                <p class="text-sm text-gray-500">{{ $fila['uso'] }}</p>
                @if($puedeEditar)
                    <div class="flex justify-end gap-2 pt-1">
                        @include('livewire.configuracion.partials.acciones-estado', ['estado' => $fila['modelo']])
                    </div>
                @endif
            </div>
        @empty
            <x-ui.vacio icono="fa-seedling">{{ __('configuracion.estados.sin_registros') }}</x-ui.vacio>
        @endforelse
    </x-ui.cards>
</section>
