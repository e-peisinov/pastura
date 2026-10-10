<?php

namespace App\Models;

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
 * Los kg MS/ha salen de la fórmula vigente del tipo de pastura del potrero,
 * punto por punto (P16), no de lo que mandó el celular.
 */
class Recorrida extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'potrero_id',
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

    public function potrero(): BelongsTo
    {
        return $this->belongsTo(Potrero::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * avg(max(0, A × altura + B)) de los puntos activos, con la fórmula del
     * tipo de pastura del potrero. Sin tipo o sin fórmula, null. El CASE
     * (y no GREATEST) corre igual en MySQL y en el SQLite de los tests.
     */
    private static function subconsultaKg(): QueryBuilder
    {
        $kg = 'tp.biomasa_a * mediciones.altura_cm + tp.biomasa_b';

        return DB::table('mediciones')
            ->join('recorridas as r', 'r.id', '=', 'mediciones.recorrida_id')
            ->join('potreros as p', 'p.id', '=', 'r.potrero_id')
            ->join('tipos_pastura as tp', 'tp.id', '=', 'p.tipo_pastura_id')
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

            $tipo = $this->potrero?->tipoPastura;
            $kgs = $this->mediciones()->where('activo', true)->get()
                ->map(fn (Medicion $medicion) => $medicion->kgMsHaSegun($tipo))
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
