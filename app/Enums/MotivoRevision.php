<?php

namespace App\Enums;

/**
 * Por qué una recorrida quedó para revisar (P18). No se guarda: se calcula
 * en el tablero de sus puntos activos.
 */
enum MotivoRevision: string
{
    case PocosPuntos = 'pocos_puntos';
    case SinGps = 'sin_gps';
    case FueraDelContorno = 'fuera_del_contorno';

    public function etiqueta(): string
    {
        return __('enums.motivo_revision.'.$this->value);
    }
}
