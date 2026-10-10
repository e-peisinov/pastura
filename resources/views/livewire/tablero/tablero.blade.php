{{-- El tablero (D25, D45, P18): una grilla de bloques, cada uno un subcomponente
     con su consulta. El recorredor ve los potreros para medir y sus recorridas. --}}
<div>
    <x-ui.pagina :titulo="__('tablero.titulo')" icono="fa-gauge-high">
        <p class="mb-4 text-sm text-gray-500" data-saludo>{{ __('tablero.saludo', ['nombre' => $nombre]) }}</p>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2" data-tablero>
            @unless($esRecorredor)
                <livewire:tablero.potreros-por-estado />
            @endunless

            <livewire:tablero.potreros-para-medir />
            <livewire:tablero.ultimas-recorridas />

            @unless($esRecorredor)
                <livewire:tablero.recorridas-para-revisar />
            @endunless
        </div>
    </x-ui.pagina>
</div>
