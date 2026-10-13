{{-- Área del disco (P23): modal hijo de Curvas (P24). --}}
<div>
    <x-ui.modal :titulo="__('curvas.area_disco.modificar')" icono="fa-circle-dot" cerrar="intentarCerrar" ancho="md" submit="guardar" data-formulario-area-disco>
        <x-ui.campo :etiqueta="__('curvas.area_disco.campo')" nombre="area_disco_m2" obligatorio :ayuda="__('curvas.area_disco.ayuda')">
            <x-ui.input nombre="area_disco_m2" wire:model="area_disco_m2" inputmode="decimal" autocomplete="off" autofocus />
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
