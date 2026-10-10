<?php

namespace App\Models;

use App\Enums\EntidadAuditada;
use App\Models\Traits\Auditable;
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
    use Auditable, HasFactory;

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

    /**
     * Los kg MS/ha de este punto con la fórmula vigente del tipo de pastura
     * (P16). `kg_ms_ha` es otra cosa: lo que vio el recorredor en el celular.
     */
    public function kgMsHaSegun(?TipoPastura $tipo): ?float
    {
        return $tipo?->kgMsHa((float) $this->altura_cm);
    }

    // ----- Auditoría (P18): descartar y restaurar ----------------------------

    public static function entidadAuditada(): EntidadAuditada
    {
        return EntidadAuditada::Medicion;
    }

    protected function camposAuditados(): array
    {
        return ['activo'];
    }

    /** @return array<string, mixed> */
    protected function datosAuditoria(): array
    {
        $recorrida = Recorrida::query()->find($this->recorrida_id);

        return [
            'hora' => $this->tomada_en?->format('H:i'),
            'altura_cm' => $this->altura_cm,
        ] + ($recorrida ? Recorrida::referenciaAuditoria($recorrida) : []);
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
