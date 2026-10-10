<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubirRecorridaRequest;
use App\Support\Recorridas\SincronizadorRecorridas;
use Illuminate\Http\JsonResponse;

/**
 * La subida de una recorrida desde la app de campo: `PUT` sobre su uuid,
 * idempotente (P4, P11). 201 si la crea, 200 si ya estaba.
 */
class RecorridasController extends Controller
{
    public function update(SubirRecorridaRequest $request, string $uuid, SincronizadorRecorridas $sincronizador): JsonResponse
    {
        $resultado = $sincronizador->sincronizar($request->user(), strtolower($uuid), $request->validated());

        return response()->json(['uuid' => strtolower($uuid), ...$resultado], $resultado['creada'] ? 201 : 200);
    }
}
