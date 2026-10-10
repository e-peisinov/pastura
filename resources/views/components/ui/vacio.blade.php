{{--
    <x-ui.vacio> — estado vacío de un listado (sin registros o sin resultados
    para el filtro). Va adentro de la tabla (en un <td colspan>) y de las cards.

    Props:
      icono  Nombre del icono ("fa-tags"); se antepone fa-solid.
--}}
@props([
    'icono' => 'fa-inbox',
])

@php
    use Illuminate\Support\Str;

    if (! Str::startsWith($icono, ['fa-solid', 'fa-regular', 'fa-brands'])) {
        $icono = 'fa-solid '.$icono;
    }
@endphp

<div {{ $attributes->class(['flex flex-col items-center justify-center gap-2 py-10 text-center text-sm text-gray-500']) }} data-vacio>
    <i class="{{ $icono }} text-3xl text-gray-300" aria-hidden="true"></i>
    <p>{{ $slot }}</p>
</div>
