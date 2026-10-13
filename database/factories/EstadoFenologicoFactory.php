<?php

namespace Database\Factories;

use App\Models\EstadoFenologico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstadoFenologico>
 */
class EstadoFenologicoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Recién pastoreada', 'Rebrote', 'Pleno crecimiento', 'Floración']).' '.fake()->unique()->numberBetween(1, 9999),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
