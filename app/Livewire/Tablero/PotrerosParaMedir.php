<?php

namespace App\Livewire\Tablero;

use App\Enums\EstadoPotrero;
use App\Models\Configuracion;
use App\Support\Tablero\EstadoPotreros;
use App\Support\Tablero\FilaPotrero;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Bloque "Potreros para medir" del tablero (P18), de los dos roles: los que
 * quedaron sin medir (la última recorrida tiene más días que los de
 * Configuración) y los que nunca se midieron. Dice adónde ir a medir.
 *
 * Los contadores filtran la lista acá mismo (D76). Las filas no abren nada:
 * lo que hay que hacer es salir con la app.
 */
class PotrerosParaMedir extends Component
{
    public const GRUPOS = ['nunca_medidos', 'sin_medir'];

    public ?string $filtro = null;

    public function filtrar(string $grupo): void
    {
        if (! in_array($grupo, self::GRUPOS, true)) {
            return;
        }

        $this->filtro = $this->filtro === $grupo ? null : $grupo;
    }

    public function render()
    {
        $filas = EstadoPotreros::filas();
        $nunca = $filas->where('estado', EstadoPotrero::NuncaMedido)
            ->sortBy(fn (FilaPotrero $f) => $f->potrero->campo->nombre.' '.$f->potrero->nombre)->values();
        $sinMedir = $filas->where('sinMedir', true)->sortByDesc('dias')->values();

        return view('livewire.tablero.potreros-para-medir', [
            'contadores' => [
                ['grupo' => 'sin_medir', 'cantidad' => $sinMedir->count(), 'color' => 'ambar'],
                ['grupo' => 'nunca_medidos', 'cantidad' => $nunca->count(), 'color' => 'rojo'],
            ],
            'lista' => $this->lista($nunca, $sinMedir),
            'diasSinMedir' => Configuracion::instancia()->dias_sin_medir,
        ]);
    }

    /**
     * Con filtro, todos los de ese grupo; sin filtro, primero los nunca
     * medidos y después los que hace más tiempo que no se miden, hasta
     * Tablero::FILAS.
     *
     * @param  Collection<int, FilaPotrero>  $nunca
     * @param  Collection<int, FilaPotrero>  $sinMedir
     * @return Collection<int, FilaPotrero>
     */
    private function lista(Collection $nunca, Collection $sinMedir): Collection
    {
        return match ($this->filtro) {
            'nunca_medidos' => $nunca,
            'sin_medir' => $sinMedir,
            default => $nunca->concat($sinMedir)->take(Tablero::FILAS)->values(),
        };
    }
}
