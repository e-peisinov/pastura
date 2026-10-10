<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La puerta de la API de la app de campo (P10), después de `auth:sanctum`:
 * un usuario dado de baja (D49) recibe 401, para que la app vuelva a la
 * pantalla de ingreso.
 */
class UsuarioActivoApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null || ! $usuario->activo) {
            return response()->json(['message' => __('api.sin_sesion')], 401);
        }

        return $next($request);
    }
}
