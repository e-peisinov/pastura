<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `modulo:<nombre>` en las rutas (D49): solo pasa un usuario con al
 * menos Ver en ese módulo. Sin acceso bloquea la ruta con 403; el sidebar
 * ya no la muestra (App\Support\Modulos::visibles).
 */
class PuedeVerModulo
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        abort_unless($request->user()?->puedeVer($modulo), 403);

        return $next($request);
    }
}
