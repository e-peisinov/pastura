<?php

namespace Database\Factories;

use App\Enums\ColorAcento;
use App\Models\Configuracion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Configuracion>
 */
class ConfiguracionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Pasturómetro',
            'logo_path' => null,
            'color_acento' => ColorAcento::Green,
        ];
    }
}
