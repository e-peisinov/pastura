{{-- Alta / edición de un tipo de pastura: sus alturas de referencia (P16). Hijo de Configuración. --}}
<div>
    <x-ui.modal :titulo="$tipoId === null ? __('configuracion.tipos.nuevo') : __('configuracion.tipos.modificar')"
                :icono="$tipoId === null ? 'fa-plus' : 'fa-pen'"
                cerrar="intentarCerrar" ancho="lg" submit="guardar" data-formulario-tipo-pastura>
        <x-ui.campo :etiqueta="__('configuracion.tipos.campos.nombre')" nombre="nombre" obligatorio :ayuda="__('configuracion.tipos.ayuda_nombre')">
            <x-ui.input nombre="nombre" wire:model="nombre" maxlength="100" autofocus autocomplete="off" />
        </x-ui.campo>

        <fieldset class="space-y-2">
            <legend class="text-sm font-medium text-gray-700">{{ __('configuracion.tipos.alturas') }}</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.campo :etiqueta="__('configuracion.tipos.campos.altura_ingreso_cm')" nombre="altura_ingreso_cm">
                    <x-ui.input nombre="altura_ingreso_cm" wire:model="altura_ingreso_cm" inputmode="decimal" autocomplete="off" />
                </x-ui.campo>
                <x-ui.campo :etiqueta="__('configuracion.tipos.campos.altura_salida_cm')" nombre="altura_salida_cm">
                    <x-ui.input nombre="altura_salida_cm" wire:model="altura_salida_cm" inputmode="decimal" autocomplete="off" />
                </x-ui.campo>
            </div>
            <p class="text-xs text-gray-500">{{ __('configuracion.tipos.ayuda_alturas') }}</p>
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
