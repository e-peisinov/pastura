{{--
    <x-ui.indicador> — un contador del tablero (D45): rótulo chico y valor
    grande. Si lleva href es un enlace a la pantalla filtrada (lleva
    wire:navigate solo, D31); si lleva wire:click es un botón que filtra
    dentro del propio bloque (D76) y se pinta como activo cuando su filtro
    está puesto; si no lleva ninguno, es solo lectura.

    Props:
      rotulo  Qué se cuenta ("En taller").
      valor   El número o el importe, ya formateado.
      color   gris (por omisión) | verde | rojo | ambar | azul, o los nombres
              de Tailwind que devuelven los enums en color() (gray | emerald |
              red | amber | blue). Pinta el valor; el rótulo queda gris.
      href    Enlace opcional.
      activo  Solo para el botón: el filtro de este contador está puesto.
--}}
@props([
    'rotulo' => '',
    'valor' => '',
    'color' => 'gris',
    'href' => null,
    'activo' => false,
])

@php
    // Clases literales (§7, trampa 7).
    $claseValor = match ($color) {
        'verde', 'emerald' => 'text-emerald-700',
        'rojo', 'red' => 'text-red-700',
        'ambar', 'amber' => 'text-amber-700',
        'azul', 'blue' => 'text-blue-700',
        default => 'text-gray-800',
    };

    $base = 'flex flex-col gap-0.5 rounded-lg border px-3 py-2 min-w-0';
    $reposo = 'border-gray-200 bg-gray-50';
    $interactivo = 'hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400 transition-colors';
    $esBoton = ! $href && $attributes->has('wire:click');
@endphp

@if($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->class([$base, $reposo, $interactivo]) }} data-indicador>
        <span class="text-xs text-gray-500 truncate">{{ $rotulo }}</span>
        <span class="text-2xl font-semibold tabular-nums leading-tight {{ $claseValor }}">{{ $valor }}</span>
    </a>
@elseif($esBoton)
    <button type="button" {{ $attributes->class([$base, $interactivo, 'text-left', $activo ? 'border-gray-700 bg-white ring-1 ring-gray-700' : $reposo]) }}
            aria-pressed="{{ $activo ? 'true' : 'false' }}" data-indicador>
        <span class="text-xs truncate {{ $activo ? 'text-gray-800 font-medium' : 'text-gray-500' }}">{{ $rotulo }}</span>
        <span class="text-2xl font-semibold tabular-nums leading-tight {{ $claseValor }}">{{ $valor }}</span>
    </button>
@else
    <div {{ $attributes->class([$base, $reposo]) }} data-indicador>
        <span class="text-xs text-gray-500 truncate">{{ $rotulo }}</span>
        <span class="text-2xl font-semibold tabular-nums leading-tight {{ $claseValor }}">{{ $valor }}</span>
    </div>
@endif
