<?php

namespace Database\Factories;

use App\Models\Campo;
use App\Models\Potrero;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Potrero>
 */
class PotreroFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campo_id' => Campo::factory(),
            'nombre' => 'Potrero '.fake()->unique()->numberBetween(1, 9999),
            'tipo_pastura_id' => null,
            'contorno' => null,
            'observaciones' => null,
            'activo' => true,
        ];
    }

    /** Un rectángulo de ~0,01° × 0,01° cerca de Río Cuarto (≈ 104 ha). */
    public function conContorno(float $lon = -64.35, float $lat = -33.12): static
    {
        return $this->state(fn () => ['contorno' => [
            'type' => 'Polygon',
            'coordinates' => [[[$lon, $lat], [$lon + 0.01, $lat], [$lon + 0.01, $lat - 0.01], [$lon, $lat - 0.01], [$lon, $lat]]],
        ]]);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
