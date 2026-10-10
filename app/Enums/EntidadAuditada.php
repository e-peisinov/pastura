<?php

namespace App\Enums;

/**
 * Qué se audita (P18): las tablas cuyas acciones quedan en `auditoria`.
 * Sumar una es sumar el caso acá, el trait Auditable en el modelo, sus
 * frases en lang/es/auditoria.php y, si hace falta, su enlace.
 */
enum EntidadAuditada: string
{
    case Recorrida = 'recorrida';
    case Medicion = 'medicion';
    case Potrero = 'potrero';
    case Campo = 'campo';
    case Usuario = 'usuario';
    case TipoPastura = 'tipo_pastura';
    case Configuracion = 'configuracion';

    public function etiqueta(): string
    {
        return __('enums.entidad_auditada.'.$this->value);
    }

    /** Icono del listado. */
    public function icono(): string
    {
        return match ($this) {
            self::Recorrida => 'fa-route',
            self::Medicion => 'fa-location-dot',
            self::Potrero => 'fa-draw-polygon',
            self::Campo => 'fa-map-location-dot',
            self::Usuario => 'fa-user-gear',
            self::TipoPastura => 'fa-wheat-awn',
            self::Configuracion => 'fa-sliders',
        };
    }

    /** El módulo que hay que poder ver para seguir el enlace de la fila. */
    public function modulo(): string
    {
        return match ($this) {
            self::Recorrida, self::Medicion => 'recorridas',
            self::Potrero, self::Campo => 'campos',
            self::Usuario => 'usuarios',
            self::TipoPastura, self::Configuracion => 'configuracion',
        };
    }
}
