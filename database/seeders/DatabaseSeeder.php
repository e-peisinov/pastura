<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Instalación nueva: `php artisan migrate --seed`, solo sobre una base vacía
 * (regla 12).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ConfiguracionSeeder::class,
            EstadosFenologicosSeeder::class,
            UsuariosSeeder::class,
        ]);
    }
}
