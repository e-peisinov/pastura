{{--
    <x-ui.boton> — los botones del sistema, con el acento del tema (§6).

    Props:
      variante  primario (acento del tema) | secundario (blanco con borde) |
                peligro (rojo) | enlace (solo texto). Por omisión primario.
      icono     Nombre del icono ("fa-plus"); se antepone fa-solid.
      href      Si se pasa, renderiza un <a> en vez de un <button>. Los enlaces
                internos llevan wire:navigate solos (D31): la navegación entre
                pantallas no recarga la página. `navegar="false"` lo desactiva
                (descargas, enlaces externos, target="_blank").
      type      Por omisión "button". Poner "submit" en el footer de los modales.

    El wire:loading de un botón siempre va acotado con wire:target (§7).
--}}
@props([
    'variante' => 'primario',
    'icono' => null,
    'href' => null,
    'type' => 'button',
    'navegar' => true,
])

@php
    use App\Support\Tema;
    use Illuminate\Support\Str;

    // Clases literales por variante (§7, trampa 7). Solo el primario toma el acento.
    $clases = match ($variante) {
        'secundario' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-gray-400',
        'peligro' => 'border border-transparent bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        'enlace' => 'border border-transparent bg-transparent '.Tema::enlace().' hover:underline focus:ring-gray-400 px-1',
        default => 'border border-transparent '.Tema::botonPrimario().' focus:ring-gray-500',
    };

    $base = 'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed transition-colors';

    if ($icono !== null && ! Str::startsWith($icono, ['fa-solid', 'fa-regular', 'fa-brands'])) {
        $icono = 'fa-solid '.$icono;
    }
@endphp

@php
    // wire:navigate solo en enlaces internos: uno externo o con target propio
    // tiene que salir de la aplicación (D31).
    $navegaConLivewire = $navegar
        && ! $attributes->has('target')
        && Str::startsWith($href ?? '', ['/', url('/')]);
@endphp

@if($href)
    <a href="{{ $href }}" @if($navegaConLivewire) wire:navigate @endif {{ $attributes->class([$base, $clases]) }}>
        @if($icono)<i class="{{ $icono }}" aria-hidden="true"></i>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$base, $clases]) }}>
        @if($icono)<i class="{{ $icono }}" aria-hidden="true"></i>@endif
        {{ $slot }}
    </button>
@endif
