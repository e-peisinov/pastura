{{--
    <x-ui.campo> — un campo de formulario: rótulo, control y error inline.

    Los errores de validación van inline con @error (regla 3 de CLAUDE.md):
    este componente los muestra debajo del control.

    Props:
      etiqueta     Texto del rótulo.
      nombre       Nombre de la propiedad Livewire, para @error y el `for`.
      obligatorio  Marca el rótulo con un asterisco.
      ayuda        Texto chico debajo del control (opcional).
    Slot: el control (<x-ui.input>, <x-ui.combobox>, <x-ui.textarea>...).
--}}
@props([
    'etiqueta' => null,
    'nombre' => null,
    'obligatorio' => false,
    'ayuda' => null,
])

<div {{ $attributes->class(['w-full']) }}>
    @if($etiqueta)
        <label @if($nombre) for="campo-{{ $nombre }}" @endif class="block text-sm font-medium text-gray-700 mb-1">
            {{ $etiqueta }}
            @if($obligatorio)<span class="text-red-600" aria-hidden="true">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if($nombre && isset($errors))
        @error($nombre)
            <p class="mt-1 text-sm text-red-600" data-error="{{ $nombre }}">{{ $message }}</p>
        @enderror
    @endif

    @if($ayuda)
        <p class="mt-1 text-xs text-gray-500">{{ $ayuda }}</p>
    @endif
</div>
