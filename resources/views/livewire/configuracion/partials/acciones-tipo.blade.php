{{-- Acciones de un tipo de pastura, compartidas por la tabla y la card (§5). --}}
<x-ui.accion icono="fa-pen" :etiqueta="__('ui.acciones.editar')" wire:click="editarTipo({{ $tipo->id }})" />
@if($tipo->activo)
    <x-ui.accion icono="fa-trash" color="peligro" :etiqueta="__('ui.acciones.eliminar')" wire:click="confirmarBaja({{ $tipo->id }})" />
@else
    <x-ui.accion icono="fa-rotate-left" :etiqueta="__('ui.acciones.reactivar')" wire:click="reactivar({{ $tipo->id }})" />
@endif
