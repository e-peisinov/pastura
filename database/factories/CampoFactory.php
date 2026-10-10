<?php

namespace Database\Factories;

use App\Models\Campo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campo>
 */
class CampoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'La '.fake()->unique()->lastName(),
            'localidad' => fake()->city(),
            'observaciones' => null,
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
