{{-- El tablero (D25, D45): una grilla de bloques, cada uno un subcomponente
     con su consulta. --}}
<div>
    <x-ui.pagina :titulo="__('tablero.titulo')" icono="fa-gauge-high">
        <p class="mb-4 text-sm text-gray-500" data-saludo>{{ __('tablero.saludo', ['nombre' => $nombre]) }}</p>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3" data-tablero>
        </div>
    </x-ui.pagina>
</div>
