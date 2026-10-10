{{--
    <x-ui.accion> — botón de acción de una fila (tabla o card): SOLO EL ICONO.

    Los rótulos ("Ver", "Modificar", "Quitar") no se escriben al lado del
    icono: la columna de acciones se lee de un vistazo y en mobile no compite
    con el contenido de la card (estandares-ux.md §5, D33). El texto sigue
    existiendo para quien no ve el icono: va en `title` (tooltip del
    escritorio) y en `aria-label` (lector de pantalla), y es obligatorio.

    Props:
      icono     Nombre del icono ("fa-trash"); se antepone fa-solid.
      etiqueta  Qué hace la acción. Obligatoria: es el title y el aria-label.
      color     neutro (por omisión) | peligro (rojo).
      href      Si se pasa, renderiza un <a>. Los internos llevan wire:navigate
                solos (D31); los externos, las descargas y los que traen
                target="_blank" no. `navegar="false"` lo desactiva a mano.

    Ejemplo:
      <x-ui.accion icono="fa-trash" color="peligro" :etiqueta="__('ui.acciones.eliminar')"
                   wire:click="confirmarBaja({{ $fila->id }})" />
--}}
@props([
    'icono',
    'etiqueta',
    'color' => 'neutro',
    'href' => null,
    'navegar' => true,
])

@php
    use Illuminate\Support\Str;

    // Clases literales (§7, trampa 7).
    $clases = $color === 'peligro'
        ? 'text-red-600 hover:text-red-700 hover:bg-red-50 focus:ring-red-500'
        : 'text-gray-500 hover:text-gray-800 hover:bg-gray-100 focus:ring-gray-400';

    // Área de toque de 36 px: en mobile el dedo necesita más que el icono (§5).
    $base = 'inline-flex items-center justify-center w-9 h-9 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-60 disabled:cursor-not-allowed';

    if (! Str::startsWith($icono, ['fa-solid', 'fa-regular', 'fa-brands'])) {
        $icono = 'fa-solid '.$icono;
    }
@endphp

@php
    // wire:navigate solo en enlaces internos: uno externo, una descarga o un
    // target propio salen de la aplicación (D31, §9). Un PDF con wire:navigate
    // se pega como texto en el body.
    $navegaConLivewire = $navegar
        && ! $attributes->has('target')
        && Str::startsWith($href ?? '', ['/', url('/')]);
@endphp

@if($href)
    <a href="{{ $href }}"@if($navegaConLivewire) wire:navigate @endif title="{{ $etiqueta }}" aria-label="{{ $etiqueta }}"
       {{ $attributes->class([$base, $clases]) }}>
        <i class="{{ $icono }}" aria-hidden="true"></i>
    </a>
@else
    <button type="button" title="{{ $etiqueta }}" aria-label="{{ $etiqueta }}"
            {{ $attributes->class([$base, $clases]) }}>
        <i class="{{ $icono }}" aria-hidden="true"></i>
    </button>
@endif
