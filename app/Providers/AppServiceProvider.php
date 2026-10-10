<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
