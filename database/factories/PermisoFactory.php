<?php

namespace Database\Factories;

use App\Enums\Nivel;
use App\Models\Permiso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permiso>
 */
class PermisoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'modulo' => 'campos',
            'nivel' => Nivel::Ver,
        ];
    }
}
