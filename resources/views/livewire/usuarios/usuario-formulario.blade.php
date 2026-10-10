{{-- Alta / edición de un usuario con su matriz de permisos (hijo de Usuarios, D49). --}}
<div>
    <x-ui.modal :titulo="$usuarioId === null ? __('usuarios.nuevo') : __('usuarios.modificar')"
                :icono="$usuarioId === null ? 'fa-plus' : 'fa-pen'"
                cerrar="intentarCerrar" ancho="2xl" submit="guardar" data-formulario-usuario>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-ui.campo :etiqueta="__('usuarios.campos.nombre')" nombre="nombre" obligatorio>
                <x-ui.input nombre="nombre" wire:model="nombre" maxlength="100" autofocus autocomplete="off" />
            </x-ui.campo>
            <x-ui.campo :etiqueta="__('usuarios.campos.email')" nombre="email" obligatorio>
                <x-ui.input nombre="email" type="email" wire:model="email" maxlength="150" autocomplete="off" />
            </x-ui.campo>
        </div>

        <x-ui.campo :etiqueta="__('usuarios.campos.rol')" nombre="rol" obligatorio :ayuda="__('usuarios.ayuda_rol')">
            <x-ui.combobox wire:model.live="rol" :opciones="$roles" :ordenar="false" />
        </x-ui.campo>

        @if($usuarioId === null)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.campo :etiqueta="__('usuarios.campos.contrasena')" nombre="contrasena" obligatorio :ayuda="__('usuarios.ayuda_contrasena')">
                    <x-ui.input nombre="contrasena" type="password" wire:model="contrasena" autocomplete="new-password" />
                </x-ui.campo>
                <x-ui.campo :etiqueta="__('usuarios.campos.contrasena_confirmation')" nombre="contrasena_confirmation" obligatorio>
                    <x-ui.input nombre="contrasena_confirmation" type="password" wire:model="contrasena_confirmation" autocomplete="new-password" />
                </x-ui.campo>
            </div>
        @endif

        {{-- Matriz de permisos: una fila por módulo con ruta, tres niveles --}}
        <fieldset data-matriz-permisos>
            <legend class="block text-sm font-medium text-gray-700">{{ __('usuarios.campos.permisos') }}</legend>
            <p class="mt-1 text-xs text-gray-500">{{ __('usuarios.ayuda_permisos') }}</p>
            <ul class="mt-2 divide-y divide-gray-200 rounded-md border border-gray-200">
                @foreach($modulos as $modulo)
                    <li wire:key="permiso-{{ $modulo->nombre }}" class="flex flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center sm:justify-between" data-permiso="{{ $modulo->nombre }}">
                        <span class="flex items-center gap-2 text-sm text-gray-800">
                            <i class="fa-solid {{ $modulo->icono }} w-5 text-center text-gray-400" aria-hidden="true"></i>
                            {{ $modulo->etiqueta() }}
                        </span>
                        <div class="sm:text-right">
                            <x-ui.opciones wire:model="permisos.{{ $modulo->nombre }}" :opciones="$niveles" :nombre="'permiso-'.$modulo->nombre" />
                            @error('permisos.'.$modulo->nombre)
                                <p class="mt-1 text-sm text-red-600" data-error="permisos.{{ $modulo->nombre }}">{{ $message }}</p>
                            @enderror
                        </div>
                    </li>
                @endforeach
            </ul>
        </fieldset>

        <x-slot:footer>
            <x-ui.boton variante="secundario" wire:click="intentarCerrar">{{ __('ui.acciones.cancelar') }}</x-ui.boton>
            <x-ui.boton type="submit" icono="fa-check" wire:loading.attr="disabled" wire:target="guardar">
                <span wire:loading.remove wire:target="guardar">{{ __('ui.acciones.guardar') }}</span>
                <span wire:loading wire:target="guardar">{{ __('ui.acciones.guardando') }}</span>
            </x-ui.boton>
        </x-slot:footer>
    </x-ui.modal>

    @if($confirmandoDescarte)
        <x-ui.modal-confirmacion tipo="advertencia" :titulo="__('ui.descarte.titulo')" :boton="__('ui.descarte.boton')"
                                 confirmar="descartarCambios" cancelar="seguirEditando" :z="$this::Z_DESCARTE">
            {{ __('ui.descarte.pregunta') }}
        </x-ui.modal-confirmacion>
    @endif
</div>
