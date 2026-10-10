<?php

use App\Http\Controllers\Api\CamposController;
use App\Http\Controllers\Api\IngresoController;
use App\Http\Controllers\Api\RecorridasController;
use App\Http\Controllers\PaseAlPanelController;
use App\Http\Middleware\PuedeSincronizar;
use App\Http\Middleware\UsuarioActivoApi;
use Illuminate\Support\Facades\Route;

// API de la app de campo (P9 a P12). La app vive en /app, en el mismo
// servidor; se autentica con un token Bearer de Sanctum.
Route::prefix('v1')->group(function () {
    Route::post('ingreso', [IngresoController::class, 'ingresar'])->middleware('throttle:ingreso-app');

    // Toda la API, salvo el ingreso, pide un usuario activo.
    Route::middleware(['auth:sanctum', UsuarioActivoApi::class])->group(function () {
        Route::get('yo', [IngresoController::class, 'yo']);
        Route::post('salida', [IngresoController::class, 'salir']);
        // El pase para abrir el panel sin volver a escribir la contraseña (P12).
        Route::post('enlace-panel', [PaseAlPanelController::class, 'crear'])->middleware('throttle:30,1');

        // Las recorridas, además, piden Editar en Recorridas.
        Route::middleware(PuedeSincronizar::class)->group(function () {
            Route::get('campos', [CamposController::class, 'index']);
            Route::put('recorridas/{uuid}', [RecorridasController::class, 'update'])->whereUuid('uuid');
        });
    });
});
