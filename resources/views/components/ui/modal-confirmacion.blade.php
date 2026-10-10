{{--
    <x-ui.modal-confirmacion> — estandares-ux.md §1.

    El único diálogo de confirmación del sistema: ni confirm() ni wire:confirm.
    Se monta sobre <x-ui.modal>, así que hereda scroll-lock, Escape y backdrop.

    Props:
      confirmar  Acción Livewire del botón de confirmar (obligatoria).
      cancelar   Acción Livewire de cancelar / X / backdrop / Escape (obligatoria).
      tipo       peligro (por omisión, rojo) | advertencia (ámbar) | info (azul).
      titulo     Por omisión según el tipo (lang/es/ui.php).
      boton      Texto del botón de confirmar; por omisión según el tipo.
      z          50 | 60 | 70, igual que <x-ui.modal>.

    Slot: el texto de la pregunta, en trato de usted.
--}}
@props([
    'confirmar',
    'cancelar',
    'tipo' => 'peligro',
    'titulo' => null,
    'boton' => null,
    'z' => 50,
])

@php
    // Colores semánticos (§6): estos no cambian con el acento del tema.
    $estilos = [
        'peligro' => [
            'icono' => 'fa-triangle-exclamation',
            'iconoClase' => 'bg-red-100 text-red-600',
            'boton' => 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500',
        ],
        'advertencia' => [
            'icono' => 'fa-circle-exclamation',
            'iconoClase' => 'bg-amber-100 text-amber-600',
            'boton' => 'bg-amber-600 hover:bg-amber-700 text-white focus:ring-amber-500',
        ],
        'info' => [
            'icono' => 'fa-circle-info',
            'iconoClase' => 'bg-blue-100 text-blue-600',
            'boton' => 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500',
        ],
    ];
    $tipo = array_key_exists($tipo, $estilos) ? $tipo : 'peligro';
    $estilo = $estilos[$tipo];

    $titulo ??= __("ui.confirmacion.$tipo.titulo");
    $boton ??= __("ui.confirmacion.$tipo.boton");
@endphp

<x-ui.modal :titulo="$titulo" :icono="$estilo['icono']" :cerrar="$cancelar" ancho="md" :z="$z" {{ $attributes }}>
    <div class="flex items-start gap-4">
        <div class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full {{ $estilo['iconoClase'] }}">
            <i class="fa-solid {{ $estilo['icono'] }}" aria-hidden="true"></i>
        </div>
        <p class="text-sm text-gray-700 pt-2">{{ $slot }}</p>
    </div>

    <x-slot:footer>
        <button type="button" wire:click="{{ $cancelar }}"
                class="inline-flex justify-center items-center px-4 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400">
            {{ __('ui.confirmacion.cancelar') }}
        </button>
        {{-- wire:loading SIEMPRE acotado con wire:target (§7, trampa 1) --}}
        <button type="button" wire:click="{{ $confirmar }}"
                wire:loading.attr="disabled" wire:target="{{ $confirmar }}"
                class="inline-flex justify-center items-center gap-2 px-4 py-2 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed {{ $estilo['boton'] }}">
            <span wire:loading.remove wire:target="{{ $confirmar }}">{{ $boton }}</span>
            <span wire:loading wire:target="{{ $confirmar }}" class="inline-flex items-center gap-2">
                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                {{ __('ui.confirmacion.procesando') }}
            </span>
        </button>
    </x-slot:footer>
</x-ui.modal>
