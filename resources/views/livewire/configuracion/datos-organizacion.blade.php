{{-- Datos de la organización (marca blanca, D8): modal hijo de Configuración (P17). --}}
@php
    use App\Support\Tema;
@endphp

<div>
    <x-ui.modal :titulo="__('configuracion.organizacion.modificar')" icono="fa-seedling" cerrar="intentarCerrar" ancho="xl" submit="guardar" data-datos-organizacion>
        <div class="grid grid-cols-1 gap-4">
            <x-ui.campo :etiqueta="__('configuracion.organizacion.campos.nombre')" nombre="nombre" obligatorio>
                <x-ui.input nombre="nombre" wire:model="nombre" maxlength="100" autofocus autocomplete="off" />
            </x-ui.campo>

            {{-- Logo: el actual, el nuevo elegido o ninguno --}}
            <x-ui.campo :etiqueta="__('configuracion.organizacion.campos.logo')" nombre="logo" :ayuda="__('configuracion.organizacion.logo_ayuda')">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center" data-logo>
                    @if($logo && $logo->isPreviewable())
                        <img src="{{ $logo->temporaryUrl() }}" alt="{{ __('configuracion.organizacion.logo_nuevo') }}" title="{{ __('configuracion.organizacion.logo_nuevo') }}"
                             class="h-20 w-20 rounded-lg border border-gray-200 object-contain bg-white shrink-0" data-logo-nuevo>
                    @elseif($urlLogoActual)
                        <img src="{{ $urlLogoActual }}" alt="" class="h-20 w-20 rounded-lg border border-gray-200 object-contain bg-white shrink-0" data-logo-actual>
                    @else
                        <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-lg border border-dashed border-gray-300 text-xs text-gray-400">{{ __('configuracion.organizacion.sin_logo') }}</span>
                    @endif
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm cursor-pointer hover:bg-gray-50 focus-within:ring-2 focus-within:ring-gray-400">
                            <i class="fa-solid fa-upload" aria-hidden="true"></i>
                            <span wire:loading.remove wire:target="logo">{{ $logo || $urlLogoActual ? __('configuracion.organizacion.logo_cambiar') : __('configuracion.organizacion.logo_elegir') }}</span>
                            <span wire:loading wire:target="logo">{{ __('configuracion.organizacion.logo_subiendo') }}</span>
                            <input type="file" id="campo-logo" wire:model="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="sr-only">
                        </label>
                        @if($logo || $urlLogoActual)
                            <x-ui.boton variante="enlace" icono="fa-xmark" wire:click="sacarLogo">{{ __('configuracion.organizacion.logo_quitar') }}</x-ui.boton>
                        @endif
                    </div>
                </div>
            </x-ui.campo>

            {{-- Paleta: un puñado de opciones literales (D8), nunca un hex --}}
            <x-ui.campo :etiqueta="__('configuracion.organizacion.campos.color_acento')" nombre="color_acento" :ayuda="__('configuracion.organizacion.color_ayuda')">
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('configuracion.organizacion.campos.color_acento') }}" data-paletas>
                    @foreach($paletas as $paleta)
                        @php $elegida = $paleta->value === $color_acento; @endphp
                        <button type="button" role="radio" aria-checked="{{ $elegida ? 'true' : 'false' }}"
                                wire:click="elegirPaleta('{{ $paleta->value }}')" data-paleta="{{ $paleta->value }}"
                                class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-400 {{ $elegida ? 'border-gray-800 bg-gray-50 font-medium text-gray-800' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                            <span class="inline-block h-4 w-4 rounded-full {{ Tema::muestra($paleta) }}" aria-hidden="true"></span>
                            {{ $paleta->etiqueta() }}
                        </button>
                    @endforeach
                </div>
            </x-ui.campo>
        </div>

        <x-slot:footer>
            <x-ui.boton variante="secundario" wire:click="intentarCerrar">{{ __('ui.acciones.cancelar') }}</x-ui.boton>
            <x-ui.boton type="submit" icono="fa-check" wire:loading.attr="disabled" wire:target="guardar,logo">
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
