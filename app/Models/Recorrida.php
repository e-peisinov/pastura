<?php

namespace App\Models;

use App\Enums\EntidadAuditada;
use App\Models\Traits\Auditable;
use App\Support\Biomasa\RectaBiomasa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Una salida de un recorredor a un potrero (glosario). La sube la app de
 * campo con su uuid (P4). Lo que se muestra (puntos, altura media, kg MS/ha
 * medio) se calcula de las mediciones activas, nunca se guarda (regla 8).
 * Los kg MS/ha salen de la curva de biomasa vigente del potrero para el
 * estado fenológico que eligió el recorredor, punto por punto (P23), no de
 * lo que mandó el celular.
 */
class Recorrida extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'uuid',
        'potrero_id',
        'estado_fenologico_id',
        'user_id',
        'iniciada_en',
        'dispositivo',
        'observaciones',
        'activo',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'iniciada_en' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    // ----- Auditoría (P18): anular, reactivar, mover de potrero ------------

    public static function entidadAuditada(): EntidadAuditada
    {
        return EntidadAuditada::Recorrida;
    }

    /** La subida (alta y cambios de la app) no se audita: sí anularla, moverla y cambiarle el estado (cambia los kg, P23). */
    protected function camposAuditados(): array
    {
        return ['activo', 'potrero_id', 'estado_fenologico_id'];
    }

    protected function valorParaAuditoria(string $campo, mixed $valor): mixed
    {
        return match ($campo) {
            'potrero_id' => Potrero::query()->find($valor)?->nombreCompleto(),
            'estado_fenologico_id' => EstadoFenologico::query()->find($valor)?->nombre,
            default => $this->valorAuditableComun($valor),
        };
    }

    /** @return array<string, mixed> */
    protected function datosAuditoria(): array
    {
        return self::referenciaAuditoria($this);
    }

    /**
     * La recorrida en la foto de su fila y de la de sus puntos: potrero (el
     * de ahora), día y recorredor.
     *
     * @return array<string, mixed>
     */
    public static function referenciaAuditoria(self $recorrida): array
    {
        return [
            'recorrida_id' => $recorrida->id,
            'potrero_id' => $recorrida->potrero_id,
            'potrero' => Potrero::query()->find($recorrida->potrero_id)?->nombreCompleto(),
            'fecha' => $recorrida->iniciada_en?->toDateString(),
            'recorredor' => User::query()->whereKey($recorrida->user_id)->value('name'),
        ];
    }

    public function potrero(): BelongsTo
    {
        return $this->belongsTo(Potrero::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estadoFenologico(): BelongsTo
    {
        return $this->belongsTo(EstadoFenologico::class);
    }

    /** La curva vigente de su potrero para su estado (P23), o null. */
    public function curvaBiomasa(): ?CurvaBiomasa
    {
        return CurvaBiomasa::vigentePara($this->potrero_id, $this->estado_fenologico_id);
    }

    /** La recta con la que se calculan sus kg MS/ha; null = sin kilos. */
    public function recta(): ?RectaBiomasa
    {
        return $this->curvaBiomasa()?->recta();
    }

    public function mediciones(): HasMany
    {
        return $this->hasMany(Medicion::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Lo que ve un usuario: quien administra, todas; el recorredor, las suyas (dominio.md, regla 3). */
    public function scopeVisiblesPara(Builder $query, User $usuario): Builder
    {
        return $usuario->esRecorredor() ? $query->where('recorridas.user_id', $usuario->id) : $query;
    }

    // ----- Resumen calculado (regla 8) -------------------------------------

    /** Agrega `puntos`, `altura_media` y `kg_ms_ha_medio` como subconsultas (para listados). */
    public function scopeConResumen(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('recorridas.*');
        }

        return $query
            ->addSelect(['puntos' => static::subconsulta('count(*)')])
            ->addSelect(['altura_media' => static::subconsulta('avg(altura_cm)')])
            ->addSelect(['kg_ms_ha_medio' => static::subconsultaKg()]);
    }

    /**
     * avg(max(0, A × altura + B)) de los puntos activos, con la recta de la
     * curva vigente del potrero para el estado de la recorrida (P23). La
     * recta se ajusta acá mismo por mínimos cuadrados con las sumas de las
     * muestras (la misma cuenta que RectaBiomasa::ajustar): sin curva, sin
     * estado o sin dos alturas distintas, null. Los gramos pasan a kg/ha con
     * el área del disco (× 10 / m²). El CASE (y no GREATEST) y el `* 1.0`
     * (que la división no sea entera) corren igual en MySQL y en SQLite.
     */
    private static function subconsultaKg(): QueryBuilder
    {
        $porGramo = sprintf('%.10F', 10 / Configuracion::instancia()->area_disco_m2);

        $sumas = DB::table('muestras_curva')
            ->groupBy('curva_biomasa_id')
            ->selectRaw('curva_biomasa_id, count(*) as n, sum(altura_cm) as sx, sum(peso_seco_g) as sy, sum(altura_cm * altura_cm) as sxx, sum(altura_cm * peso_seco_g) as sxy');

        $a = '((s.n * s.sxy - s.sx * s.sy) * 1.0 / nullif(s.n * s.sxx - s.sx * s.sx, 0))';
        $b = "((s.sy - {$a} * s.sx) * 1.0 / s.n)";
        $kg = "({$a} * mediciones.altura_cm + {$b}) * {$porGramo}";

        return DB::table('mediciones')
            ->join('recorridas as r', 'r.id', '=', 'mediciones.recorrida_id')
            ->join('curvas_biomasa as c', fn ($join) => $join
                ->on('c.potrero_id', '=', 'r.potrero_id')
                ->on('c.estado_fenologico_id', '=', 'r.estado_fenologico_id')
                ->where('c.activo', true))
            ->joinSub($sumas, 's', 's.curva_biomasa_id', '=', 'c.id')
            ->selectRaw("avg(case when {$kg} < 0 then 0 else {$kg} end)")
            ->whereColumn('mediciones.recorrida_id', 'recorridas.id')
            ->where('mediciones.activo', true);
    }

    private static function subconsulta(string $agregado): QueryBuilder
    {
        return DB::table('mediciones')
            ->selectRaw($agregado)
            ->whereColumn('mediciones.recorrida_id', 'recorridas.id')
            ->where('mediciones.activo', true);
    }

    protected function puntos(): Attribute
    {
        return Attribute::make(get: fn ($valor) => array_key_exists('puntos', $this->attributes)
            ? (int) $valor
            : $this->mediciones()->where('activo', true)->count());
    }

    protected function alturaMedia(): Attribute
    {
        return Attribute::make(get: fn ($valor) => $this->promedio('altura_media', 'altura_cm', $valor));
    }

    protected function kgMsHaMedio(): Attribute
    {
        return Attribute::make(get: function ($valor) {
            if (array_key_exists('kg_ms_ha_medio', $this->attributes)) {
                return $valor === null ? null : (float) $valor;
            }

            $recta = $this->recta();
            $kgs = $this->mediciones()->where('activo', true)->get()
                ->map(fn (Medicion $medicion) => $medicion->kgMsHaSegun($recta))
                ->filter(fn (?float $kg) => $kg !== null);

            return $kgs->isEmpty() ? null : (float) $kgs->avg();
        });
    }

    /** El valor de la subconsulta si vino con conResumen(); si no, la consulta. */
    private function promedio(string $atributo, string $columna, mixed $valor): ?float
    {
        if (! array_key_exists($atributo, $this->attributes)) {
            $valor = $this->mediciones()->where('activo', true)->avg($columna);
        }

        return $valor === null ? null : (float) $valor;
    }
}
