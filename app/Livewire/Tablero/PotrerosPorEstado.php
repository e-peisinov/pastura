<?php

namespace App\Livewire\Tablero;

use App\Enums\EstadoPotrero;
use App\Livewire\Traits\AbreRecorridaEnModal;
use App\Support\Tablero\EstadoPotreros;
use App\Support\Tablero\FilaPotrero;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Bloque "Estado de los potreros" del tablero (P18), de quien administra:
 * cuántos están listos para pastorear, en crecimiento y bajo el remanente,
 * según la última recorrida de cada uno, y la lista de los listos, los más
 * altos primero.
 *
 * Los contadores filtran la lista acá mismo (D76): un clic deja todos los de
 * ese estado en una caja con scroll; otro clic saca el filtro. Una fila abre
 * la última recorrida del potrero en su ficha (D78).
 */
class PotrerosPorEstado extends Component
{
    use AbreRecorridaEnModal;

    /** Los estados que se cuentan y por los que se filtra. */
    public const ESTADOS = [EstadoPotrero::Listo, EstadoPotrero::EnCrecimiento, EstadoPotrero::BajoRemanente];

    public ?string $filtro = null;

    public function filtrar(string $estado): void
    {
        $elegido = EstadoPotrero::tryFrom($estado);

        if ($elegido === null || ! in_array($elegido, self::ESTADOS, true)) {
            return;
        }

        $this->filtro = $this->filtro === $estado ? null : $estado;
    }

    public function render()
    {
        $filas = EstadoPotreros::filas();
        $filtrado = $this->filtro !== null ? EstadoPotrero::from($this->filtro) : null;

        return view('livewire.tablero.potreros-por-estado', [
            'contadores' => collect(self::ESTADOS)->map(fn (EstadoPotrero $estado) => [
                'estado' => $estado,
                'cantidad' => $filas->where('estado', $estado)->count(),
            ]),
            'lista' => $this->lista($filas, $filtrado ?? EstadoPotrero::Listo, $filtrado !== null),
            'filtrado' => $filtrado,
            'sinAlturas' => $filas->where('estado', EstadoPotrero::SinAlturas)->count(),
            'veConfiguracion' => Gate::allows('puedeVer', 'configuracion'),
        ]);
    }

    /**
     * Los de ese estado: bajo el remanente, los más bajos primero; el resto,
     * los más altos. Sin filtro, los primeros Tablero::FILAS listos.
     *
     * @param  Collection<int, FilaPotrero>  $filas
     * @return Collection<int, FilaPotrero>
     */
    private function lista(Collection $filas, EstadoPotrero $estado, bool $todos): Collection
    {
        $lista = $filas->where('estado', $estado)
            ->sortBy(fn (FilaPotrero $f) => $f->alturaCm(), SORT_NUMERIC, $estado !== EstadoPotrero::BajoRemanente)
            ->values();

        return $todos ? $lista : $lista->take(Tablero::FILAS);
    }
}
