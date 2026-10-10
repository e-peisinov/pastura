<?php

namespace App\Enums;

/**
 * Nivel de permiso de un usuario sobre un módulo (D49): sin acceso (no está
 * en el sidebar y la ruta bloquea), ver (entra y mira, ninguna acción de
 * escritura) o editar (acceso completo).
 */
enum Nivel: string
{
    case SinAcceso = 'sin_acceso';
    case Ver = 'ver';
    case Editar = 'editar';

    public function etiqueta(): string
    {
        return __('enums.nivel.'.$this->value);
    }

    public function puedeVer(): bool
    {
        return $this !== self::SinAcceso;
    }

    public function puedeEditar(): bool
    {
        return $this === self::Editar;
    }
}
