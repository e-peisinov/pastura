<?php

namespace App\Support\Reportes;

use App\Models\Campo;
use App\Models\Potrero;
use App\Models\Recorrida;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Los números de la pantalla de Reportes (P18, P21; molde: el módulo 39 del
 * kit), para un período. Todo se calcula desde las tablas de siempre
 * (regla 8) y en PHP, así corre igual en MySQL y en el SQLite de los tests.
 *
 * Cuentan las recorridas activas con algún punto activo (regla 1): una
 * anulada o con todo descartado no es una medición. Los kg MS/ha salen de la
 * curva de biomasa vigente del potrero para el estado de cada recorrida (P23).
 */
final class Reportes
{
    /** El período con el que abre la pantalla: este mes y los dos anteriores. */
    public const MESES_POR_OMISION = 3;

    /** @var Collection<int, Recorrida>|null */
    private ?Collection $recorridas = null;

    public function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly ?int $campoId = null,
        public readonly ?int $potreroId = null,
    ) {}

    /** Este mes y los anteriores hasta completar `$meses`, desde un día 1 para que los meses salgan enteros. */
    public static function ultimosMeses(int $meses, ?int $campoId = null, ?int $potreroId = null): self
    {
        return new self(
            Carbon::today()->startOfMonth()->subMonthsNoOverflow(max($meses, 1) - 1)->toDateString(),
            Carbon::today()->toDateString(),
            $campoId,
            $potreroId,
        );
    }

    /**
     * Lo que viaja en la URL (`desde`, `hasta`, `campo`, `potrero`), para el
     * PDF. Sin fechas, los últimos tres meses, como la pantalla; una fecha
     * inválida cae en hoy y, si quedan cruzadas, manda `desde`.
     *
     * @param  array<string, mixed>  $parametros
     */
    public static function desdeParametros(array $parametros): self
    {
        $id = fn ($valor) => is_numeric($valor) && (int) $valor > 0 ? (int) $valor : null;
        $campo = $id($parametros['campo'] ?? null);
        $potrero = $id($parametros['potrero'] ?? null);
        $desde = $parametros['desde'] ?? null;
        $hasta = $parametros['hasta'] ?? null;

        if (blank($desde) && blank($hasta)) {
            return self::ultimosMeses(self::MESES_POR_OMISION, $campo, $potrero);
        }

        $hoy = Carbon::today()->toDateString();
        $valida = fn ($fecha) => is_string($fecha) && Carbon::hasFormat($fecha, 'Y-m-d');

        $desde = $valida($desde) ? $desde : $hoy;
        $hasta = $valida($hasta) ? $hasta : $hoy;

        return new self($desde, max($desde, $hasta), $campo, $potrero);
    }

    // ----- Resumen y comparación --------------------------------------------------

    /** @return array{recorridas: int, puntos: int, potreros: int, hectareas: float} */
    public function resumen(): array
    {
        $recorridas = $this->recorridas();
        $potreros = $recorridas->pluck('potrero')->unique('id');

        return [
            'recorridas' => $recorridas->count(),
            'puntos' => (int) $recorridas->sum('puntos'),
            'potreros' => $potreros->count(),
            'hectareas' => round((float) $potreros->sum(fn (Potrero $p) => $p->superficieHa() ?? 0), 2),
        ];
    }

    /**
     * El período inmediatamente anterior y del mismo largo: para el 1 al 30
     * de septiembre, del 2 al 31 de agosto (no "el mes pasado": un mes a
     * medio correr se compara contra la misma cantidad de días).
     */
    public function anterior(): self
    {
        $desde = Carbon::parse($this->desde);
        $dias = (int) $desde->diffInDays(Carbon::parse($this->hasta)) + 1;

        return new self($desde->copy()->subDays($dias)->toDateString(), $desde->copy()->subDay()->toDateString(), $this->campoId, $this->potreroId);
    }

    /**
     * Cada número del resumen contra el del período anterior. `variacion` es
     * el cambio en porcentaje, o null si antes era cero (pasar de 0 a algo
     * no es "infinito por ciento").
     *
     * @return array<string, array{actual: float|int, anterior: float|int, variacion: ?float}>
     */
    public function comparacion(): array
    {
        $actual = $this->resumen();
        $anterior = $this->anterior()->resumen();
        $comparacion = [];

        foreach ($actual as $clave => $valor) {
            $antes = $anterior[$clave];

            $comparacion[$clave] = [
                'actual' => $valor,
                'anterior' => $antes,
                'variacion' => $antes > 0 ? round(($valor - $antes) / $antes * 100, 1) : null,
            ];
        }

        return $comparacion;
    }

    // ----- Evolución de un potrero ---------------------------------------------------

    /**
     * Cada recorrida del potrero en el período, en orden, con la tasa de
     * crecimiento desde la anterior del período: lo que cambió la altura
     * (y los kg) dividido los días que pasaron. Negativa si bajó (se
     * pastoreó: no se registra, P18). Dos el mismo día no tienen tasa.
     *
     * @return list<array{fecha: string, etiqueta: string, altura: float, kg: ?float, tasa_cm: ?float, tasa_kg: ?float}>
     */
    public function evolucion(int $potreroId): array
    {
        $filas = [];
        $previa = null;

        foreach ($this->recorridas()->where('potrero_id', $potreroId) as $recorrida) {
            $dia = $recorrida->iniciada_en->copy()->startOfDay();
            $dias = $previa === null ? 0 : (int) $previa['dia']->diffInDays($dia);
            $altura = (float) $recorrida->altura_media;
            $kg = $recorrida->kg_ms_ha_medio;

            $filas[] = [
                'fecha' => $dia->toDateString(),
                'etiqueta' => $dia->format('d/m'),
                'altura' => $altura,
                'kg' => $kg,
                'tasa_cm' => $dias > 0 ? ($altura - $previa['altura']) / $dias : null,
                'tasa_kg' => $dias > 0 && $kg !== null && $previa['kg'] !== null ? ($kg - $previa['kg']) / $dias : null,
            ];

            $previa = ['dia' => $dia, 'altura' => $altura, 'kg' => $kg];
        }

        return $filas;
    }

    // ----- Potreros de un campo -------------------------------------------------------

    /**
     * Los potreros activos del campo por la altura de su última recorrida
     * del período, los más altos primero, y cuántos no se midieron.
     *
     * @return array{filas: list<array{nombre: string, altura: float, kg: ?float, fecha: string}>, sin_datos: int}
     */
    public function potrerosDelCampo(int $campoId): array
    {
        $potreros = Potrero::query()->where('campo_id', $campoId)->where('activo', true)->get();
        $ultimas = $this->ultimasPorPotrero();

        $filas = $potreros
            ->map(fn (Potrero $p) => ($r = $ultimas->get($p->id)) === null ? null : [
                'nombre' => $p->nombre,
                'altura' => (float) $r->altura_media,
                'kg' => $r->kg_ms_ha_medio,
                'fecha' => $r->iniciada_en->toDateString(),
            ])
            ->filter()
            ->sortByDesc('altura')
            ->values()
            ->all();

        return ['filas' => $filas, 'sin_datos' => $potreros->count() - count($filas)];
    }

    // ----- Disponibilidad forrajera -------------------------------------------------

    /**
     * Los kg de materia seca de cada campo activo (glosario): por cada
     * potrero activo con superficie y con kg MS/ha en su última recorrida del
     * período, kg MS/ha × hectáreas. Solo los campos con algún dato, de más a
     * menos; `con_dato` de `potreros` dice cuánto del campo cubre el número.
     *
     * @return list<array{nombre: string, kg: float, con_dato: int, potreros: int}>
     */
    public function disponibilidad(): array
    {
        $ultimas = $this->ultimasPorPotrero();

        return Campo::query()->where('activo', true)
            ->with(['potreros' => fn ($q) => $q->where('activo', true)])
            ->orderBy('nombre')
            ->get()
            ->map(function (Campo $campo) use ($ultimas) {
                $kg = 0.0;
                $conDato = 0;

                foreach ($campo->potreros as $potrero) {
                    $porHa = $ultimas->get($potrero->id)?->kg_ms_ha_medio;
                    $ha = $potrero->superficieHa();

                    if ($porHa !== null && $ha !== null) {
                        $kg += $porHa * $ha;
                        $conDato++;
                    }
                }

                return ['nombre' => $campo->nombre, 'kg' => round($kg, 1), 'con_dato' => $conDato, 'potreros' => $campo->potreros->count()];
            })
            ->filter(fn (array $fila) => $fila['con_dato'] > 0)
            ->sortByDesc('kg')
            ->values()
            ->all();
    }

    // ----- Actividad por recorredor ---------------------------------------------------

    /** @return list<array{nombre: string, recorridas: int, puntos: int}> */
    public function porRecorredor(): array
    {
        return $this->recorridas()
            ->groupBy('user_id')
            ->map(fn (Collection $suyas) => [
                'nombre' => $suyas->first()->user->name,
                'recorridas' => $suyas->count(),
                'puntos' => (int) $suyas->sum('puntos'),
            ])
            ->sortBy([['recorridas', 'desc'], ['nombre', 'asc']])
            ->values()
            ->all();
    }

    // ----- Datos ------------------------------------------------------------------

    /**
     * Las recorridas activas del período con algún punto activo, con su
     * resumen, de la más vieja a la más nueva. Se consultan una sola vez.
     *
     * @return Collection<int, Recorrida>
     */
    private function recorridas(): Collection
    {
        return $this->recorridas ??= Recorrida::query()
            ->where('recorridas.activo', true)
            ->whereBetween('recorridas.iniciada_en', [$this->desde.' 00:00:00', $this->hasta.' 23:59:59'])
            ->conResumen()
            ->with(['potrero.tipoPastura', 'user'])
            ->orderBy('recorridas.iniciada_en')
            ->orderBy('recorridas.id')
            ->get()
            ->filter(fn (Recorrida $r) => $r->puntos > 0)
            ->values();
    }

    /** @return Collection<int, Recorrida> la última del período de cada potrero, por id de potrero */
    private function ultimasPorPotrero(): Collection
    {
        return $this->recorridas()->groupBy('potrero_id')->map(fn (Collection $suyas) => $suyas->last());
    }
}
