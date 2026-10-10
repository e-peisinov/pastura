<?php

namespace Database\Factories;

use App\Models\Medicion;
use App\Models\Recorrida;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Medicion>
 */
class MedicionFactory extends Factory
{
    public function definition(): array
    {
        $altura = fake()->randomFloat(1, 5, 35);

        return [
            'recorrida_id' => Recorrida::factory(),
            'uuid' => (string) Str::uuid(),
            'tomada_en' => now()->subMinutes(fake()->numberBetween(1, 120)),
            'altura_cm' => $altura,
            'distancia_cm' => round(120 - $altura, 1),
            'kg_ms_ha' => null,
            'latitud' => -33.125 + fake()->randomFloat(5, -0.004, 0.004),
            'longitud' => -64.345 + fake()->randomFloat(5, -0.004, 0.004),
            'precision_m' => fake()->randomFloat(1, 3, 12),
            'inclinacion_grados' => fake()->randomFloat(1, 0, 6),
            'imagenes' => 10,
            'dispersion_cm' => fake()->randomFloat(2, 0, 0.5),
            'calibracion' => null,
            'activo' => true,
        ];
    }

    public function sinUbicacion(): static
    {
        return $this->state(fn () => ['latitud' => null, 'longitud' => null, 'precision_m' => null]);
    }
}
