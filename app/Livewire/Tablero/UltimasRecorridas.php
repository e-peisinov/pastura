<?php

namespace App\Livewire\Tablero;

use App\Livewire\Traits\AbreRecorridaEnModal;
use App\Models\Recorrida;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Bloque "Últimas recorridas" del tablero (P18). Quien administra ve las de
 * todos; el recorredor, las suyas, y el bloque se llama "Mis recorridas"
 * (dominio.md, regla 3). Cuenta las de hoy y las de los últimos 7 días, con
 * enlace al listado filtrado por ese período, y lista las más recientes;
 * una fila abre la recorrida en su ficha (D78). Solo las activas: una
 * anulada no es una recorrida que cuente.
 */
class UltimasRecorridas extends Component
{
    use AbreRecorridaEnModal;

    public function render()
    {
        $usuario = auth()->user();
        $base = Recorrida::query()->visiblesPara($usuario)->where('recorridas.activo', true);
        $hoy = Carbon::today();
        $haceUnaSemana = $hoy->copy()->subDays(6);

        return view('livewire.tablero.ultimas-recorridas', [
            'esRecorredor' => $usuario->esRecorredor(),
            'contadores' => [
                [
                    'clave' => 'hoy',
                    'cantidad' => $base->clone()->where('recorridas.iniciada_en', '>=', $hoy)->count(),
                    'href' => route('recorridas', ['desde' => $hoy->toDateString(), 'hasta' => $hoy->toDateString()]),
                ],
                [
                    'clave' => 'semana',
                    'cantidad' => $base->clone()->where('recorridas.iniciada_en', '>=', $haceUnaSemana)->count(),
                    'href' => route('recorridas', ['desde' => $haceUnaSemana->toDateString(), 'hasta' => $hoy->toDateString()]),
                ],
            ],
            'recorridas' => $base->clone()
                ->conResumen()
                ->with(['potrero.campo', 'user'])
                ->orderByDesc('recorridas.iniciada_en')
                ->orderByDesc('recorridas.id')
                ->limit(Tablero::FILAS)
                ->get(),
        ]);
    }
}
