{{--
    <x-ui.textarea> — área de texto, mismo criterio que <x-ui.input>.
--}}
@props([
    'nombre' => null,
    'filas' => 3,
])

@php
    use App\Support\Tema;

    $conError = $nombre !== null && isset($errors) && $errors->has($nombre);
@endphp

<textarea rows="{{ $filas }}"
          @if($nombre) id="campo-{{ $nombre }}" @endif
          {{ $attributes->class([
              'block w-full rounded-md shadow-sm text-sm text-gray-800 focus:outline-none focus:ring-1 disabled:bg-gray-50 disabled:text-gray-400',
              Tema::anilloFoco(),
              'border-gray-300' => ! $conError,
              'border-red-500' => $conError,
          ]) }}>{{ $slot }}</textarea>
