<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Recorrida>
 */
class RecorridaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'potrero_id' => Potrero::factory(),
            'user_id' => User::factory()->state(['rol' => Rol::Recorredor]),
            'iniciada_en' => now()->subHours(fake()->numberBetween(1, 240)),
            'dispositivo' => 'iPhone',
            'observaciones' => null,
            'activo' => true,
        ];
    }

    public function anulada(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
