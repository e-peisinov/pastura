<?php

namespace Database\Factories;

use App\Models\TipoPastura;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoPastura>
 */
class TipoPasturaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Alfalfa', 'Festuca', 'Raigrás', 'Verdeo de avena', 'Pastura base alfalfa']).' '.fake()->unique()->numberBetween(1, 9999),
            'altura_ingreso_cm' => 20,
            'altura_salida_cm' => 7,
            'activo' => true,
        ];
    }

    public function sinAlturas(): static
    {
        return $this->state(fn () => ['altura_ingreso_cm' => null, 'altura_salida_cm' => null]);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
