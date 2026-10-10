<?php

namespace App\Enums;

enum Rol: string
{
    case Admin = 'admin';
    case Recorredor = 'recorredor';

    public function etiqueta(): string
    {
        return __('enums.rol.'.$this->value);
    }
}
