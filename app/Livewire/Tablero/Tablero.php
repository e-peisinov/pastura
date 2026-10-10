<?php

namespace App\Livewire\Tablero;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * El tablero (D25, D45): el panorama al entrar. Es un componente padre que
 * arma la grilla y monta un subcomponente por bloque; cada bloque hace su
 * propia consulta y muestra un resumen más una lista corta con enlace a la
 * pantalla que lo resuelve. El tablero no edita nada.
 */
#[Layout('layouts.app')]
class Tablero extends Component
{
    /** Filas que muestra la lista corta de cada bloque. */
    public const FILAS = 5;

    public function render()
    {
        return view('livewire.tablero.tablero', [
            'nombre' => auth()->user()->name,
        ]);
    }
}
