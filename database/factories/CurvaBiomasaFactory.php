<?php

namespace Database\Factories;

use App\Models\Configuracion;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\Potrero;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sin estado, la curva no tiene muestras (y no tiene recta). `recta(A, B)`
 * le carga tres muestras justo sobre kg = A × altura + B, a 10, 20 y 30 cm,
 * con el área del disco que haya en Configuración.
 *
 * @extends Factory<CurvaBiomasa>
 */
class CurvaBiomasaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'potrero_id' => Potrero::factory(),
            'estado_fenologico_id' => EstadoFenologico::factory(),
            'fecha' => now()->subDays(fake()->numberBetween(1, 60))->toDateString(),
            'observaciones' => null,
            'activo' => true,
        ];
    }

    public function recta(float $a, float $b): static
    {
        return $this->afterCreating(function (CurvaBiomasa $curva) use ($a, $b) {
            $area = Configuracion::instancia()->area_disco_m2;

            foreach ([10, 20, 30] as $altura) {
                // kg/ha = g / m² × 10 → g = kg × m² / 10.
                $curva->muestras()->create(['altura_cm' => $altura, 'peso_seco_g' => ($a * $altura + $b) * $area / 10]);
            }
        });
    }

    public function reemplazada(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
