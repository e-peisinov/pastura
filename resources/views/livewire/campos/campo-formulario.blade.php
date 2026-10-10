{{-- Alta / edición de un campo (hijo, D57): se monta abierto y se apila con `z`. --}}
<div>
    <x-ui.modal :titulo="$campoId === null ? __('campos.nuevo') : __('campos.modificar')"
                :icono="$campoId === null ? 'fa-plus' : 'fa-pen'"
                cerrar="intentarCerrar" ancho="lg" submit="guardar" :z="$z" data-formulario-campo>
        <div class="grid grid-cols-1 gap-4">
            <x-ui.campo :etiqueta="__('campos.campos.nombre')" nombre="nombre" obligatorio>
                <x-ui.input nombre="nombre" wire:model="nombre" maxlength="150" autofocus autocomplete="off" />
            </x-ui.campo>
            <x-ui.campo :etiqueta="__('campos.campos.localidad')" nombre="localidad">
                <x-ui.input nombre="localidad" wire:model="localidad" maxlength="100" autocomplete="off" />
            </x-ui.campo>
            <x-ui.campo :etiqueta="__('campos.campos.observaciones')" nombre="observaciones">
                <x-ui.textarea nombre="observaciones" wire:model="observaciones" :filas="3" />
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
