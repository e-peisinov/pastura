<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campo;
use App\Models\Configuracion;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\Potrero;
use Illuminate\Http\JsonResponse;

/**
 * Lo que la app de campo baja para elegir dónde se mide: los estados
 * fenológicos activos y los campos activos con sus potreros activos, el
 * contorno y la recta de cada curva de biomasa vigente, por estado (regla 4,
 * P23). Un campo sin potreros activos no se manda, porque no hay nada que
 * elegir. Los estados van en el orden en que se cargaron.
 */
class CamposController extends Controller
{
    public function index(): JsonResponse
    {
        $campos = Campo::where('activo', true)
            ->whereHas('potreros', fn ($potreros) => $potreros->where('activo', true))
            ->with([
                'potreros' => fn ($potreros) => $potreros->where('activo', true)->orderBy('nombre'),
                'potreros.tipoPastura',
                'potreros.curvas' => fn ($curvas) => $curvas->vigentes()->with('muestras'),
            ])
            ->orderBy('nombre')
            ->get();

        $area = Configuracion::instancia()->area_disco_m2;

        return response()->json([
            'generado_en' => now()->toIso8601String(),
            'estados_fenologicos' => EstadoFenologico::activos()->orderBy('id')->get(['id', 'nombre'])
                ->map(fn (EstadoFenologico $estado) => ['id' => $estado->id, 'nombre' => $estado->nombre])
                ->values(),
            'campos' => $campos->map(fn (Campo $campo) => [
                'id' => $campo->id,
                'nombre' => $campo->nombre,
                'localidad' => $campo->localidad,
                'potreros' => $campo->potreros->map(fn (Potrero $potrero) => [
                    'id' => $potrero->id,
                    'nombre' => $potrero->nombre,
                    'tipo_pastura' => $potrero->tipoPastura?->nombre,
                    // kg MS/ha = max(0, a × altura + b) con la curva vigente del estado (P23).
                    'curvas' => $potrero->curvas
                        ->map(fn (CurvaBiomasa $curva) => [$curva, $curva->recta($area)])
                        ->filter(fn (array $par) => $par[1] !== null)
                        ->map(fn (array $par) => [
                            'estado_fenologico_id' => $par[0]->estado_fenologico_id,
                            'a' => round($par[1]->a, 3),
                            'b' => round($par[1]->b, 1),
                        ])
                        ->values(),
                    'superficie_ha' => ($ha = $potrero->superficieHa()) === null ? null : round($ha, 2),
                    'contorno' => $potrero->contorno,
                ])->values(),
            ])->values(),
        ]);
    }
}
