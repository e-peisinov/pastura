{{-- Ficha de una curva: la recta, el gráfico y las muestras. Parte de livewire.curvas.curvas. --}}
@php
    use App\Support\Biomasa\RectaBiomasa;
    use App\Support\Formato;
@endphp
@if($detalle)
    @php
        $recta = $detalle->recta($area);
        $muestras = $detalle->muestras->map(fn ($m) => [
            'altura' => Formato::numero($m->altura_cm, 1),
            'peso' => Formato::numero($m->peso_seco_g, 2),
            'kg' => $m->kgMsHa($area),
            'x' => $m->altura_cm,
        ]);
    @endphp

    <x-ui.modal :titulo="$detalle->potrero->nombreCompleto()" icono="fa-chart-line" cerrar="cerrarDetalle" ancho="3xl" data-detalle-curva>
        <x-slot:encabezado>
            <x-ui.badge :color="$detalle->activo ? 'verde' : 'gris'">{{ $detalle->activo ? __('curvas.vigente') : __('curvas.reemplazada') }}</x-ui.badge>
        </x-slot:encabezado>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('curvas.columnas.estado') }}</dt>
                <dd class="text-gray-800">{{ $detalle->estadoFenologico->nombre }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('curvas.columnas.fecha') }}</dt>
                <dd class="text-gray-800 tabular-nums">{{ Formato::fecha($detalle->fecha) }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('curvas.columnas.recta') }}</dt>
                <dd class="text-gray-800 tabular-nums font-semibold" data-recta-texto>{{ $recta?->texto() ?? __('curvas.sin_recta') }}</dd>
                <dd class="text-xs text-gray-500" title="{{ __('curvas.r2_ayuda') }}">{{ __('curvas.columnas.r2') }} {{ $recta?->textoR2() ?? '—' }} · {{ trans_choice('curvas.muestras_count', $muestras->count()) }}</dd>
            </div>
            @if($detalle->observaciones)
                <div class="col-span-2 sm:col-span-4">
                    <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('curvas.detalle.observaciones') }}</dt>
                    <dd class="text-gray-800 whitespace-pre-line">{{ $detalle->observaciones }}</dd>
                </div>
            @endif
        </dl>

        <div>
            <h3 class="text-sm font-semibold text-gray-800 mb-2">{{ __('curvas.detalle.grafico') }}</h3>
            <x-ui.grafico-dispersion :puntos="$muestras->map(fn ($m) => [$m['x'], $m['kg']])->all()"
                                     :recta="$recta ? ['a' => $recta->a, 'b' => $recta->b] : null"
                                     :eje-x="__('curvas.detalle.eje_x')" :eje-y="__('curvas.detalle.eje_y')"
                                     :nombre-puntos="__('curvas.campos.muestras')" />
            <p class="mt-2 text-xs text-gray-500">{{ __('curvas.detalle.area_disco', [
                'valor' => rtrim(rtrim(Formato::numero($area, 4), '0'), ','),
                'kg' => Formato::numero(RectaBiomasa::kgMsHaDeMuestra(1, $area)),
            ]) }}</p>
        </div>

        {{-- Las muestras: la tabla del gráfico (§11, regla 6). Tres columnas cortas: entran en mobile. --}}
        <div>
            <h3 class="text-sm font-semibold text-gray-800 mb-2">{{ __('curvas.campos.muestras') }}</h3>
            <table class="w-full text-sm" data-muestras>
                <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr class="border-b border-gray-200">
                        <th class="py-2 pr-3 text-right">{{ __('curvas.campos.altura') }}</th>
                        <th class="py-2 px-3 text-right">{{ __('curvas.campos.peso') }}</th>
                        <th class="py-2 pl-3 text-right">{{ __('curvas.campos.kg') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($muestras as $muestra)
                        <tr>
                            <td class="py-1.5 pr-3 text-right tabular-nums text-gray-800">{{ $muestra['altura'] }}</td>
                            <td class="py-1.5 px-3 text-right tabular-nums text-gray-800">{{ $muestra['peso'] }}</td>
                            <td class="py-1.5 pl-3 text-right tabular-nums text-gray-500">{{ Formato::numero($muestra['kg']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            @if($puedeEditar)
                <x-ui.boton variante="secundario" icono="fa-pen" wire:click="editar({{ $detalle->id }})">{{ __('ui.acciones.editar') }}</x-ui.boton>
            @endif
            <x-ui.boton wire:click="cerrarDetalle">{{ __('ui.acciones.cerrar') }}</x-ui.boton>
        </x-slot:footer>
    </x-ui.modal>
@endif
