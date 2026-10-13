{{-- Alta / edición de una curva de biomasa (hijo, D57; P23): potrero, estado, fecha y las muestras, con la recta en vivo. --}}
@php
    use App\Support\Biomasa\RectaBiomasa;
    use App\Support\Formato;
@endphp
<div>
    <x-ui.modal :titulo="$curvaId === null ? __('curvas.nueva') : __('curvas.modificar')"
                :icono="$curvaId === null ? 'fa-plus' : 'fa-pen'"
                cerrar="intentarCerrar" ancho="3xl" submit="guardar" data-formulario-curva>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-ui.campo :etiqueta="__('curvas.campos.potrero')" nombre="potrero_id" obligatorio>
                <x-ui.combobox wire:model.live="potrero_id" :opciones="$potreros" :placeholder="__('curvas.campos.potrero')" />
            </x-ui.campo>
            <x-ui.campo :etiqueta="__('curvas.campos.estado')" nombre="estado_fenologico_id" obligatorio>
                <x-ui.combobox wire:model.live="estado_fenologico_id" :opciones="$estados" :ordenar="false" :placeholder="__('curvas.campos.estado')" />
            </x-ui.campo>
            @if($reemplaza)
                <p class="sm:col-span-2 -mt-2 text-xs text-gray-500" data-reemplaza>
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    {{ __('curvas.ayuda_reemplaza', ['fecha' => Formato::fecha($reemplaza->fecha)]) }}
                </p>
            @endif
            <x-ui.campo :etiqueta="__('curvas.campos.fecha')" nombre="fecha" obligatorio>
                <x-ui.input nombre="fecha" type="date" wire:model="fecha" :max="now()->toDateString()" />
            </x-ui.campo>
            <x-ui.campo :etiqueta="__('curvas.campos.observaciones')" nombre="observaciones">
                <x-ui.textarea nombre="observaciones" wire:model="observaciones" :filas="1" />
            </x-ui.campo>
        </div>

        {{-- Muestras: una fila por corte. Tres columnas cortas y la X: entran en mobile. --}}
        <div data-muestras>
            <div class="flex items-center justify-between gap-3 mb-1">
                <h3 class="text-sm font-medium text-gray-700">{{ __('curvas.campos.muestras') }} <span class="text-red-600" aria-hidden="true">*</span></h3>
                <x-ui.boton variante="enlace" icono="fa-plus" wire:click="agregarMuestra" data-agregar-muestra>{{ __('curvas.agregar_muestra') }}</x-ui.boton>
            </div>
            <p class="mb-2 text-xs text-gray-500">{{ __('curvas.ayuda_muestras') }}</p>
            @error('muestras') <p class="mb-2 text-sm text-red-600" data-error-muestras>{{ $message }}</p> @enderror

            <table class="w-full text-sm">
                <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="pb-1 pr-2 w-8 text-right">#</th>
                        <th class="pb-1 px-1">{{ __('curvas.campos.altura') }}</th>
                        <th class="pb-1 px-1">{{ __('curvas.campos.peso') }}</th>
                        <th class="pb-1 px-1 text-right">{{ __('curvas.campos.kg') }}</th>
                        <th class="pb-1 w-8"><span class="sr-only">{{ __('curvas.quitar_muestra') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($muestras as $i => $fila)
                        @php
                            $peso = str_replace(',', '.', trim($fila['peso'] ?? ''));
                            $kg = is_numeric($peso) && $peso > 0 ? RectaBiomasa::kgMsHaDeMuestra((float) $peso, $area) : null;
                        @endphp
                        <tr wire:key="muestra-{{ $i }}-{{ count($muestras) }}" class="align-top">
                            <td class="py-1 pr-2 text-right text-xs text-gray-400 tabular-nums leading-9">{{ $i + 1 }}</td>
                            <td class="py-1 px-1">
                                <x-ui.input :nombre="'muestras.'.$i.'.altura'" wire:model.blur="muestras.{{ $i }}.altura" inputmode="decimal" autocomplete="off" class="tabular-nums" />
                                @error('muestras.'.$i.'.altura') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                            <td class="py-1 px-1">
                                <x-ui.input :nombre="'muestras.'.$i.'.peso'" wire:model.blur="muestras.{{ $i }}.peso" inputmode="decimal" autocomplete="off" class="tabular-nums" />
                                @error('muestras.'.$i.'.peso') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                            <td class="py-1 px-1 text-right tabular-nums text-gray-500 leading-9">{{ $kg === null ? '—' : Formato::numero($kg) }}</td>
                            <td class="py-1 text-right">
                                <button type="button" wire:click="quitarMuestra({{ $i }})" class="h-9 w-8 text-gray-400 hover:text-red-600" title="{{ __('curvas.quitar_muestra') }}" aria-label="{{ __('curvas.quitar_muestra') }}">
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Vista previa: la recta y el gráfico con las muestras completas --}}
        <div class="rounded-md border border-gray-200 p-3" data-vista-previa>
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 mb-2">
                <h3 class="text-sm font-medium text-gray-700">{{ __('curvas.vista_previa') }}</h3>
                <p class="text-sm tabular-nums">
                    <span class="font-semibold text-gray-800" data-recta-previa>{{ $recta?->texto() ?? __('curvas.sin_recta') }}</span>
                    @if($recta)
                        <span class="text-gray-500" title="{{ __('curvas.r2_ayuda') }}">· {{ __('curvas.columnas.r2') }} {{ $recta->textoR2() }}</span>
                    @endif
                </p>
            </div>
            @if($puntos === [])
                <p class="py-6 text-center text-sm text-gray-400" data-sin-muestras>{{ __('curvas.sin_muestras') }}</p>
            @else
                <x-ui.grafico-dispersion :puntos="$puntos" :recta="$recta ? ['a' => $recta->a, 'b' => $recta->b] : null"
                                         :eje-x="__('curvas.detalle.eje_x')" :eje-y="__('curvas.detalle.eje_y')"
                                         :nombre-puntos="__('curvas.campos.muestras')" />
            @endif
            <p class="mt-2 text-xs text-gray-500">{{ __('curvas.detalle.area_disco', [
                'valor' => rtrim(rtrim(Formato::numero($area, 4), '0'), ','),
                'kg' => Formato::numero(RectaBiomasa::kgMsHaDeMuestra(1, $area)),
            ]) }}</p>
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
