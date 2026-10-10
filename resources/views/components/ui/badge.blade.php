{{--
    <x-ui.badge> — etiqueta chica de estado (activo/inactivo, estados de la
    orden, semáforo). Solo los colores semánticos de §6 y el gris neutro.

    Props:
      color  gris (por omisión) | verde | rojo | ambar | azul. También acepta
             los nombres de Tailwind que devuelven los enums en color():
             gray | emerald | red | amber | blue.
--}}
@props([
    'color' => 'gris',
])

@php
    // Clases literales (§7, trampa 7).
    $clases = match ($color) {
        'verde', 'emerald' => 'bg-emerald-100 text-emerald-800',
        'rojo', 'red' => 'bg-red-100 text-red-800',
        'ambar', 'amber' => 'bg-amber-100 text-amber-800',
        'azul', 'blue' => 'bg-blue-100 text-blue-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', $clases]) }}>
    {{ $slot }}
</span>
