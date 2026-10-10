<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bajar el catálogo y subir recorridas pide Editar en Recorridas (P10, P12);
 * sin ese permiso, 403. Va después de UsuarioActivoApi.
 */
class PuedeSincronizar
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->puedeEditar('recorridas')) {
            return response()->json(['message' => __('api.sin_permiso')], 403);
        }

        return $next($request);
    }
}
