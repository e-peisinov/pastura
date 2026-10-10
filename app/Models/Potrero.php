<?php

namespace App\Models;

use App\Support\Geo\Superficie;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una parcela del campo con su contorno en el mapa (glosario). El contorno es
 * una geometría GeoJSON; la superficie se calcula de ahí (P5).
 */
class Potrero extends Model
{
    use HasFactory;

    protected $fillable = [
        'campo_id',
        'nombre',
        'tipo_pastura',
        'contorno',
        'observaciones',
        'activo',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'contorno' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function campo(): BelongsTo
    {
        return $this->belongsTo(Campo::class);
    }

    public function recorridas(): HasMany
    {
        return $this->hasMany(Recorrida::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function tieneHistoria(): bool
    {
        return $this->recorridas()->exists();
    }

    public function superficieHa(): ?float
    {
        return Superficie::hectareas($this->contorno);
    }

    /** La recorrida más reciente que no está anulada. */
    public function ultimaRecorrida(): ?Recorrida
    {
        return $this->recorridas()
            ->where('activo', true)
            ->orderByDesc('iniciada_en')
            ->orderByDesc('id')
            ->first();
    }

    /** "Campo · Potrero", para combos y títulos. */
    public function nombreCompleto(): string
    {
        return $this->campo->nombre.' · '.$this->nombre;
    }
}
