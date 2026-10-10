<?php

namespace App\Models\Traits;

use App\Enums\AccionAuditada;
use App\Enums\EntidadAuditada;
use App\Models\Auditoria;

/**
 * Deja en `auditoria` (P18; D64 del kit) lo que pasa con el modelo, desde sus
 * eventos, así ninguna pantalla puede olvidarse. El modelo dice qué entidad
 * es (entidadAuditada), qué campos vigilar en una modificación
 * (camposAuditados: si ninguno cambió, no se registra nada), si el alta y la
 * baja también se auditan (auditaAlta, auditaBaja), con qué foto
 * (datosAuditoria) y, si hace falta, cómo se escribe un valor en la foto
 * (valorParaAuditoria: un id por su nombre, un contorno por su superficie).
 */
trait Auditable
{
    abstract public static function entidadAuditada(): EntidadAuditada;

    /** @return array<string, mixed> */
    abstract protected function datosAuditoria(): array;

    /** @return list<string> */
    protected function camposAuditados(): array
    {
        return [];
    }

    protected function auditaAlta(): bool
    {
        return false;
    }

    protected function auditaBaja(): bool
    {
        return false;
    }

    protected static function bootAuditable(): void
    {
        static::created(function (self $modelo) {
            if ($modelo->auditaAlta()) {
                $modelo->auditar(AccionAuditada::Creado);
            }
        });

        static::updated(function (self $modelo) {
            $cambios = [];

            foreach ($modelo->camposAuditados() as $campo) {
                if ($modelo->wasChanged($campo)) {
                    $cambios[$campo] = [
                        $modelo->valorParaAuditoria($campo, $modelo->valorOriginal($campo)),
                        $modelo->valorParaAuditoria($campo, $modelo->getAttribute($campo)),
                    ];
                }
            }

            $modelo->auditarCambios($cambios);
        });

        static::deleted(function (self $modelo) {
            if ($modelo->auditaBaja()) {
                $modelo->auditar(AccionAuditada::Eliminado);
            }
        });
    }

    /** @param  array<string, mixed>  $extra */
    public function auditar(AccionAuditada $accion, array $extra = []): Auditoria
    {
        return Auditoria::registrar($this, static::entidadAuditada(), $accion, $this->datosAuditoria() + $extra);
    }

    /**
     * Una modificación con sus cambios `{campo: [antes, después]}`; sin
     * cambios no deja nada. También la usan los cambios que no son columnas
     * del modelo (los permisos de un usuario).
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $cambios
     */
    public function auditarCambios(array $cambios): void
    {
        if ($cambios !== []) {
            $this->auditar(AccionAuditada::Modificado, ['cambios' => $cambios]);
        }
    }

    /** Cómo va un valor a la foto; el modelo lo sobrescribe para un campo propio y usa valorAuditableComun para el resto. */
    protected function valorParaAuditoria(string $campo, mixed $valor): mixed
    {
        return $this->valorAuditableComun($valor);
    }

    /** Un enum va por su etiqueta, una fecha por su día; el resto tal cual, para que la foto se lea en JSON. */
    protected function valorAuditableComun(mixed $valor): mixed
    {
        if ($valor instanceof \BackedEnum) {
            return method_exists($valor, 'etiqueta') ? $valor->etiqueta() : $valor->value;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        return $valor;
    }

    /** El valor de antes, con el cast del campo (el original puede venir crudo según cómo se cargó). */
    private function valorOriginal(string $campo): mixed
    {
        $valor = $this->getOriginal($campo);
        $cast = $this->getCasts()[$campo] ?? null;

        if (is_string($valor) && is_string($cast) && enum_exists($cast)) {
            return $cast::tryFrom($valor) ?? $valor;
        }

        return $valor;
    }
}
