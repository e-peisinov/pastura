{{-- Acciones de un estado de la pastura, compartidas por la tabla y la card (§5). --}}
<x-ui.accion icono="fa-pen" :etiqueta="__('ui.acciones.editar')" wire:click="editar({{ $estado->id }})" />
@if($estado->activo)
    <x-ui.accion icono="fa-trash" color="peligro" :etiqueta="__('ui.acciones.eliminar')" wire:click="confirmarBaja({{ $estado->id }})" />
@else
    <x-ui.accion icono="fa-rotate-left" :etiqueta="__('ui.acciones.reactivar')" wire:click="reactivar({{ $estado->id }})" />
@endif
