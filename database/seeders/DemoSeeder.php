<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Campo;
use App\Models\Configuracion;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\TipoPastura;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Set de demo para el video y las presentaciones: dos campos vecinos en
 * Ayacucho (Estancia La Aurora y El Mirador) con los contornos sobre lotes
 * reales de la imagen satelital, cuatro tipos de pastura con alturas, los
 * estados de la pastura, una curva de biomasa por potrero y estado (P23),
 * tres usuarios y unos seis meses de recorridas. NO va en DatabaseSeeder: se
 * corre a mano y solo agrega datos.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Las fechas son relativas a hoy, así el tablero queda con potreros en cada
 * estado (listos, en crecimiento, bajo el remanente, uno nunca medido, dos
 * sin medir hace más de una semana) y tres recorridas para revisar (pocos
 * puntos, sin GPS, un punto fuera del contorno). Cada recorrida tiene el
 * estado que corresponde a su altura, y El Monte no tiene curvas: así se ve
 * una recorrida sin kg MS/ha. Volver a correrlo reemplaza solo sus
 * recorridas (por el uuid) y las muestras de sus curvas.
 */
class DemoSeeder extends Seeder
{
    public const ADMIN = 'lucia.fernandez@laaurora.test';

    private const USUARIOS = [
        'lucia' => [self::ADMIN, 'Lucía Fernández', Rol::Admin],
        'martin' => ['martin.gomez@laaurora.test', 'Martín Gómez', Rol::Recorredor],
        'diego' => ['diego.ruiz@laaurora.test', 'Diego Ruiz', Rol::Recorredor],
    ];

    /**
     * Nombre => [ingreso cm, salida cm, A, B]. A y B son la recta de base de
     * las curvas de demo (kg MS/ha = A × altura + B); cada estado la corre.
     */
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

    /**
     * Cómo corre cada estado la recta del tipo: recién pastoreada el pasto es
     * más denso por centímetro; en plena primavera se acuesta y pesa menos
     * (lo que contó Mateo). Estado => [× A, × B].
     */
    private const ESTADOS = [
        'Recién pastoreada' => [1.15, 0.5],
        'Rebrote' => [1.0, 1.0],
        'Pleno crecimiento' => [0.85, 1.3],
    ];

    /** Un potrero sin curvas, para mostrar una recorrida sin kilos. */
    private const SIN_CURVAS = 'El Monte';

    /** Las alturas de los cortes de cada curva: unas 15, como hace Mateo. */
    private const ALTURAS_MUESTRAS = [4, 6, 7, 9, 10, 12, 13, 15, 16, 18, 20, 22, 24, 27, 30];

    /** Recorridas anteriores a la última, cada tantos días: unos seis meses. */
    private const ANTERIORES = 12;

    private const CADA_DIAS = 14;

    public function run(): void
    {
        mt_srand(7);

        DB::transaction(function () {
            $this->call(EstadosFenologicosSeeder::class);
            $tipos = $this->tipos();
            $usuarios = $this->usuarios();
            $campos = $this->campos();
            $estados = EstadoFenologico::query()->whereIn('nombre', array_keys(self::ESTADOS))->get()->keyBy('nombre');

            foreach (self::POTREROS as $n => [$campo, $nombre, $tipo, $anillo, $ultima]) {
                $potrero = Potrero::query()->firstOrCreate(
                    ['campo_id' => $campos[$campo]->id, 'nombre' => $nombre],
                    ['tipo_pastura_id' => $tipos[$tipo]->id, 'contorno' => ['type' => 'Polygon', 'coordinates' => [[...$anillo, $anillo[0]]]]],
                );

                if ($ultima !== null) {
                    $this->historial($n, $potrero, $ultima, $tipos[$tipo], $usuarios, $estados);
                }
            }

            // Después de las recorridas, para no correr la secuencia de mt_rand (el tablero de demo queda igual).
            foreach (self::POTREROS as $n => [$campo, $nombre, $tipo]) {
                if ($nombre !== self::SIN_CURVAS) {
                    $this->curvas($n, Potrero::query()->where('campo_id', $campos[$campo]->id)->where('nombre', $nombre)->sole(), $tipo, $estados);
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
     * @param  Collection<string, EstadoFenologico>  $estados
     */
    private function historial(int $n, Potrero $potrero, array $ultima, TipoPastura $tipo, array $usuarios, Collection $estados): void
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

            $estado = self::estadoSegun((float) $altura, $tipo);
            $recorrida = Recorrida::query()->create([
                'uuid' => $uuids[$k],
                'potrero_id' => $potrero->id,
                'estado_fenologico_id' => $estados[$estado]->id,
                'user_id' => $usuarios[$esUltima ? $quien : $quienes[($n + $k) % 2 + 1]]->id,
                'iniciada_en' => $inicio,
                'dispositivo' => $esUltima && $quien === 'diego' ? 'Samsung Galaxy A15' : 'Motorola moto g54',
            ]);

            $this->mediciones($recorrida, $inicio, (float) $altura, $esUltima ? $puntos : 20 + mt_rand(0, 6), $anillo, self::recta($tipo->nombre, $estado), $esUltima ? $motivo : null);
        }
    }

    /** El estado que eligiría el recorredor al ver la pastura: cerca del remanente, recién pastoreada; pasada la de ingreso, en pleno crecimiento. */
    private static function estadoSegun(float $altura, TipoPastura $tipo): string
    {
        return match (true) {
            $altura < $tipo->altura_salida_cm + 4 => 'Recién pastoreada',
            $altura >= $tipo->altura_ingreso_cm => 'Pleno crecimiento',
            default => 'Rebrote',
        };
    }

    /** [A, B] de la curva de demo de un tipo en un estado. */
    private static function recta(string $tipo, string $estado): array
    {
        [, , $a, $b] = self::TIPOS[$tipo];
        [$porA, $porB] = self::ESTADOS[$estado];

        return [$a * $porA, $b * $porB];
    }

    /**
     * Una curva vigente por estado, con 15 muestras alrededor de su recta
     * (±8 %). El ruido sale del nombre del potrero, no de mt_rand: es el
     * mismo cada vez y no corre la secuencia de las recorridas.
     *
     * @param  Collection<string, EstadoFenologico>  $estados
     */
    private function curvas(int $n, Potrero $potrero, string $tipo, Collection $estados): void
    {
        $area = Configuracion::instancia()->area_disco_m2;

        foreach (array_keys(self::ESTADOS) as $e => $estado) {
            [$a, $b] = self::recta($tipo, $estado);
            $curva = CurvaBiomasa::query()->firstOrCreate(
                ['potrero_id' => $potrero->id, 'estado_fenologico_id' => $estados[$estado]->id, 'observaciones' => 'Set de demo'],
                ['fecha' => Carbon::today()->subDays(150 - 30 * $e - $n)->toDateString(), 'activo' => true],
            );
            $curva->muestras()->delete();

            foreach (self::ALTURAS_MUESTRAS as $i => $altura) {
                $ruido = (crc32("{$potrero->nombre}-{$estado}-{$i}") % 1601 - 800) / 10000;
                $kg = max(50.0, ($a * $altura + $b) * (1 + $ruido));
                // kg/ha = g / m² × 10 → g = kg × m² / 10.
                $curva->muestras()->create(['altura_cm' => $altura, 'peso_seco_g' => round($kg * $area / 10, 2)]);
            }
        }
    }

    /** @param  array{0: float, 1: float}  $recta  lo que vio el celular: la curva del estado de la recorrida */
    private function mediciones(Recorrida $recorrida, Carbon $inicio, float $media, int $cantidad, array $anillo, array $recta, ?string $motivo): void
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
                'kg_ms_ha' => (int) max(0, round($recta[0] * $altura + $recta[1])),
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
