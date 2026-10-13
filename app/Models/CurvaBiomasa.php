<?php

namespace App\Models;

use App\Enums\EntidadAuditada;
use App\Models\Traits\Auditable;
use App\Support\Biomasa\RectaBiomasa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Curva de biomasa (glosario, P23): las muestras cortadas en un potrero en
 * un estado fenológico y la recta que sale de ellas. La recta se calcula,
 * no se guarda (regla 8). Hay una sola vigente (`activo`) por potrero y
 * estado: guardar o reactivar otra deja reemplazada a la que estaba.
 */
class CurvaBiomasa extends Model
{
    use Auditable, HasFactory;

    /** Lo mínimo para guardar una curva (y que siempre tenga recta). */
    public const MUESTRAS_MINIMAS = 3;

    protected $table = 'curvas_biomasa';

    protected $fillable = [
        'potrero_id',
        'estado_fenologico_id',
        'fecha',
        'observaciones',
        'activo',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Una sola vigente por potrero y estado: la que se guarda vigente
        // reemplaza a las otras. De a una, para que la auditoría las registre.
        static::saved(function (self $curva) {
            if (! $curva->activo || ! ($curva->wasRecentlyCreated || $curva->wasChanged(['activo', 'potrero_id', 'estado_fenologico_id']))) {
                return;
            }

            static::query()
                ->where('potrero_id', $curva->potrero_id)
                ->where('estado_fenologico_id', $curva->estado_fenologico_id)
                ->where('activo', true)
                ->whereKeyNot($curva->id)
                ->get()
                ->each(fn (self $otra) => $otra->update(['activo' => false]));
        });
    }

    // ----- Auditoría (P18): todo, porque cambia los kg ------------------------

    public static function entidadAuditada(): EntidadAuditada
    {
        return EntidadAuditada::CurvaBiomasa;
    }

    protected function camposAuditados(): array
    {
        return ['potrero_id', 'estado_fenologico_id', 'fecha', 'activo'];
    }

    protected function auditaAlta(): bool
    {
        return true;
    }

    protected function auditaBaja(): bool
    {
        return true;
    }

    /** El potrero y el estado por su nombre; la fecha como se lee. */
    protected function valorParaAuditoria(string $campo, mixed $valor): mixed
    {
        return match ($campo) {
            'potrero_id' => Potrero::query()->with('campo')->find($valor)?->nombreCompleto(),
            'estado_fenologico_id' => EstadoFenologico::query()->find($valor)?->nombre,
            'fecha' => $valor === null ? null : Carbon::parse($valor)->format('d/m/Y'),
            default => $this->valorAuditableComun($valor),
        };
    }

    /** @return array<string, mixed> */
    protected function datosAuditoria(): array
    {
        return [
            'potrero' => Potrero::query()->with('campo')->find($this->potrero_id)?->nombreCompleto(),
            'potrero_id' => $this->potrero_id,
            'nombre' => EstadoFenologico::query()->whereKey($this->estado_fenologico_id)->value('nombre'),
            'fecha' => $this->fecha?->format('Y-m-d'),
        ];
    }

    // ----- Relaciones ---------------------------------------------------------

    public function potrero(): BelongsTo
    {
        return $this->belongsTo(Potrero::class);
    }

    public function estadoFenologico(): BelongsTo
    {
        return $this->belongsTo(EstadoFenologico::class);
    }

    public function muestras(): HasMany
    {
        return $this->hasMany(MuestraCurva::class)->orderBy('altura_cm')->orderBy('id');
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** La vigente de un potrero para un estado, o null. */
    public static function vigentePara(?int $potreroId, ?int $estadoId): ?self
    {
        if ($potreroId === null || $estadoId === null) {
            return null;
        }

        return static::query()
            ->where('potrero_id', $potreroId)
            ->where('estado_fenologico_id', $estadoId)
            ->vigentes()
            ->latest('id')
            ->first();
    }

    // ----- La recta (regla 8) -------------------------------------------------

    /**
     * Las rectas de todas las vigentes, de una vez (para exportar sin una
     * consulta por recorrida): "potrero-estado" => recta.
     *
     * @return array<string, RectaBiomasa>
     */
    public static function rectasVigentes(): array
    {
        $area = Configuracion::instancia()->area_disco_m2;
        $rectas = [];

        foreach (static::query()->vigentes()->with('muestras')->get() as $curva) {
            if (($recta = $curva->recta($area)) !== null) {
                $rectas[$curva->potrero_id.'-'.$curva->estado_fenologico_id] = $recta;
            }
        }

        return $rectas;
    }

    /** La recta de sus muestras con el área del disco de Configuración (o la que se pase). */
    public function recta(?float $areaDiscoM2 = null): ?RectaBiomasa
    {
        $area = $areaDiscoM2 ?? Configuracion::instancia()->area_disco_m2;

        return RectaBiomasa::ajustar($this->muestras->map(
            fn (MuestraCurva $muestra) => [$muestra->altura_cm, $muestra->kgMsHa($area)],
        ));
    }
}
