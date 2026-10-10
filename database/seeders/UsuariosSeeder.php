<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(['email' => 'admin@pastura.test'], [
            'name' => 'Administración',
            'password' => 'password',
            'rol' => Rol::Admin,
            'email_verified_at' => now(),
        ]);

        User::query()->firstOrCreate(['email' => 'recorredor@pastura.test'], [
            'name' => 'Recorredor de prueba',
            'password' => 'password',
            'rol' => Rol::Recorredor,
            'email_verified_at' => now(),
        ]);
    }
}
