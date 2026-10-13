{{-- Acciones de una curva, iguales en la tabla y en las cards. Parte de livewire.curvas.curvas. --}}
<x-ui.accion icono="fa-eye" :etiqueta="__('ui.acciones.ver')" wire:click="verDetalle({{ $curva->id }})" />
@if($puedeEditar)
    <x-ui.accion icono="fa-pen" :etiqueta="__('ui.acciones.editar')" wire:click="editar({{ $curva->id }})" />
    @unless($curva->activo)
        <x-ui.accion icono="fa-rotate-left" :etiqueta="__('curvas.hacer_vigente')" wire:click="hacerVigente({{ $curva->id }})" />
    @endunless
    <x-ui.accion icono="fa-trash" color="peligro" :etiqueta="__('ui.acciones.eliminar')" wire:click="confirmarBaja({{ $curva->id }})" />
@endif
