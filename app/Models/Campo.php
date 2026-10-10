<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * El establecimiento (glosario): un conjunto de potreros.
 */
class Campo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'localidad',
        'observaciones',
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

    public function potreros(): HasMany
    {
        return $this->hasMany(Potrero::class);
    }

    public function recorridas(): HasManyThrough
    {
        return $this->hasManyThrough(Recorrida::class, Potrero::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Con historia se desactiva; sin historia se borra con sus potreros (D20). */
    public function tieneHistoria(): bool
    {
        return $this->recorridas()->exists();
    }

    /** Suma de la superficie de los potreros activos con contorno, en hectáreas (regla 8). */
    public function superficieHa(): ?float
    {
        $superficies = $this->potreros
            ->where('activo', true)
            ->map(fn (Potrero $potrero) => $potrero->superficieHa())
            ->filter(fn (?float $ha) => $ha !== null);

        return $superficies->isEmpty() ? null : (float) $superficies->sum();
    }
}
