<?php

namespace Database\Seeders;

use App\Enums\ColorAcento;
use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        Configuracion::query()->firstOrCreate([], [
            'nombre' => 'Pasturómetro',
            'logo_path' => null,
            'color_acento' => ColorAcento::Green,
        ]);
    }
}
