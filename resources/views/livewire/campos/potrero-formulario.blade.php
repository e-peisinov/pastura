{{-- Alta / edición de un potrero con su contorno (hijo, D57; P5): apilado sobre la ficha del campo. --}}
<div>
    <x-ui.modal :titulo="$potreroId === null ? __('campos.potrero.nuevo') : __('campos.potrero.modificar')"
                :icono="$potreroId === null ? 'fa-plus' : 'fa-pen'"
                cerrar="intentarCerrar" ancho="3xl" submit="guardar" :z="$z" data-formulario-potrero>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-ui.campo :etiqueta="__('campos.potrero.campos.nombre')" nombre="nombre" obligatorio>
                <x-ui.input nombre="nombre" wire:model="nombre" maxlength="100" autofocus autocomplete="off" />
            </x-ui.campo>
            <x-ui.campo :etiqueta="__('campos.potrero.campos.tipo_pastura')" nombre="tipo_pastura_id" :ayuda="__('campos.potrero.ayuda_tipo_pastura')">
                <x-ui.combobox wire:model="tipo_pastura_id" :opciones="$tipos" :placeholder="__('campos.potrero.sin_tipo_pastura')" />
            </x-ui.campo>

            {{-- Contorno: el mapa lo edita en el navegador y lo devuelve por entangle --}}
            <div class="sm:col-span-2" x-data="mapaContorno({ contorno: $wire.entangle('contorno'), vecinos: @js($vecinos) })" data-editor-contorno>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <span class="text-sm font-medium text-gray-700">{{ __('campos.potrero.campos.contorno') }}</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm tabular-nums text-gray-600" data-superficie
                              x-text="hectareas === null ? @js(__('campos.potrero.sin_contorno')) : @js(__('campos.potrero.superficie', ['valor' => '__V__'])).replace('__V__', hectareas.toLocaleString('es-AR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }))"></span>
                        <label class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 cursor-pointer">
                            <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                            {{ __('campos.potrero.importar') }}
                            <input type="file" accept=".kml,.kmz,application/vnd.google-earth.kml+xml,application/vnd.google-earth.kmz" class="sr-only" x-on:change="importar($event)" data-importar-kml>
                        </label>
                        <button type="button" x-show="hectareas !== null" x-on:click="borrar()"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-red-700 hover:bg-red-50">
                            <i class="fa-solid fa-eraser" aria-hidden="true"></i>
                            {{ __('campos.potrero.borrar_contorno') }}
                        </button>
                    </div>
                </div>
                <div wire:ignore class="h-72 sm:h-96 rounded-md overflow-hidden border border-gray-200">
                    <div x-ref="mapa" class="h-full w-full"></div>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ __('campos.potrero.ayuda_contorno') }}</p>
                <p x-show="error === 'sin-poligono'" x-cloak class="mt-1 text-sm text-red-600">{{ __('campos.potrero.error_sin_poligono') }}</p>
                <p x-show="error === 'archivo'" x-cloak class="mt-1 text-sm text-red-600">{{ __('campos.potrero.error_archivo') }}</p>
                @error('contorno') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <x-ui.campo class="sm:col-span-2" :etiqueta="__('campos.potrero.campos.observaciones')" nombre="observaciones">
                <x-ui.textarea nombre="observaciones" wire:model="observaciones" :filas="2" />
            </x-ui.campo>
        </div>

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
                                 confirmar="descartarCambios" cancelar="seguirEditando" :z="$zDescarte">
            {{ __('ui.descarte.pregunta') }}
        </x-ui.modal-confirmacion>
    @endif
</div>
