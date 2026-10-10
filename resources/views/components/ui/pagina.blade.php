{{--
    <x-ui.pagina> — encabezado y contenedor de una pantalla del sistema.

    Los componentes Livewire de página completa no pueden llenar el slot
    `header` del layout, así que el título va acá. Todas las pantallas lo usan
    para verse iguales.

    Props:
      titulo   Título de la pantalla.
      icono    Nombre del icono ("fa-tags"); se antepone fa-solid.
    Slots: default (contenido) y `acciones` (botones a la derecha del título,
    por ejemplo "Nueva marca").
--}}
@props([
    'titulo' => '',
    'icono' => null,
])

@php
    use App\Support\Tema;
    use Illuminate\Support\Str;

    if ($icono !== null && ! Str::startsWith($icono, ['fa-solid', 'fa-regular', 'fa-brands'])) {
        $icono = 'fa-solid '.$icono;
    }
@endphp

<div {{ $attributes->class(['flex flex-col min-h-full']) }}>
    {{-- En escritorio mide lo mismo que la cabecera del sidebar (h-16), así la línea de abajo es una sola. --}}
    <header class="flex bg-white border-b border-gray-200 md:min-h-16">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-4 py-4 sm:px-6 lg:px-8 md:py-2">
            <h1 class="flex items-center gap-3 text-xl font-semibold text-gray-800 leading-tight">
                @if($icono)
                    <i class="{{ $icono }} {{ Tema::acentoTexto() }}" aria-hidden="true"></i>
                @endif
                <span>{{ $titulo }}</span>
            </h1>
            @isset($acciones)
                <div class="flex flex-wrap items-center gap-2">
                    {{ $acciones }}
                </div>
            @endisset
        </div>
    </header>

    <div class="flex-1 p-4 sm:p-6 lg:p-8">
        {{ $slot }}
    </div>
</div>
