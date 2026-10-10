<?php

use App\Http\Middleware\PuedeVerModulo;
use App\Http\Middleware\UsuarioActivo;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
        // La API de la app de campo responde siempre en JSON (P10), aunque el
        // pedido no mande `Accept: application/json`.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => __('api.sin_sesion')], 401)
            : null);
    })->create();
