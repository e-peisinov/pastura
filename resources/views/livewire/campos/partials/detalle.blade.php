{{-- Ficha de un campo: datos, mapa de sus potreros y la lista con su última recorrida. Parte de livewire.campos.campos. --}}
@php
    use App\Support\Formato;
@endphp
@if($detalle)
    @php
        $superficieTotal = $detalle->superficieHa();
        $contornos = $detalle->potreros
            ->filter(fn ($p) => $p->contorno !== null && $p->activo)
            ->map(fn ($p) => ['nombre' => $p->nombre, 'contorno' => $p->contorno])
            ->values();
        // Cuando cambia un contorno, el mapa se vuelve a montar (wire:ignore no se redibuja).
        $claveMapa = md5($contornos->toJson());
    @endphp

    <x-ui.modal :titulo="$detalle->nombre" icono="fa-map-location-dot" cerrar="cerrarDetalle" ancho="4xl" data-detalle-campo>
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
            @if($detalle->localidad)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('campos.campos.localidad') }}</dt>
                    <dd class="text-gray-800">{{ $detalle->localidad }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('campos.detalle.superficie_total') }}</dt>
                <dd class="text-gray-800 tabular-nums">{{ $superficieTotal === null ? __('campos.sin_superficie') : __('campos.hectareas', ['valor' => Formato::numero($superficieTotal, 1)]) }}</dd>
            </div>
            @if($detalle->observaciones)
                <div class="sm:col-span-3">
                    <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('campos.campos.observaciones') }}</dt>
                    <dd class="text-gray-800 whitespace-pre-line">{{ $detalle->observaciones }}</dd>
                </div>
            @endif
        </dl>

        {{-- Mapa de los potreros --}}
        @if($contornos->isNotEmpty())
            <div wire:key="mapa-campo-{{ $detalle->id }}-{{ $claveMapa }}" wire:ignore
                 x-data="mapaVista({ potreros: @js($contornos) })"
                 class="h-64 sm:h-80 rounded-md overflow-hidden border border-gray-200" data-mapa-campo>
                <div x-ref="mapa" class="h-full w-full"></div>
            </div>
        @else
            <p class="text-sm text-gray-500" data-sin-contornos>{{ __('campos.detalle.sin_contornos') }}</p>
        @endif

        {{-- Potreros --}}
        <div>
            <div class="flex items-center justify-between gap-3 mb-2">
                <h3 class="text-sm font-semibold text-gray-800">{{ __('campos.detalle.potreros') }}</h3>
                @if($puedeEditar)
                    <x-ui.boton variante="enlace" icono="fa-plus" wire:click="agregarPotrero" data-agregar-potrero>{{ __('campos.detalle.agregar_potrero') }}</x-ui.boton>
                @endif
            </div>
            @forelse($detalle->potreros as $potrero)
                @php
                    $ha = $potrero->superficieHa();
                    $ultima = $ultimas->get($potrero->id);
                @endphp
                <div wire:key="detalle-potrero-{{ $potrero->id }}" data-potrero="{{ $potrero->id }}"
                     class="flex flex-col gap-2 py-2 border-t border-gray-200 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-800">
                            {{ $potrero->nombre }}
                            @if(! $potrero->activo)
                                <x-ui.badge color="gris" class="ml-1">{{ __('campos.detalle.inactivo') }}</x-ui.badge>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ $potrero->tipoPastura?->nombre ?? '—' }}
                            · {{ $ha === null ? __('campos.sin_superficie') : __('campos.hectareas', ['valor' => Formato::numero($ha, 1)]) }}
                        </p>
                    </div>
                    <div class="flex items-center justify-between gap-3 sm:justify-end">
                        <span class="text-xs text-gray-500 tabular-nums">
                            {{ $ultima
                                ? __('campos.detalle.ultima', ['fecha' => Formato::fecha($ultima->iniciada_en), 'altura' => Formato::numero($ultima->altura_media ?? 0, 1)])
                                : __('campos.detalle.sin_recorridas') }}
                        </span>
                        <div class="flex gap-1">
                            {{-- Las curvas de biomasa del potrero (P23), con Ver en Curvas --}}
                            @if($verCurvas)
                                <x-ui.accion icono="fa-chart-line" :etiqueta="__('campos.detalle.curvas')" :href="route('curvas', ['potrero' => $potrero->id])" data-curvas-potrero />
                            @endif
                            @if($puedeEditar)
                                <x-ui.accion icono="fa-pen" :etiqueta="__('ui.acciones.editar')" wire:click="editarPotrero({{ $potrero->id }})" />
                                @if($potrero->activo)
                                    <x-ui.accion icono="fa-trash" color="peligro" :etiqueta="__('ui.acciones.eliminar')" wire:click="confirmarBajaPotrero({{ $potrero->id }})" />
                                @else
                                    <x-ui.accion icono="fa-rotate-left" :etiqueta="__('ui.acciones.reactivar')" wire:click="reactivarPotrero({{ $potrero->id }})" />
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('campos.detalle.sin_potreros') }}</p>
            @endforelse
        </div>

        <x-slot:footer>
            @if($puedeEditar)
                <x-ui.boton variante="secundario" icono="fa-pen" wire:click="editar({{ $detalle->id }})">{{ __('ui.acciones.editar') }}</x-ui.boton>
            @endif
            <x-ui.boton wire:click="cerrarDetalle">{{ __('ui.acciones.cerrar') }}</x-ui.boton>
        </x-slot:footer>
    </x-ui.modal>
@endif
