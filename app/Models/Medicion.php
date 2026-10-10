<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una lectura de altura con su posición GPS y su hora (glosario). La genera
 * la app de campo; `activo = false` la descarta del promedio.
 */
class Medicion extends Model
{
    use HasFactory;

    protected $table = 'mediciones';

    protected $fillable = [
        'recorrida_id',
        'uuid',
        'tomada_en',
        'altura_cm',
        'distancia_cm',
        'kg_ms_ha',
        'latitud',
        'longitud',
        'precision_m',
        'inclinacion_grados',
        'imagenes',
        'dispersion_cm',
        'calibracion',
        'activo',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'tomada_en' => 'datetime',
            'altura_cm' => 'float',
            'distancia_cm' => 'float',
            'kg_ms_ha' => 'integer',
            'latitud' => 'float',
            'longitud' => 'float',
            'precision_m' => 'float',
            'inclinacion_grados' => 'float',
            'imagenes' => 'integer',
            'dispersion_cm' => 'float',
            'calibracion' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function recorrida(): BelongsTo
    {
        return $this->belongsTo(Recorrida::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function tieneUbicacion(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }
}
