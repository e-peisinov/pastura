<?php

use App\Livewire\Actions\Logout;
use App\Livewire\Campos\Campos;
use App\Livewire\Tablero\Tablero;
use App\Livewire\Usuarios\Usuarios;
use Illuminate\Support\Facades\Route;

// La raíz no tiene pantalla propia (D52): sin sesión va al login, con sesión al tablero.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('inicio');

// Pantallas del sistema: componentes Livewire de clase (regla 6). Cada ruta
// pide al menos Ver en su módulo (`modulo:<nombre>`, D49); Sin acceso da 403.
Route::middleware(['auth', 'verified'])->group(function () {
    // El tablero vive en /dashboard (D25); es un padre con un bloque por subcomponente (D45).
    Route::get('dashboard', Tablero::class)->name('dashboard')->middleware('modulo:tablero');
    // Campos y, desde su ficha, sus potreros con el contorno en el mapa (P5).
    Route::get('campos', Campos::class)->name('campos')->middleware('modulo:campos');
    // Usuarios (D49): alta, contraseña, baja lógica y la matriz de permisos de cada uno.
    Route::get('admin/usuarios', Usuarios::class)->name('admin.usuarios')->middleware('modulo:usuarios');
});

Route::middleware('auth')->group(function () {
    Route::view('profile', 'profile')->name('profile');

    // Cerrar sesión desde el menú del usuario del sidebar (D24).
    Route::post('logout', function (Logout $logout) {
        $logout();

        return redirect('/');
    })->name('logout');
});

require __DIR__.'/auth.php';
