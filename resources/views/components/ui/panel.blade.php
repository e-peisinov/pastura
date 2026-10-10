{{--
    <x-ui.panel> — un bloque del tablero (D45): título con icono, un resumen
    a la derecha (contadores, un total) y una lista corta abajo con enlace a
    la pantalla del módulo. Cada bloque muestra lo urgente y manda a la
    pantalla que lo resuelve; el tablero no edita nada.

    Props:
      titulo   Título del bloque.
      icono    Nombre del icono ("fa-bell"); se antepone fa-solid.
      href     Enlace a la pantalla del módulo ("Ver todo"). Lleva
               wire:navigate solo (D31). Si no se pasa, no hay pie.
      enlace   Texto del enlace del pie; por omisión "Ver todo".
    Slots: default (la lista, normalmente un <ul> con divide-y, o un
    <x-ui.vacio>) y `resumen` (lo que va a la derecha del título).
--}}
@props([
    'titulo' => '',
    'icono' => null,
    'href' => null,
    'enlace' => null,
])

@php
    use App\Support\Tema;
    use Illuminate\Support\Str;

    if ($icono !== null && ! Str::startsWith($icono, ['fa-solid', 'fa-regular', 'fa-brands'])) {
        $icono = 'fa-solid '.$icono;
    }
@endphp

<section {{ $attributes->class(['flex flex-col bg-white border border-gray-200 rounded-lg']) }} data-panel>
    <header class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200">
        <h2 class="flex items-center gap-2 font-semibold text-gray-800">
            @if($icono)
                <i class="{{ $icono }} {{ Tema::acentoTexto() }}" aria-hidden="true"></i>
            @endif
            <span>{{ $titulo }}</span>
        </h2>
        @isset($resumen)
            <div class="flex flex-wrap items-center gap-2 text-sm">
                {{ $resumen }}
            </div>
        @endisset
    </header>

    <div class="flex-1">
        {{ $slot }}
    </div>

    @if($href)
        <footer class="px-4 py-2 border-t border-gray-100 text-right">
            <a href="{{ $href }}" wire:navigate class="text-sm font-medium {{ Tema::enlace() }} hover:underline">
                {{ $enlace ?? __('ui.panel.ver_todo') }}
                <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
            </a>
        </footer>
    @endif
</section>
