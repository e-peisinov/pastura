{{--
    <x-ui.checkbox> — casilla con su rótulo al lado.

    Props:
      nombre    Nombre de la propiedad Livewire (id).
      etiqueta  Texto al lado de la casilla.
      ayuda     Texto chico debajo (opcional).
--}}
@props([
    'nombre' => null,
    'etiqueta' => '',
    'ayuda' => null,
])

@php
    use App\Support\Tema;
@endphp

<label class="flex items-start gap-3 cursor-pointer">
    <input type="checkbox"
           @if($nombre) id="campo-{{ $nombre }}" @endif
           {{ $attributes->class(['mt-0.5 h-4 w-4 rounded border-gray-300 shadow-sm', Tema::acentoTexto(), Tema::anilloFoco()]) }}>
    <span class="text-sm">
        <span class="font-medium text-gray-700">{{ $etiqueta }}</span>
        @if($ayuda)
            <span class="block text-xs text-gray-500">{{ $ayuda }}</span>
        @endif
    </span>
</label>
