<?php

namespace App\Enums;

/**
 * Qué se hizo (P18, D64 del kit): una fila de `auditoria` por acción.
 */
enum AccionAuditada: string
{
    case Creado = 'creado';
    case Modificado = 'modificado';
    case Eliminado = 'eliminado';

    public function etiqueta(): string
    {
        return __('enums.accion_auditada.'.$this->value);
    }

    /** Color semántico del badge (estandares-ux.md §6). */
    public function color(): string
    {
        return match ($this) {
            self::Creado => 'verde',
            self::Modificado => 'ambar',
            self::Eliminado => 'rojo',
        };
    }
}
