@php
    use App\Support\Formato;
    use App\Support\Tema;


    // Lo que comparten tabla y cards se calcula una sola vez (§5).
    $filas = $tipos->map(fn ($t) => [
        'modelo' => $t,
        'ingreso' => $t->tieneAlturas() ? __('configuracion.tipos.altura', ['valor' => Formato::numero($t->altura_ingreso_cm, 1)]) : null,
        'salida' => $t->tieneAlturas() ? __('configuracion.tipos.altura', ['valor' => Formato::numero($t->altura_salida_cm, 1)]) : null,
        'uso' => trans_choice('configuracion.tipos.uso', $t->potreros_count, ['count' => $t->potreros_count]),
    ]);
@endphp

<div>
    <x-ui.pagina :titulo="__('configuracion.titulo')" icono="fa-gear">
        <div class="space-y-6">
            {{-- Datos de la organización (marca blanca, D8) --}}
            <section class="bg-white border border-gray-200 rounded-lg" data-bloque-organizacion>
                <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-b border-gray-200">
                    <div>
                        <h2 class="flex items-center gap-2 font-semibold text-gray-800">
                            <i class="fa-solid fa-seedling {{ Tema::acentoTexto() }}" aria-hidden="true"></i>
                            {{ __('configuracion.organizacion.titulo') }}
                        </h2>
                        <p class="text-sm text-gray-500">{{ __('configuracion.organizacion.intro') }}</p>
                    </div>
                    @if($puedeEditar)
                        <x-ui.boton variante="secundario" icono="fa-pen" wire:click="abrirOrganizacion">{{ __('ui.acciones.editar') }}</x-ui.boton>
                    @endif
                </header>
                <div class="flex flex-col gap-4 sm:flex-row p-4">
                    <div class="shrink-0">
                        @if($configuracion->logo_path)
                            <img src="{{ asset('storage/'.$configuracion->logo_path) }}" alt="" class="h-20 w-20 rounded-lg border border-gray-200 object-contain bg-white" data-logo-organizacion>
                        @else
                            <span class="flex h-20 w-20 items-center justify-center rounded-lg border border-dashed border-gray-300 text-xs text-gray-400">{{ __('configuracion.organizacion.sin_logo') }}</span>
                        @endif
                    </div>
                    <dl class="grid flex-1 grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-gray-500">{{ __('configuracion.organizacion.campos.nombre') }}</dt>
                            <dd class="font-medium text-gray-800">{{ $configuracion->nombre }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('configuracion.organizacion.campos.color_acento') }}</dt>
                            <dd class="flex items-center gap-2 font-medium text-gray-800">
                                <span class="inline-block h-4 w-4 rounded-full {{ Tema::muestra($configuracion->color_acento) }}" aria-hidden="true"></span>
                                {{ $configuracion->color_acento->etiqueta() }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            {{-- Tipos de pastura: alturas de referencia (P16) --}}
            <section class="bg-white border border-gray-200 rounded-lg" data-bloque-tipos>
                <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-b border-gray-200">
                    <div>
                        <h2 class="flex items-center gap-2 font-semibold text-gray-800">
                            <i class="fa-solid fa-wheat-awn {{ Tema::acentoTexto() }}" aria-hidden="true"></i>
                            {{ __('configuracion.tipos.titulo') }}
                        </h2>
                        <p class="text-sm text-gray-500">{{ __('configuracion.tipos.intro') }}</p>
                    </div>
                    @if($puedeEditar)
                        <x-ui.boton icono="fa-plus" wire:click="nuevoTipo" class="shrink-0">{{ __('configuracion.tipos.nuevo') }}</x-ui.boton>
                    @endif
                </header>

                {{-- Escritorio --}}
                <x-ui.tabla>
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('configuracion.tipos.columnas.nombre') }}</th>
                            <th class="px-4 py-3">{{ __('configuracion.tipos.columnas.ingreso') }}</th>
                            <th class="px-4 py-3">{{ __('configuracion.tipos.columnas.salida') }}</th>
                            <th class="px-4 py-3">{{ __('configuracion.tipos.columnas.uso') }}</th>
                            <th class="px-4 py-3">{{ __('configuracion.tipos.columnas.estado') }}</th>
                            @if($puedeEditar)
                                <th class="px-4 py-3 text-right">{{ __('ui.acciones.acciones') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($filas as $fila)
                            <tr wire:key="fila-tipo-{{ $fila['modelo']->id }}" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $fila['modelo']->nombre }}</td>
                                @if($fila['ingreso'] !== null)
                                    <td class="px-4 py-3 text-gray-800 tabular-nums whitespace-nowrap">{{ $fila['ingreso'] }}</td>
                                    <td class="px-4 py-3 text-gray-800 tabular-nums whitespace-nowrap">{{ $fila['salida'] }}</td>
                                @else
                                    <td class="px-4 py-3 text-gray-400" colspan="2">{{ __('configuracion.tipos.sin_alturas') }}</td>
                                @endif
                                <td class="px-4 py-3 text-gray-500">{{ $fila['uso'] }}</td>
                                <td class="px-4 py-3">
                                    <x-ui.badge :color="$fila['modelo']->activo ? 'verde' : 'gris'">
                                        {{ $fila['modelo']->activo ? __('ui.estado.activo') : __('ui.estado.inactivo') }}
                                    </x-ui.badge>
                                </td>
                                @if($puedeEditar)
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1">
                                            @include('livewire.configuracion.partials.acciones-tipo', ['tipo' => $fila['modelo']])
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-ui.vacio icono="fa-wheat-awn">{{ __('configuracion.tipos.sin_registros') }}</x-ui.vacio>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.tabla>

                {{-- Mobile --}}
                <x-ui.cards>
                    @forelse($filas as $fila)
                        <div wire:key="card-tipo-{{ $fila['modelo']->id }}" class="bg-white border border-gray-200 rounded-lg p-4 space-y-2">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-medium text-gray-800">{{ $fila['modelo']->nombre }}</p>
                                <x-ui.badge :color="$fila['modelo']->activo ? 'verde' : 'gris'">
                                    {{ $fila['modelo']->activo ? __('ui.estado.activo') : __('ui.estado.inactivo') }}
                                </x-ui.badge>
                            </div>
                            <p class="text-sm tabular-nums {{ $fila['ingreso'] !== null ? 'text-gray-800' : 'text-gray-400' }}">
                                @if($fila['ingreso'] !== null)
                                    {{ __('configuracion.tipos.columnas.ingreso') }} {{ $fila['ingreso'] }} · {{ __('configuracion.tipos.columnas.salida') }} {{ $fila['salida'] }}
                                @else
                                    {{ __('configuracion.tipos.sin_alturas') }}
                                @endif
                            </p>
                            <p class="text-sm text-gray-500">{{ $fila['uso'] }}</p>
                            @if($puedeEditar)
                                <div class="flex justify-end gap-2 pt-1">
                                    @include('livewire.configuracion.partials.acciones-tipo', ['tipo' => $fila['modelo']])
                                </div>
                            @endif
                        </div>
                    @empty
                        <x-ui.vacio icono="fa-wheat-awn">{{ __('configuracion.tipos.sin_registros') }}</x-ui.vacio>
                    @endforelse
                </x-ui.cards>
            </section>

            {{-- Parámetros del seguimiento (P17) --}}
            <section class="bg-white border border-gray-200 rounded-lg" data-bloque-parametros>
                <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-b border-gray-200">
                    <div>
                        <h2 class="flex items-center gap-2 font-semibold text-gray-800">
                            <i class="fa-solid fa-sliders {{ Tema::acentoTexto() }}" aria-hidden="true"></i>
                            {{ __('configuracion.parametros.titulo') }}
                        </h2>
                        <p class="text-sm text-gray-500">{{ __('configuracion.parametros.intro') }}</p>
                    </div>
                    @if($puedeEditar)
                        <x-ui.boton variante="secundario" icono="fa-pen" wire:click="abrirParametros">{{ __('ui.acciones.editar') }}</x-ui.boton>
                    @endif
                </header>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 p-4 text-sm">
                    <div>
                        <dt class="text-gray-500">{{ __('configuracion.parametros.campos.dias_sin_medir') }}</dt>
                        <dd class="font-medium text-gray-800 tabular-nums">{{ trans_choice('configuracion.parametros.dias', $configuracion->dias_sin_medir, ['count' => $configuracion->dias_sin_medir]) }}</dd>
                        <dd class="text-xs text-gray-500">{{ __('configuracion.parametros.ayuda_dias_sin_medir') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('configuracion.parametros.campos.puntos_minimos') }}</dt>
                        <dd class="font-medium text-gray-800 tabular-nums">{{ trans_choice('configuracion.parametros.puntos', $configuracion->puntos_minimos, ['count' => $configuracion->puntos_minimos]) }}</dd>
                        <dd class="text-xs text-gray-500">{{ __('configuracion.parametros.ayuda_puntos_minimos') }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </x-ui.pagina>

    @if($mostrarOrganizacion)
        <livewire:configuracion.datos-organizacion :key="'datos-organizacion'" />
    @endif

    @if($mostrarParametros)
        <livewire:configuracion.parametros :key="'parametros'" />
    @endif

    @if($tipoFormulario !== null)
        <livewire:configuracion.tipo-pastura-formulario :tipo-id="$tipoFormulario ?: null" :key="'tipo-'.$tipoFormulario" />
    @endif

    {{-- Baja: eliminar sin potreros, desactivar con potreros --}}
    @if($tipoEnBaja)
        <x-ui.modal-confirmacion :tipo="$bajaDesactiva ? 'advertencia' : 'peligro'"
                                 :boton="$bajaDesactiva ? __('ui.acciones.desactivar') : __('ui.acciones.eliminar')"
                                 confirmar="baja" cancelar="cancelarBaja">
            {{ __($bajaDesactiva ? 'configuracion.tipos.confirmar_desactivar' : 'configuracion.tipos.confirmar_eliminar', ['nombre' => $tipoEnBaja->nombre]) }}
        </x-ui.modal-confirmacion>
    @endif
</div>
