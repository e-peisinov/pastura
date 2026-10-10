<?php

namespace App\Livewire\Tablero;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * El tablero (D25, D45, P18): el panorama al entrar. Es un componente padre
 * que arma la grilla y monta un subcomponente por bloque; cada bloque hace su
 * propia consulta y muestra un resumen más una lista corta con enlace a la
 * pantalla que lo resuelve. El tablero no edita nada: una recorrida tocada en
 * un bloque se abre en su ficha encima (D78), con el permiso de Recorridas.
 *
 * Quien administra ve el estado de los potreros, los potreros para medir, las
 * últimas recorridas y las recorridas para revisar. El recorredor ve los
 * potreros para medir y sus recorridas. Eso lo dice el rol, no el permiso
 * (D49): el tablero es igual con Ver o Editar.
 */
#[Layout('layouts.app')]
class Tablero extends Component
{
    /** Filas que muestra la lista corta de cada bloque. */
    public const FILAS = 5;

    public function render()
    {
        return view('livewire.tablero.tablero', [
            'esRecorredor' => auth()->user()->esRecorredor(),
            'nombre' => auth()->user()->name,
        ]);
    }
}
