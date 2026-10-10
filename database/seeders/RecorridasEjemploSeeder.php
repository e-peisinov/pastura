<?php

namespace Database\Seeders;

use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recorridas de ejemplo para ver la pantalla en el navegador, sobre el campo
 * de prueba "La Esperanza" / "Lote 4" de la base de desarrollo. NO va en
 * DatabaseSeeder: se corre a mano.
 *
 *   php artisan db:seed --class=RecorridasEjemploSeeder
 *
 * Se reconocen por el dispositivo (self::DISPOSITIVO): volver a correrlo
 * reemplaza las de ejemplo y no toca las reales. Para borrarlas:
 *
 *   php artisan tinker --execute="App\Models\Recorrida::where('dispositivo', 'Datos de ejemplo')->delete()"
 *
 * (las mediciones se van en cascada). Los puntos caen dentro del contorno
 * del potrero y la altura sigue una curva de crecimiento con un pastoreo en
 * el medio, así el listado y el mapa muestran algo creíble.
 */
class RecorridasEjemploSeeder extends Seeder
{
    public const DISPOSITIVO = 'Datos de ejemplo';

    /**
     * Días atrás, hora de inicio, altura media (cm), cantidad de puntos, con
     * kg MS/ha, quién (recorredor | admin), anulada, puntos descartados.
     */
    private const RECORRIDAS = [
        [74, '08:10', 9.0, 14, false, 'recorredor', false, 0],
        [60, '07:45', 13.5, 18, false, 'recorredor', false, 1],
        [46, '09:20', 18.0, 16, true, 'admin', false, 0],
        [39, '08:30', 22.5, 20, true, 'recorredor', false, 2],
        [38, '16:05', 31.0, 12, true, 'recorredor', true, 0],  // anulada: mala calibración
        [25, '08:00', 7.5, 22, true, 'recorredor', false, 1],   // después del pastoreo
        [12, '08:40', 11.0, 18, true, 'admin', false, 0],
        [3, '07:55', 15.5, 24, true, 'recorredor', false, 1],
    ];

    public function run(): void
    {
        $potrero = Potrero::query()
            ->where('nombre', 'Lote 4')
            ->whereIn('campo_id', Campo::query()->select('id')->where('nombre', 'La Esperanza'))
            ->first();

        $usuarios = [
            'recorredor' => User::query()->where('email', 'recorredor@pastura.test')->first(),
            'admin' => User::query()->where('email', 'admin@pastura.test')->first(),
        ];

        if ($potrero === null || $potrero->contorno === null || in_array(null, $usuarios, true)) {
            $this->command?->warn('Falta el potrero "La Esperanza" / "Lote 4" con su contorno, o los usuarios de desarrollo: no se cargó nada.');

            return;
        }

        mt_srand(4);
        $anillo = self::anilloExterior($potrero->contorno);

        DB::transaction(function () use ($potrero, $usuarios, $anillo) {
            Recorrida::query()->where('dispositivo', self::DISPOSITIVO)->delete();

            foreach (self::RECORRIDAS as [$dias, $hora, $media, $cantidad, $conKg, $quien, $anulada, $descartados]) {
                $inicio = Carbon::today()->subDays($dias)->setTimeFromTimeString($hora);

                $recorrida = Recorrida::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'potrero_id' => $potrero->id,
                    'user_id' => $usuarios[$quien]->id,
                    'iniciada_en' => $inicio,
                    'dispositivo' => self::DISPOSITIVO,
                    'observaciones' => $anulada ? 'Calibración con el disco mal apoyado.' : null,
                    'activo' => ! $anulada,
                ]);

                $momento = $inicio->copy();
                for ($i = 0; $i < $cantidad; $i++) {
                    $momento = $momento->copy()->addSeconds(mt_rand(50, 150));
                    $descartado = $i >= $cantidad - $descartados;
                    // Un descartado es un error de lectura: una altura que no tiene sentido.
                    $altura = $descartado ? $media * 2.4 : max(2.0, $media + self::gauss() * $media * 0.22);
                    [$lon, $lat] = self::puntoAdentro($anillo);
                    $sinGps = mt_rand(1, 30) === 1;

                    Medicion::query()->create([
                        'recorrida_id' => $recorrida->id,
                        'uuid' => (string) Str::uuid(),
                        'tomada_en' => $momento,
                        'altura_cm' => round($altura, 1),
                        'distancia_cm' => round(118 - $altura, 1),
                        // Lo que habría visto el celular (kg MS/ha = 105 × altura + 600); el panel
                        // muestra los que salen de la fórmula del tipo de pastura (P16).
                        'kg_ms_ha' => $conKg ? (int) round(105 * $altura + 600) : null,
                        'latitud' => $sinGps ? null : round($lat, 7),
                        'longitud' => $sinGps ? null : round($lon, 7),
                        'precision_m' => $sinGps ? null : round(mt_rand(25, 120) / 10, 1),
                        'inclinacion_grados' => round(mt_rand(0, 60) / 10, 1),
                        'imagenes' => mt_rand(8, 12),
                        'dispersion_cm' => round(mt_rand(5, 60) / 100, 2),
                        'calibracion' => ['a' => 812.4, 'c' => -3.1, 'r2' => 0.998],
                        'activo' => ! $descartado,
                    ]);
                }
            }
        });

        $this->command?->info(count(self::RECORRIDAS).' recorridas de ejemplo cargadas en La Esperanza / Lote 4.');
    }

    /** @return list<array{0: float, 1: float}> [lon, lat] del primer polígono */
    private static function anilloExterior(array $contorno): array
    {
        return $contorno['type'] === 'MultiPolygon' ? $contorno['coordinates'][0][0] : $contorno['coordinates'][0];
    }

    /** Un punto al azar dentro del anillo (rechazo sobre el rectángulo que lo contiene). */
    private static function puntoAdentro(array $anillo): array
    {
        $lons = array_column($anillo, 0);
        $lats = array_column($anillo, 1);

        do {
            $lon = min($lons) + (max($lons) - min($lons)) * mt_rand() / mt_getrandmax();
            $lat = min($lats) + (max($lats) - min($lats)) * mt_rand() / mt_getrandmax();
        } while (! self::adentro($lon, $lat, $anillo));

        return [$lon, $lat];
    }

    /** Ray casting. */
    private static function adentro(float $x, float $y, array $anillo): bool
    {
        $dentro = false;
        for ($i = 0, $j = count($anillo) - 1; $i < count($anillo); $j = $i++) {
            [$xi, $yi] = $anillo[$i];
            [$xj, $yj] = $anillo[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }

    /** Normal estándar (Box-Muller). */
    private static function gauss(): float
    {
        $u = max(1e-9, mt_rand() / mt_getrandmax());
        $v = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
}
