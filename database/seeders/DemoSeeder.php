<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\TipoPastura;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Set de demo para el video y las presentaciones: dos campos vecinos en
 * Ayacucho (Estancia La Aurora y El Mirador) con los contornos sobre lotes
 * reales de la imagen satelital, cuatro tipos de pastura con alturas y
 * fórmula, tres usuarios y unos seis meses de recorridas. NO va en
 * DatabaseSeeder: se corre a mano y solo agrega datos.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Las fechas son relativas a hoy, así el tablero queda con potreros en cada
 * estado (listos, en crecimiento, bajo el remanente, uno nunca medido, dos
 * sin medir hace más de una semana) y tres recorridas para revisar (pocos
 * puntos, sin GPS, un punto fuera del contorno). Volver a correrlo
 * reemplaza solo sus recorridas, que se reconocen por el uuid.
 */
class DemoSeeder extends Seeder
{
    public const ADMIN = 'lucia.fernandez@laaurora.test';

    private const USUARIOS = [
        'lucia' => [self::ADMIN, 'Lucía Fernández', Rol::Admin],
        'martin' => ['martin.gomez@laaurora.test', 'Martín Gómez', Rol::Recorredor],
        'diego' => ['diego.ruiz@laaurora.test', 'Diego Ruiz', Rol::Recorredor],
    ];

    /** Nombre => [ingreso cm, salida cm, A, B] (kg MS/ha = A × altura + B). */
    private const TIPOS = [
        'Pastura consociada' => [20, 7, 115, 250],
        'Raigrás anual' => [18, 6, 125, 150],
        'Avena' => [25, 8, 95, 300],
        'Campo natural' => [15, 5, 85, 350],
    ];

    private const CAMPOS = [
        'Estancia La Aurora' => ['Ayacucho', 'Casco y ocho potreros sobre la ruta.'],
        'El Mirador' => ['Ayacucho', 'Campo vecino, arrendado. Verdeos de invierno.'],
    ];

    /**
     * Campo, nombre, tipo, contorno (lon, lat) y la última recorrida:
     * [días atrás, altura media, puntos, quién, motivo para revisar].
     * null = nunca medido.
     */
    private const POTREROS = [
        ['Estancia La Aurora', 'La Loma', 'Pastura consociada', [[-58.6196364, -37.1704], [-58.6155177, -37.1727939], [-58.6183231, -37.1758829], [-58.6224417, -37.173489]], [6, 16.5, 24, 'martin', null]],
        ['Estancia La Aurora', 'El Molino', 'Pastura consociada', [[-58.6155177, -37.1727939], [-58.6113991, -37.1751878], [-58.6142044, -37.1782768], [-58.6183231, -37.1758829]], [2, 24.5, 26, 'martin', null]],
        ['Estancia La Aurora', 'Los Sauces', 'Raigrás anual', [[-58.6113991, -37.1751878], [-58.6072805, -37.1775817], [-58.6100858, -37.1806706], [-58.6142044, -37.1782768]], [4, 21.0, 22, 'diego', null]],
        ['Estancia La Aurora', 'La Tranquera', 'Pastura consociada', [[-58.6183231, -37.1758829], [-58.6142044, -37.1782768], [-58.6170098, -37.1813657], [-58.6211284, -37.1789718]], [9, 12.0, 23, 'diego', null]],
        ['Estancia La Aurora', 'El Bajo', 'Campo natural', [[-58.6142044, -37.1782768], [-58.6100858, -37.1806706], [-58.6128911, -37.1837596], [-58.6170098, -37.1813657]], [3, 4.2, 21, 'martin', null]],
        ['Estancia La Aurora', 'El Monte', 'Campo natural', [[-58.625247, -37.1765779], [-58.6211284, -37.1789718], [-58.6239337, -37.1820608], [-58.6280524, -37.1796669]], [12, 11.0, 25, 'lucia', null]],
        ['Estancia La Aurora', 'La Laguna', 'Raigrás anual', [[-58.6211284, -37.1789718], [-58.6170098, -37.1813657], [-58.6198151, -37.1844547], [-58.6239337, -37.1820608]], [5, 5.1, 22, 'diego', 'sin_gps']],
        ['Estancia La Aurora', 'El Recto', 'Pastura consociada', [[-58.6170098, -37.1813657], [-58.6128911, -37.1837596], [-58.6156965, -37.1868486], [-58.6198151, -37.1844547]], [1, 27.0, 11, 'martin', 'pocos']],
        ['El Mirador', 'Avena 1', 'Avena', [[-58.635545, -37.169636], [-58.632328, -37.171507], [-58.635133, -37.174596], [-58.638351, -37.172725]], [7, 19.0, 24, 'diego', 'fuera']],
        ['El Mirador', 'Avena 2', 'Avena', [[-58.632328, -37.171507], [-58.62911, -37.173377], [-58.631915, -37.176466], [-58.635133, -37.174596]], [2, 28.5, 25, 'martin', null]],
        ['El Mirador', 'Raigrás nuevo', 'Raigrás anual', [[-58.62911, -37.173377], [-58.625892, -37.175247], [-58.628698, -37.178336], [-58.631915, -37.176466]], null],
    ];

    /** Recorridas anteriores a la última, cada tantos días: unos seis meses. */
    private const ANTERIORES = 12;

    private const CADA_DIAS = 14;

    public function run(): void
    {
        mt_srand(7);

        DB::transaction(function () {
            $tipos = $this->tipos();
            $usuarios = $this->usuarios();
            $campos = $this->campos();

            foreach (self::POTREROS as $n => [$campo, $nombre, $tipo, $anillo, $ultima]) {
                $potrero = Potrero::query()->firstOrCreate(
                    ['campo_id' => $campos[$campo]->id, 'nombre' => $nombre],
                    ['tipo_pastura_id' => $tipos[$tipo]->id, 'contorno' => ['type' => 'Polygon', 'coordinates' => [[...$anillo, $anillo[0]]]]],
                );

                if ($ultima !== null) {
                    $this->historial($n, $potrero, $ultima, $tipos[$tipo], $usuarios);
                }
            }
        });

        $this->command?->info('Set de demo cargado: Estancia La Aurora y El Mirador.');
    }

    /** @return array<string, TipoPastura> */
    private function tipos(): array
    {
        $tipos = [];
        foreach (self::TIPOS as $nombre => [$ingreso, $salida, $a, $b]) {
            $tipos[$nombre] = TipoPastura::query()->firstOrCreate(['nombre' => $nombre], [
                'altura_ingreso_cm' => $ingreso,
                'altura_salida_cm' => $salida,
                'biomasa_a' => $a,
                'biomasa_b' => $b,
            ]);
        }

        return $tipos;
    }

    /** @return array<string, User> */
    private function usuarios(): array
    {
        $usuarios = [];
        foreach (self::USUARIOS as $clave => [$email, $nombre, $rol]) {
            $usuarios[$clave] = User::query()->firstOrCreate(['email' => $email], [
                'name' => $nombre,
                'password' => 'password',
                'rol' => $rol,
                'email_verified_at' => now(),
            ]);
        }

        return $usuarios;
    }

    /** @return array<string, Campo> */
    private function campos(): array
    {
        $campos = [];
        foreach (self::CAMPOS as $nombre => [$localidad, $observaciones]) {
            $campos[$nombre] = Campo::query()->firstOrCreate(['nombre' => $nombre], [
                'localidad' => $localidad,
                'observaciones' => $observaciones,
            ]);
        }

        return $campos;
    }

    /**
     * La última recorrida y las anteriores, cada CADA_DIAS días. Hacia atrás
     * la altura baja lo que crece en dos semanas, hasta el remanente: ahí
     * hubo un pastoreo y la recorrida de antes estaba alta.
     *
     * @param  array{0: int, 1: float, 2: int, 3: string, 4: ?string}  $ultima
     * @param  array<string, User>  $usuarios
     */
    private function historial(int $n, Potrero $potrero, array $ultima, TipoPastura $tipo, array $usuarios): void
    {
        [$dias, $media, $puntos, $quien, $motivo] = $ultima;
        $uuids = array_map(fn (int $k) => self::uuid("demo-{$n}-{$k}"), range(0, self::ANTERIORES));
        Recorrida::query()->whereIn('uuid', $uuids)->delete();

        $anillo = $potrero->contorno['coordinates'][0];
        $quienes = array_keys($usuarios);
        $altura = $media;

        for ($k = 0; $k <= self::ANTERIORES; $k++) {
            $esUltima = $k === 0;
            if (! $esUltima) {
                $altura -= 3.6 + ($n + $k) % 3 * 0.6;
                if ($altura < $tipo->altura_salida_cm + 1.5) {
                    $altura = $tipo->altura_ingreso_cm + 2 + ($n + $k) % 4;
                }
            }
            $inicio = Carbon::today()->subDays($dias + self::CADA_DIAS * $k)->setTime(7 + $k % 3, 10 * ($n % 6));

            $recorrida = Recorrida::query()->create([
                'uuid' => $uuids[$k],
                'potrero_id' => $potrero->id,
                'user_id' => $usuarios[$esUltima ? $quien : $quienes[($n + $k) % 2 + 1]]->id,
                'iniciada_en' => $inicio,
                'dispositivo' => $esUltima && $quien === 'diego' ? 'Samsung Galaxy A15' : 'Motorola moto g54',
            ]);

            $this->mediciones($recorrida, $inicio, (float) $altura, $esUltima ? $puntos : 20 + mt_rand(0, 6), $anillo, $tipo, $esUltima ? $motivo : null);
        }
    }

    private function mediciones(Recorrida $recorrida, Carbon $inicio, float $media, int $cantidad, array $anillo, TipoPastura $tipo, ?string $motivo): void
    {
        $momento = $inicio->copy();
        for ($i = 0; $i < $cantidad; $i++) {
            $momento = $momento->copy()->addSeconds(mt_rand(55, 140));
            $altura = round(max(2.0, $media + self::gauss() * max(1.0, $media * 0.18)), 1);
            [$lon, $lat] = self::puntoAdentro($anillo);

            $sinGps = $motivo === 'sin_gps' && $i % 9 === 4;
            if ($motivo === 'fuera' && $i === $cantidad - 1) {
                // Un punto tomado del otro lado del alambrado.
                [$lon, $lat] = [$anillo[0][0] + 0.0012, $anillo[0][1] + 0.0004];
            }

            Medicion::query()->create([
                'recorrida_id' => $recorrida->id,
                'uuid' => self::uuid("{$recorrida->uuid}-{$i}"),
                'tomada_en' => $momento,
                'altura_cm' => $altura,
                'distancia_cm' => round(118 - $altura, 1),
                'kg_ms_ha' => (int) max(0, round($tipo->biomasa_a * $altura + $tipo->biomasa_b)),
                'latitud' => $sinGps ? null : round($lat, 7),
                'longitud' => $sinGps ? null : round($lon, 7),
                'precision_m' => $sinGps ? null : round(mt_rand(25, 90) / 10, 1),
                'inclinacion_grados' => round(mt_rand(0, 45) / 10, 1),
                'imagenes' => 10,
                'dispersion_cm' => round(mt_rand(8, 45) / 100, 2),
                'calibracion' => ['a' => -4810.2, 'c' => 96.4, 'r2' => 0.999],
            ]);
        }
    }

    /** Un uuid fijo a partir de un texto, para reconocer lo del set al repetirlo. */
    private static function uuid(string $texto): string
    {
        $h = md5('pasturometro-demo-'.$texto);

        return sprintf('%s-%s-4%s-a%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3), substr($h, 17, 3), substr($h, 20, 12));
    }

    /** Un punto al azar dentro del anillo, con un margen de los alambrados. */
    private static function puntoAdentro(array $anillo): array
    {
        $lons = array_column($anillo, 0);
        $lats = array_column($anillo, 1);
        $cx = array_sum(array_slice($lons, 0, 4)) / 4;
        $cy = array_sum(array_slice($lats, 0, 4)) / 4;

        do {
            $lon = min($lons) + (max($lons) - min($lons)) * mt_rand() / mt_getrandmax();
            $lat = min($lats) + (max($lats) - min($lats)) * mt_rand() / mt_getrandmax();
            // achicado un 10 % hacia el centro: ningún punto pegado al alambrado
            $lon = $cx + ($lon - $cx) * 0.9;
            $lat = $cy + ($lat - $cy) * 0.9;
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
