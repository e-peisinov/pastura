<?php

namespace App\Livewire\Tablero;

use App\Enums\MotivoRevision;
use App\Livewire\Traits\AbreRecorridaEnModal;
use App\Support\Tablero\FilaRevision;
use App\Support\Tablero\RevisionRecorridas;
use Livewire\Component;

/**
 * Bloque "Recorridas para revisar" del tablero (P18), de quien administra:
 * las de los últimos 30 días con pocos puntos, con puntos sin GPS o con
 * puntos fuera del contorno. Revisarla es abrirla (D78) y decidir si se
 * descartan puntos o se anula.
 *
 * Un contador por motivo que filtra la lista acá mismo (D76).
 */
class RecorridasParaRevisar extends Component
{
    use AbreRecorridaEnModal;

    public ?string $filtro = null;

    public function filtrar(string $motivo): void
    {
        if (MotivoRevision::tryFrom($motivo) === null) {
            return;
        }

        $this->filtro = $this->filtro === $motivo ? null : $motivo;
    }

    public function render()
    {
        $filas = RevisionRecorridas::filas();
        $filtrado = $this->filtro !== null ? MotivoRevision::from($this->filtro) : null;

        return view('livewire.tablero.recorridas-para-revisar', [
            'contadores' => collect(MotivoRevision::cases())->map(fn (MotivoRevision $motivo) => [
                'motivo' => $motivo,
                'cantidad' => $filas->filter(fn (FilaRevision $f) => $f->tiene($motivo))->count(),
            ]),
            'lista' => $filtrado === null
                ? $filas->take(Tablero::FILAS)
                : $filas->filter(fn (FilaRevision $f) => $f->tiene($filtrado))->values(),
            'filtrado' => $filtrado,
            'dias' => RevisionRecorridas::DIAS,
        ]);
    }
}
