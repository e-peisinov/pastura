<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Panel\LivewireEnSubcarpeta;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        $this->registrarGates();

        // El ingreso de la app de campo (P10): 5 intentos por minuto por email e IP.
        RateLimiter::for('ingreso-app', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        // El panel también anda en una subcarpeta, como en XAMPP (P13).
        LivewireEnSubcarpeta::registrar();
    }

    /**
     * Permisos por módulo (D49): `puedeVer` y `puedeEditar` reciben el nombre
     * del módulo (la clave de App\Support\Modulos) y leen la matriz del
     * usuario. El rol solo dice con qué niveles nace esa matriz. Cada acción
     * pide Editar en el módulo de la pantalla donde está su botón.
     */
    private function registrarGates(): void
    {
        Gate::define('puedeVer', fn (User $user, string $modulo): bool => $user->puedeVer($modulo));
        Gate::define('puedeEditar', fn (User $user, string $modulo): bool => $user->puedeEditar($modulo));
    }
}
