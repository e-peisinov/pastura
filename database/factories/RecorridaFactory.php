<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\CurvaBiomasa;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Por omisión, la recorrida toma el estado fenológico de la curva vigente de
 * su potrero, si tiene una (P23): así una recorrida de prueba tiene kilos
 * como en el campo. Para otro estado, o ninguno, se pasa
 * `estado_fenologico_id`.
 *
 * @extends Factory<Recorrida>
 */
class RecorridaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'potrero_id' => Potrero::factory(),
            'estado_fenologico_id' => fn (array $atributos) => CurvaBiomasa::query()
                ->where('potrero_id', $atributos['potrero_id'])->vigentes()->latest('id')->value('estado_fenologico_id'),
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
