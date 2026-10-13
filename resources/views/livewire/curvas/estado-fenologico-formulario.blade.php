{{-- Alta / edición de un estado de la pastura (P23). Hijo de EstadosFenologicos (P24). --}}
<div>
    <x-ui.modal :titulo="$estadoId === null ? __('curvas.estados.nuevo') : __('curvas.estados.modificar')"
                :icono="$estadoId === null ? 'fa-plus' : 'fa-pen'"
                cerrar="intentarCerrar" ancho="md" submit="guardar" data-formulario-estado>
        <x-ui.campo :etiqueta="__('curvas.estados.campos.nombre')" nombre="nombre" obligatorio :ayuda="__('curvas.estados.ayuda_nombre')">
            <x-ui.input nombre="nombre" wire:model="nombre" maxlength="100" autofocus autocomplete="off" />
        </x-ui.campo>

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
