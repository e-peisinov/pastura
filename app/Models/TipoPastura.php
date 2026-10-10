<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La pastura de un potrero (glosario, P16): sus alturas de referencia y su
 * fórmula de kg MS/ha. Se administra en Configuración.
 */
class TipoPastura extends Model
{
    use HasFactory;

    protected $table = 'tipos_pastura';

    protected $fillable = [
        'nombre',
        'altura_ingreso_cm',
        'altura_salida_cm',
        'biomasa_a',
        'biomasa_b',
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
            'biomasa_a' => 'float',
            'biomasa_b' => 'float',
            'activo' => 'boolean',
        ];
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

    public function tieneFormula(): bool
    {
        return $this->biomasa_a !== null && $this->biomasa_b !== null;
    }

    /** kg MS/ha = max(0, A × altura + B) con la fórmula vigente (P16). Sin fórmula, null. */
    public function kgMsHa(float $alturaCm): ?float
    {
        if (! $this->tieneFormula()) {
            return null;
        }

        return max(0.0, $this->biomasa_a * $alturaCm + $this->biomasa_b);
    }
}
