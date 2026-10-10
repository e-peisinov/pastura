<?php

use App\Http\Middleware\PuedeVerModulo;
use App\Http\Middleware\UsuarioActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Un usuario dado de baja pierde la sesión al primer pedido (D49).
        $middleware->web(append: [UsuarioActivo::class]);

        $middleware->alias([
            // `modulo:caja`: al menos Ver en ese módulo (D49).
            'modulo' => PuedeVerModulo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
