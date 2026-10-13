<?php

namespace App\Models;

use App\Enums\EntidadAuditada;
use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La pastura de un potrero (glosario, P16): sus alturas de referencia. Los
 * kg MS/ha ya no son del tipo sino de las curvas de cada potrero (P23). Se
 * administra en Configuración.
 */
class TipoPastura extends Model
{
    use Auditable, HasFactory;

    protected $table = 'tipos_pastura';

    protected $fillable = [
        'nombre',
        'altura_ingreso_cm',
        'altura_salida_cm',
        'activo',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'altura_ingreso_cm' => 'float',
            'altura_salida_cm' => 'float',
            'activo' => 'boolean',
        ];
    }

    // ----- Auditoría (P18): todo, porque cambia los estados del tablero -----

    public static function entidadAuditada(): EntidadAuditada
    {
        return EntidadAuditada::TipoPastura;
    }

    protected function camposAuditados(): array
    {
        return ['nombre', 'altura_ingreso_cm', 'altura_salida_cm', 'activo'];
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

    public function potreros(): HasMany
    {
        return $this->hasMany(Potrero::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Con potreros se desactiva; sin potreros se borra (D20). */
    public function tieneHistoria(): bool
    {
        return $this->potreros()->exists();
    }

    public function tieneAlturas(): bool
    {
        return $this->altura_ingreso_cm !== null && $this->altura_salida_cm !== null;
    }
}
