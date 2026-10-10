<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campo;
use App\Models\Potrero;
use Illuminate\Http\JsonResponse;

/**
 * Lo que la app de campo baja para elegir dónde se mide: los campos activos
 * con sus potreros activos y el contorno (regla 4). Un campo sin potreros
 * activos no se manda, porque no hay nada que elegir.
 */
class CamposController extends Controller
{
    public function index(): JsonResponse
    {
        $campos = Campo::where('activo', true)
            ->whereHas('potreros', fn ($potreros) => $potreros->where('activo', true))
            ->with(['potreros' => fn ($potreros) => $potreros->where('activo', true)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'generado_en' => now()->toIso8601String(),
            'campos' => $campos->map(fn (Campo $campo) => [
                'id' => $campo->id,
                'nombre' => $campo->nombre,
                'localidad' => $campo->localidad,
                'potreros' => $campo->potreros->map(fn (Potrero $potrero) => [
                    'id' => $potrero->id,
                    'nombre' => $potrero->nombre,
                    'tipo_pastura' => $potrero->tipo_pastura,
                    'superficie_ha' => ($ha = $potrero->superficieHa()) === null ? null : round($ha, 2),
                    'contorno' => $potrero->contorno,
                ])->values(),
            ])->values(),
        ]);
    }
}
