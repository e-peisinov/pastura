{{--
    <x-ui.th> — cabecera de columna ordenable (estandares-ux.md §5, D55).

    Va dentro del <thead> de <x-ui.tabla>. Con `columna`, el rótulo es un
    botón que llama `ordenarPor('columna')` en el componente (trait
    OrdenaPorColumna) y muestra la flecha asc/desc cuando esa columna es la
    que manda. Sin `columna`, es un <th> común (acciones, columnas derivadas
    que no tienen por qué ordenarse).

      <x-ui.th columna="nombre" :orden="$orden">Cliente</x-ui.th>
      <x-ui.th columna="saldo" :orden="$orden" derecha>Saldo</x-ui.th>
      <x-ui.th derecha>Acciones</x-ui.th>

    Props:
      columna  Clave que recibe ordenarPor(). Sin ella no se ordena.
      orden    ['columna' => ..., 'direccion' => 'asc'|'desc'], lo que expone
               el trait con $this->orden().
      derecha  Alinea a la derecha (importes).
--}}
@props([
    'columna' => null,
    'orden' => ['columna' => null, 'direccion' => 'asc'],
    'derecha' => false,
])

@php
    use App\Support\Tema;

    $activa = $columna !== null && ($orden['columna'] ?? null) === $columna;
    $direccion = $activa ? ($orden['direccion'] ?? 'asc') : null;
    $ariaSort = $activa ? ($direccion === 'desc' ? 'descending' : 'ascending') : null;
@endphp

<th {{ $attributes->class(['px-4 py-3', 'text-right' => $derecha]) }}
    @if($columna) scope="col" @endif
    @if($ariaSort) aria-sort="{{ $ariaSort }}" @endif
    @if($columna) data-columna="{{ $columna }}" @endif>
    @if($columna)
        <button type="button" wire:click="ordenarPor('{{ $columna }}')"
                class="group inline-flex items-center gap-1.5 uppercase tracking-wide hover:text-gray-800 focus:outline-none focus:underline {{ $derecha ? 'flex-row-reverse' : '' }} {{ $activa ? 'text-gray-800' : '' }}"
                title="{{ __('ui.tabla.ordenar_por', ['columna' => trim(strip_tags($slot))]) }}">
            <span>{{ $slot }}</span>
            @if($activa)
                <i class="fa-solid {{ $direccion === 'desc' ? 'fa-arrow-down-wide-short' : 'fa-arrow-up-short-wide' }} text-[11px] {{ Tema::acentoTexto() }}" aria-hidden="true"></i>
            @else
                <i class="fa-solid fa-arrow-up-short-wide text-[11px] text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity" aria-hidden="true"></i>
            @endif
        </button>
    @else
        {{ $slot }}
    @endif
</th>
