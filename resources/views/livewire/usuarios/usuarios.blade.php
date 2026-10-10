@php
    // Lo que comparten tabla y cards se calcula una sola vez (§5).
    $mensajeVacio = $hayFiltro ? __('usuarios.sin_resultados') : __('usuarios.sin_registros');
@endphp

<div>
    <x-ui.pagina :titulo="__('usuarios.titulo')" icono="fa-user-gear">
        @if($puedeEditar)
            <x-slot:acciones>
                <x-ui.boton icono="fa-plus" wire:click="nuevo">{{ __('usuarios.nuevo') }}</x-ui.boton>
            </x-slot:acciones>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg">
            {{-- Filtro de estado --}}
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-end p-4 border-b border-gray-200">
                <div class="w-full md:w-44">
                    <x-ui.combobox wire:model.live="filtroEstado" :opciones="$estados"
                                   :placeholder="__('ui.filtros.estado')" :limpiable="false" :ordenar="false" />
                </div>
            </div>

            {{-- Escritorio --}}
            <x-ui.tabla>
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <x-ui.th columna="usuario" :orden="$this->orden()">{{ __('usuarios.columnas.usuario') }}</x-ui.th>
                        <x-ui.th columna="rol" :orden="$this->orden()">{{ __('usuarios.columnas.rol') }}</x-ui.th>
                        {{-- El resumen de permisos se arma en PHP: no se ordena. --}}
                        <x-ui.th>{{ __('usuarios.columnas.permisos') }}</x-ui.th>
                        <x-ui.th columna="estado" :orden="$this->orden()">{{ __('usuarios.columnas.estado') }}</x-ui.th>
                        @if($puedeEditar)
                            <x-ui.th derecha>{{ __('ui.acciones.acciones') }}</x-ui.th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($usuarios as $usuario)
                        <tr wire:key="fila-{{ $usuario->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">
                                    {{ $usuario->name }}
                                    @if($usuario->id === $yoId)
                                        <x-ui.badge color="azul" class="ml-1">{{ __('usuarios.yo') }}</x-ui.badge>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">{{ $usuario->email }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $usuario->rol->etiqueta() }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $resumenes[$usuario->id] }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge :color="$usuario->activo ? 'verde' : 'gris'">
                                    {{ $usuario->activo ? __('ui.estado.activo') : __('ui.estado.inactivo') }}
                                </x-ui.badge>
                            </td>
                            @if($puedeEditar)
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        @include('livewire.usuarios.partials.acciones', ['usuario' => $usuario])
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $puedeEditar ? 5 : 4 }}">
                                <x-ui.vacio icono="fa-user-gear">{{ $mensajeVacio }}</x-ui.vacio>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.tabla>

            {{-- Mobile --}}
            <x-ui.cards>
                @forelse($usuarios as $usuario)
                    <div wire:key="card-{{ $usuario->id }}" class="bg-white border border-gray-200 rounded-lg p-4 space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800">
                                    {{ $usuario->name }}
                                    @if($usuario->id === $yoId)
                                        <x-ui.badge color="azul" class="ml-1">{{ __('usuarios.yo') }}</x-ui.badge>
                                    @endif
                                </p>
                                <p class="text-sm text-gray-500 truncate">{{ $usuario->email }}</p>
                            </div>
                            <x-ui.badge :color="$usuario->activo ? 'verde' : 'gris'">
                                {{ $usuario->activo ? __('ui.estado.activo') : __('ui.estado.inactivo') }}
                            </x-ui.badge>
                        </div>
                        <p class="text-sm text-gray-800">{{ $usuario->rol->etiqueta() }}</p>
                        <p class="text-sm text-gray-500">{{ $resumenes[$usuario->id] }}</p>
                        @if($puedeEditar)
                            <div class="flex justify-end gap-2 pt-1">
                                @include('livewire.usuarios.partials.acciones', ['usuario' => $usuario])
                            </div>
                        @endif
                    </div>
                @empty
                    <x-ui.vacio icono="fa-user-gear">{{ $mensajeVacio }}</x-ui.vacio>
                @endforelse
            </x-ui.cards>
        </div>
    </x-ui.pagina>

    @if($formulario !== null)
        <livewire:usuarios.usuario-formulario :usuario-id="$formulario ?: null" :key="'usuario-'.$formulario" />
    @endif

    {{-- Cambio de contraseña desde el listado --}}
    @if($usuarioContrasena)
        <x-ui.modal :titulo="__('usuarios.contrasena.titulo', ['nombre' => $usuarioContrasena->name])" icono="fa-key"
                    cerrar="intentarCerrar" ancho="sm" submit="guardarContrasena" data-formulario-contrasena>
            <x-ui.campo :etiqueta="__('usuarios.campos.contrasena')" nombre="contrasena" obligatorio :ayuda="__('usuarios.ayuda_contrasena')">
                <x-ui.input nombre="contrasena" type="password" wire:model="contrasena" autocomplete="new-password" autofocus />
            </x-ui.campo>
            <x-ui.campo :etiqueta="__('usuarios.campos.contrasena_confirmation')" nombre="contrasena_confirmation" obligatorio>
                <x-ui.input nombre="contrasena_confirmation" type="password" wire:model="contrasena_confirmation" autocomplete="new-password" />
            </x-ui.campo>

            <x-slot:footer>
                <x-ui.boton variante="secundario" wire:click="intentarCerrar">{{ __('ui.acciones.cancelar') }}</x-ui.boton>
                <x-ui.boton type="submit" icono="fa-check" wire:loading.attr="disabled" wire:target="guardarContrasena">
                    <span wire:loading.remove wire:target="guardarContrasena">{{ __('usuarios.contrasena.boton') }}</span>
                    <span wire:loading wire:target="guardarContrasena">{{ __('ui.acciones.guardando') }}</span>
                </x-ui.boton>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if($confirmandoDescarte)
        <x-ui.modal-confirmacion tipo="advertencia" :titulo="__('ui.descarte.titulo')" :boton="__('ui.descarte.boton')"
                                 confirmar="descartarCambios" cancelar="seguirEditando" :z="60">
            {{ __('ui.descarte.pregunta') }}
        </x-ui.modal-confirmacion>
    @endif

    {{-- Baja lógica --}}
    @if($usuarioEnBaja)
        <x-ui.modal-confirmacion tipo="advertencia" :boton="__('ui.acciones.desactivar')" confirmar="baja" cancelar="cancelarBaja">
            {{ __('usuarios.confirmar_desactivar', ['nombre' => $usuarioEnBaja->name]) }}
        </x-ui.modal-confirmacion>
    @endif
</div>
