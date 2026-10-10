{{-- Acciones de una fila (§5, solo icono): las mismas en la tabla y en la card. Recibe $usuario y $yoId. --}}
<x-ui.accion icono="fa-pen" :etiqueta="__('ui.acciones.editar')" wire:click="editar({{ $usuario->id }})" />
<x-ui.accion icono="fa-key" :etiqueta="__('usuarios.acciones.contrasena')" wire:click="abrirContrasena({{ $usuario->id }})" />
@if(! $usuario->activo)
    <x-ui.accion icono="fa-rotate-left" :etiqueta="__('ui.acciones.reactivar')" wire:click="reactivar({{ $usuario->id }})" />
@elseif($usuario->id !== $yoId)
    <x-ui.accion icono="fa-ban" color="peligro" :etiqueta="__('ui.acciones.desactivar')" wire:click="confirmarBaja({{ $usuario->id }})" />
@endif
