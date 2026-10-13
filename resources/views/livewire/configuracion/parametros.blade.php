{{-- Parámetros del seguimiento (P17) y área del disco (P23): modal hijo de Configuración. --}}
<div>
    <x-ui.modal :titulo="__('configuracion.parametros.modificar')" icono="fa-sliders" cerrar="intentarCerrar" ancho="lg" submit="guardar" data-parametros>
        <x-ui.campo :etiqueta="__('configuracion.parametros.campos.dias_sin_medir')" nombre="dias_sin_medir" obligatorio :ayuda="__('configuracion.parametros.ayuda_dias_sin_medir')">
            <x-ui.input nombre="dias_sin_medir" type="number" min="1" :max="$this::DIAS_MAXIMO" step="1" wire:model="dias_sin_medir" inputmode="numeric" autofocus />
        </x-ui.campo>
        <x-ui.campo :etiqueta="__('configuracion.parametros.campos.puntos_minimos')" nombre="puntos_minimos" obligatorio :ayuda="__('configuracion.parametros.ayuda_puntos_minimos')">
            <x-ui.input nombre="puntos_minimos" type="number" min="1" :max="$this::PUNTOS_MAXIMO" step="1" wire:model="puntos_minimos" inputmode="numeric" />
        </x-ui.campo>
        <x-ui.campo :etiqueta="__('configuracion.parametros.campos.area_disco_m2')" nombre="area_disco_m2" obligatorio :ayuda="__('configuracion.parametros.ayuda_area_disco')">
            <x-ui.input nombre="area_disco_m2" wire:model="area_disco_m2" inputmode="decimal" autocomplete="off" />
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
