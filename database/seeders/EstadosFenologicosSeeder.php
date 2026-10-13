<?php

namespace Database\Seeders;

use App\Models\EstadoFenologico;
use Illuminate\Database\Seeder;

/**
 * Los estados de la pastura con los que arranca una instalación (P23), en el
 * orden en que los ofrece la app. Los dijo Mateo; se cambian en
 * Configuración. Solo agrega los que faltan:
 *
 *   php artisan db:seed --class=EstadosFenologicosSeeder
 */
class EstadosFenologicosSeeder extends Seeder
{
    public const ESTADOS = ['Recién pastoreada', 'Rebrote', 'Pleno crecimiento'];

    public function run(): void
    {
        foreach (self::ESTADOS as $nombre) {
            EstadoFenologico::query()->firstOrCreate(['nombre' => $nombre]);
        }
    }
}
