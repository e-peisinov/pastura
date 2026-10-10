<?php

namespace App\Livewire\Traits;

use App\Models\Recorrida;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/**
 * Un bloque del tablero que abre una recorrida en su ficha, encima del
 * tablero (D78): la fila es un botón `wire:click="verDetalle(id)"` y la vista
 * monta el mismo hijo que Recorridas con el parcial
 * `livewire.tablero.partials.recorrida-detalle`. Lo que se hace en la ficha
 * (descartar un punto, anular) se hace con el permiso de Recorridas.
 *
 * Solo se abren las recorridas que el usuario ve (dominio.md, regla 3): una
 * ajena para el recorredor es 404.
 */
trait AbreRecorridaEnModal
{
    /** Recorrida abierta en su ficha, encima del tablero. */
    public ?int $detalleId = null;

    public function verDetalle(int $id): void
    {
        Gate::authorize('puedeVer', 'recorridas');

        $this->detalleId = Recorrida::query()->visiblesPara(auth()->user())->findOrFail($id)->id;
    }

    #[On('cerrar-recorrida-detalle')]
    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    /** La ficha cambió algo (un punto, la anuló): el bloque se redibuja. */
    #[On('recorrida-actualizada')]
    public function recorridaActualizada(): void {}
}
