{{--
    <x-ui.paginacion> — estandares-ux.md §5.

    Paginación manual (para listados que no usan WithPagination; con
    WithPagination queda `links()`). Colores del tema vía Tema.

    Props:
      current-page, total-pages, offset, per-page, total-records  Estado del listado.
      accion   Acción Livewire que recibe el número de página. Por omisión "gotoPage".

    Mobile: Anterior / "Página X de Y" / Siguiente. Escritorio: además los
    números, con "…" cuando hay muchas páginas.
--}}
@props([
    'currentPage' => 1,
    'totalPages' => 1,
    'offset' => 0,
    'perPage' => 10,
    'totalRecords' => 0,
    'accion' => 'gotoPage',
])

@php
    use App\Support\Tema;

    $currentPage = max(1, (int) $currentPage);
    $totalPages = max(1, (int) $totalPages);
    $desde = $totalRecords > 0 ? $offset + 1 : 0;
    $hasta = min($offset + $perPage, $totalRecords);

    // Ventana de números: primera, última, actual ±1, con "…" en los huecos.
    $paginas = [];
    if ($totalPages <= 7) {
        $paginas = range(1, $totalPages);
    } else {
        $candidatas = array_unique(array_filter(
            [1, 2, $currentPage - 1, $currentPage, $currentPage + 1, $totalPages - 1, $totalPages],
            fn ($p) => $p >= 1 && $p <= $totalPages,
        ));
        sort($candidatas);
        $anterior = 0;
        foreach ($candidatas as $p) {
            if ($p - $anterior > 1) {
                $paginas[] = '…';
            }
            $paginas[] = $p;
            $anterior = $p;
        }
    }

    $claseBoton = 'inline-flex items-center justify-center min-w-[2.25rem] h-9 px-3 rounded-md border text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-1 '.Tema::anilloFoco();
    $claseNormal = 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50';
    $claseActiva = 'border-transparent '.Tema::acentoFondo();
    $claseDeshabilitado = 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed';
@endphp

<nav {{ $attributes->class(['flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-t border-gray-200 bg-white text-sm text-gray-500']) }}
     aria-label="{{ __('ui.paginacion.etiqueta') }}"
     data-paginacion>

    <p class="text-center sm:text-left">
        @if($totalRecords === 0)
            {{ __('ui.paginacion.sin_registros') }}
        @elseif($totalRecords === 1)
            {{ __('ui.paginacion.mostrando_uno') }}
        @else
            {{ __('ui.paginacion.mostrando', ['desde' => $desde, 'hasta' => $hasta, 'total' => $totalRecords]) }}
        @endif
    </p>

    @if($totalPages > 1)
        <div class="flex items-center justify-center gap-1">
            {{-- Anterior --}}
            @if($currentPage > 1)
                <button type="button" wire:click="{{ $accion }}({{ $currentPage - 1 }})"
                        class="{{ $claseBoton }} {{ $claseNormal }}" aria-label="{{ __('ui.paginacion.anterior') }}">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </button>
            @else
                <span class="{{ $claseBoton }} {{ $claseDeshabilitado }}" aria-disabled="true">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </span>
            @endif

            {{-- Mobile: solo "Página X de Y" --}}
            <span class="md:hidden px-3 text-gray-700">
                {{ __('ui.paginacion.pagina_de', ['actual' => $currentPage, 'total' => $totalPages]) }}
            </span>

            {{-- Escritorio: los números --}}
            <div class="hidden md:flex items-center gap-1">
                @foreach($paginas as $pagina)
                    @if($pagina === '…')
                        <span class="px-2 text-gray-400">…</span>
                    @elseif($pagina === $currentPage)
                        <span class="{{ $claseBoton }} {{ $claseActiva }}" aria-current="page">{{ $pagina }}</span>
                    @else
                        <button type="button" wire:click="{{ $accion }}({{ $pagina }})"
                                class="{{ $claseBoton }} {{ $claseNormal }}"
                                aria-label="{{ __('ui.paginacion.ir_a', ['pagina' => $pagina]) }}">{{ $pagina }}</button>
                    @endif
                @endforeach
            </div>

            {{-- Siguiente --}}
            @if($currentPage < $totalPages)
                <button type="button" wire:click="{{ $accion }}({{ $currentPage + 1 }})"
                        class="{{ $claseBoton }} {{ $claseNormal }}" aria-label="{{ __('ui.paginacion.siguiente') }}">
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
            @else
                <span class="{{ $claseBoton }} {{ $claseDeshabilitado }}" aria-disabled="true">
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </span>
            @endif
        </div>
    @endif
</nav>
