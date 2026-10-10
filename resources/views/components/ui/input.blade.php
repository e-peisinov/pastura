{{--
    <x-ui.input> — input de texto / número / fecha con el anillo de foco del
    tema. Se usa adentro de <x-ui.campo>. El `id` sale de `nombre` para que el
    rótulo del campo lo enfoque.

    Props:
      nombre  Nombre de la propiedad Livewire (id y borde rojo si tiene error).
      type    text por omisión.
--}}
@props([
    'nombre' => null,
    'type' => 'text',
])

@php
    use App\Support\Tema;

    // Fuera de una request (Blade::render en tests) $errors no está compartido.
    $conError = $nombre !== null && isset($errors) && $errors->has($nombre);
@endphp

<input type="{{ $type }}"
       @if($nombre) id="campo-{{ $nombre }}" @endif
       {{ $attributes->class([
           'block w-full rounded-md shadow-sm text-sm text-gray-800 focus:outline-none focus:ring-1 disabled:bg-gray-50 disabled:text-gray-400',
           Tema::anilloFoco(),
           'border-gray-300' => ! $conError,
           'border-red-500' => $conError,
       ]) }}>
