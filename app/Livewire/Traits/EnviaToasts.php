<?php

namespace App\Livewire\Traits;

/**
 * Toasts desde una acción Livewire (estandares-ux.md §3).
 *
 * El layout no se re-renderiza en una acción, así que un flash de sesión no
 * llega al contenedor: se dispara el evento de navegador `toast`, que escucha
 * <x-ui.toasts /> montado en el layout. Los métodos son protected a propósito:
 * no son acciones invocables desde el navegador.
 */
trait EnviaToasts
{
    protected function toastExito(string $mensaje): void
    {
        $this->toast('exito', $mensaje);
    }

    protected function toastError(string $mensaje): void
    {
        $this->toast('error', $mensaje);
    }

    protected function toastInfo(string $mensaje): void
    {
        $this->toast('info', $mensaje);
    }

    protected function toastAviso(string $mensaje): void
    {
        $this->toast('aviso', $mensaje);
    }

    private function toast(string $tipo, string $mensaje): void
    {
        $this->dispatch('toast', tipo: $tipo, mensaje: $mensaje);
    }
}
