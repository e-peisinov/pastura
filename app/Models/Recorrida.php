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
            ->addSelect(['kg_ms_ha_medio' => static::subconsulta('avg(kg_ms_ha)')]);
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
        return Attribute::make(get: fn ($valor) => $this->promedio('kg_ms_ha_medio', 'kg_ms_ha', $valor));
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
