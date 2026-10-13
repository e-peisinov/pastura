<?php

namespace App\Models;

use App\Enums\AccionAuditada;
use App\Enums\EntidadAuditada;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Una línea de la auditoría (P18; D64 del kit): quién hizo qué y cuándo. La
 * escribe el trait Auditable desde los eventos de cada modelo, con una foto
 * (`datos`) que alcanza para leerla aunque la entidad ya no exista. Sin
 * `updated_at`. No es un diff genérico: una fila por acción que se lee en
 * una frase.
 */
class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auditoria';

    protected $fillable = [
        'user_id',
        'entidad',
        'entidad_id',
        'accion',
        'datos',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'entidad' => EntidadAuditada::class,
            'accion' => AccionAuditada::class,
            'datos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Escribe la fila con el usuario en sesión (en la API, el dueño del
     * token); sin sesión (seeder, comando) queda quien figura en el modelo
     * como `user_id`, y si no, el sistema.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function registrar(Model $modelo, EntidadAuditada $entidad, AccionAuditada $accion, array $datos): self
    {
        return static::query()->create([
            'user_id' => Auth::id() ?? ($modelo->getAttribute('user_id') ?: null),
            'entidad' => $entidad,
            'entidad_id' => $modelo->getKey(),
            'accion' => $accion,
            'datos' => $datos,
            'created_at' => now(),
        ]);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeEntre(Builder $query, \DateTimeInterface $desde, \DateTimeInterface $hasta): Builder
    {
        return $query->whereBetween('created_at', [$desde, $hasta]);
    }

    /** Quién lo hizo: el nombre o "Sistema". */
    public function nombreUsuario(): string
    {
        return $this->usuario?->name ?? __('auditoria.sistema');
    }

    /**
     * El texto que se lee: "Modificó la recorrida de La Esperanza · Lote 4
     * del 05/10/2026 (Juan Pérez): estado de válida a anulada.". Sale de la
     * foto, no de la entidad.
     */
    public function descripcion(): string
    {
        $datos = $this->datos ?? [];

        return __('auditoria.descripciones.'.$this->entidad->value.'.'.$this->accion->value, [
            'potrero' => $datos['potrero'] ?? '',
            'fecha' => Formato::fecha($datos['fecha'] ?? null),
            'recorredor' => $datos['recorredor'] ?? '',
            'hora' => $datos['hora'] ?? '',
            'altura' => __('recorridas.cm', ['valor' => Formato::numero($datos['altura_cm'] ?? 0, 1)]),
            'nombre' => $datos['nombre'] ?? '',
            'email' => $datos['email'] ?? '',
            'cambios' => $this->textoCambios($datos['cambios'] ?? []),
        ]);
    }

    /**
     * "estado de válida a anulada, potrero de Lote 4 a El Bajo".
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $cambios
     */
    private function textoCambios(array $cambios): string
    {
        $partes = [];

        foreach ($cambios as $campo => [$antes, $despues]) {
            // La contraseña nunca se guarda: se dice que cambió y nada más.
            if ($campo === 'password') {
                $partes[] = __('auditoria.contrasena_nueva');

                continue;
            }

            $partes[] = __('auditoria.cambio', [
                'campo' => $this->nombreCampo($campo),
                'antes' => $this->formatear($campo, $antes),
                'despues' => $this->formatear($campo, $despues),
            ]);
        }

        return implode(', ', $partes);
    }

    /** "estado", "superficie", "permiso en Campos". */
    private function nombreCampo(string $campo): string
    {
        if (str_starts_with($campo, 'permiso.')) {
            return __('auditoria.permiso', ['modulo' => __('layout.modulos.'.substr($campo, 8))]);
        }

        return __('auditoria.campos.'.$campo);
    }

    private function formatear(string $campo, mixed $valor): string
    {
        if ($campo === 'activo') {
            $clave = 'auditoria.estados.'.$this->entidad->value;

            return __((trans()->has($clave) ? $clave : 'auditoria.estados.otro').'.'.($valor ? 'si' : 'no'));
        }

        if ($campo === 'contorno') {
            return $valor === null
                ? __('auditoria.sin_contorno')
                : __('auditoria.hectareas', ['valor' => Formato::numero($valor, 1)]);
        }

        if ($valor === null || $valor === '') {
            return '—';
        }

        if (is_int($valor) || is_float($valor)) {
            // Hasta 3 decimales, sin ceros de más ni punto de miles: 22,5 / -100.
            return rtrim(rtrim(number_format($valor, 3, ',', ''), '0'), ',');
        }

        return (string) $valor;
    }

    /**
     * A dónde lleva la fila, si el usuario puede ver ese módulo: el listado
     * de recorridas filtrado por el potrero y el día, la pantalla de campos,
     * la de usuarios o la de configuración. Null si no hay a dónde ir.
     */
    public function enlace(): ?string
    {
        if (! Gate::allows('puedeVer', $this->entidad->modulo())) {
            return null;
        }

        $datos = $this->datos ?? [];

        return match ($this->entidad) {
            EntidadAuditada::Recorrida, EntidadAuditada::Medicion => isset($datos['potrero_id'], $datos['fecha'])
                && Potrero::query()->whereKey($datos['potrero_id'])->exists()
                ? route('recorridas', ['potrero' => $datos['potrero_id'], 'desde' => $datos['fecha'], 'hasta' => $datos['fecha'], 'estado' => 'todas'])
                : null,
            EntidadAuditada::Potrero, EntidadAuditada::Campo => route('campos'),
            EntidadAuditada::Usuario => route('admin.usuarios'),
            EntidadAuditada::TipoPastura, EntidadAuditada::Configuracion, EntidadAuditada::EstadoFenologico => route('configuracion'),
            EntidadAuditada::CurvaBiomasa => isset($datos['potrero_id']) && Potrero::query()->whereKey($datos['potrero_id'])->exists()
                ? route('curvas', ['potrero' => $datos['potrero_id'], 'vigencia' => 'todas'])
                : route('curvas'),
        };
    }
}
