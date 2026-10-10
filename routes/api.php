<?php

use App\Http\Controllers\Api\CamposController;
use App\Http\Controllers\Api\IngresoController;
use App\Http\Controllers\Api\RecorridasController;
use App\Http\Middleware\PuedeSincronizar;
use Illuminate\Support\Facades\Route;

// API de la app de campo (P9 a P11). La app vive en /app, en el mismo
// servidor; se autentica con un token Bearer de Sanctum.
Route::prefix('v1')->group(function () {
    Route::post('ingreso', [IngresoController::class, 'ingresar'])->middleware('throttle:ingreso-app');

    // Toda la API, salvo el ingreso, pide un usuario activo con Editar en Recorridas.
    Route::middleware(['auth:sanctum', PuedeSincronizar::class])->group(function () {
        Route::get('yo', [IngresoController::class, 'yo']);
        Route::post('salida', [IngresoController::class, 'salir']);
        Route::get('campos', [CamposController::class, 'index']);
        Route::put('recorridas/{uuid}', [RecorridasController::class, 'update'])->whereUuid('uuid');
    });
});
