{{--
    <x-ui.solapas> — solapas para partir una pantalla en vistas hermanas
    (marcas de vehículo / de neumático, datos / historial).

    Props:
      opciones  Array asociativo valor => texto, en el orden de presentación.
      activa    Valor de la solapa activa.
      accion    Acción Livewire que recibe el valor al tocar una solapa.
                Por omisión "cambiarSolapa".
--}}
@props([
    'opciones' => [],
    'activa' => null,
    'accion' => 'cambiarSolapa',
])

@php
    use App\Support\Tema;

    $base = 'whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-inset focus:ring-gray-300';
    $claseActiva = Tema::acentoBorde().' '.Tema::acentoTexto();
    $claseInactiva = 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700';
@endphp

<div {{ $attributes->class(['border-b border-gray-200 overflow-x-auto overflow-y-hidden']) }}>
    <nav class="-mb-px flex gap-2" role="tablist">
        @foreach($opciones as $valor => $texto)
            <button type="button" role="tab"
                    wire:click="{{ $accion }}('{{ $valor }}')"
                    aria-selected="{{ (string) $valor === (string) $activa ? 'true' : 'false' }}"
                    data-solapa="{{ $valor }}"
                    class="{{ $base }} {{ (string) $valor === (string) $activa ? $claseActiva : $claseInactiva }}">
                {{ $texto }}
            </button>
        @endforeach
    </nav>
</div>
