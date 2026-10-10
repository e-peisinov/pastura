{{-- La recorrida en su ficha, encima del tablero (D57, D78): el mismo hijo que
     monta Recorridas. Va al pie de la vista de cada bloque que use el trait
     AbreRecorridaEnModal, dentro del elemento raíz. --}}
@if($detalleId !== null)
    <livewire:recorridas.recorrida-detalle :recorrida-id="$detalleId" :key="'recorrida-detalle-'.$detalleId" />
@endif
