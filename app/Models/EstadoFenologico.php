<?php

namespace App\Models;

use App\Enums\EntidadAuditada;
use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El estado de la pastura cuando se mide (glosario, P23): recién
 * pastoreada, rebrote, pleno crecimiento... Cada potrero tiene una curva de
 * biomasa por estado, y el recorredor lo elige al empezar la recorrida. Se
 * administra en Configuración.
 */
class EstadoFenologico extends Model
{
    use Auditable, HasFactory;

    protected $table = 'estados_fenologicos';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    // ----- Auditoría (P18): todo, porque elige la curva de los kg -------------

    public static function entidadAuditada(): EntidadAuditada
    {
        return EntidadAuditada::EstadoFenologico;
    }

    protected function camposAuditados(): array
    {
        return ['nombre', 'activo'];
    }

    protected function auditaAlta(): bool
    {
        return true;
    }

    protected function auditaBaja(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    protected function datosAuditoria(): array
    {
        return ['nombre' => $this->nombre];
    }

    public function curvas(): HasMany
    {
        return $this->hasMany(CurvaBiomasa::class);
    }

    public function recorridas(): HasMany
    {
        return $this->hasMany(Recorrida::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Con curvas o recorridas se desactiva; sin nada se borra (D20). */
    public function tieneHistoria(): bool
    {
        return $this->curvas()->exists() || $this->recorridas()->exists();
    }
}
