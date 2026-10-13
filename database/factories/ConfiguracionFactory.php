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
            'nombre' => 'GeoGrass',
            'logo_path' => null,
            'color_acento' => ColorAcento::Green,
            'dias_sin_medir' => Configuracion::DIAS_SIN_MEDIR,
            'puntos_minimos' => Configuracion::PUNTOS_MINIMOS,
            'area_disco_m2' => Configuracion::AREA_DISCO_M2,
        ];
    }
}
