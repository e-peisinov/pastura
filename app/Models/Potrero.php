<?php

namespace App\Models;

use App\Enums\EntidadAuditada;
use App\Models\Traits\Auditable;
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
    use Auditable, HasFactory;

    protected $fillable = [
        'campo_id',
        'nombre',
        'tipo_pastura_id',
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

    // ----- Auditoría (P18): contorno, tipo de pastura, baja ------------------

    public static function entidadAuditada(): EntidadAuditada
    {
        return EntidadAuditada::Potrero;
    }

    /** El tipo cambia los kg MS/ha (P16) y el contorno, la superficie. */
    protected function camposAuditados(): array
    {
        return ['tipo_pastura_id', 'contorno', 'activo'];
    }

    protected function auditaBaja(): bool
    {
        return true;
    }

    /** El tipo por su nombre; el contorno por su superficie (la geometría no se lee). */
    protected function valorParaAuditoria(string $campo, mixed $valor): mixed
    {
        return match ($campo) {
            'tipo_pastura_id' => TipoPastura::query()->find($valor)?->nombre,
            'contorno' => ($ha = Superficie::hectareas(is_string($valor) ? json_decode($valor, true) : $valor)) === null ? null : round($ha, 2),
            default => $this->valorAuditableComun($valor),
        };
    }

    /** @return array<string, mixed> */
    protected function datosAuditoria(): array
    {
        return [
            'potrero' => Campo::query()->whereKey($this->campo_id)->value('nombre').' · '.$this->nombre,
            'campo_id' => $this->campo_id,
        ];
    }

    public function campo(): BelongsTo
    {
        return $this->belongsTo(Campo::class);
    }

    public function tipoPastura(): BelongsTo
    {
        return $this->belongsTo(TipoPastura::class);
    }

    public function recorridas(): HasMany
    {
        return $this->hasMany(Recorrida::class);
    }

    /** Sus curvas de biomasa, una vigente por estado fenológico (P23). */
    public function curvas(): HasMany
    {
        return $this->hasMany(CurvaBiomasa::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Con recorridas o con curvas se desactiva; sin nada se borra. */
    public function tieneHistoria(): bool
    {
        return $this->recorridas()->exists() || $this->curvas()->exists();
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
