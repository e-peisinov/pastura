<?php

namespace App\Enums;

use App\Models\TipoPastura;

/**
 * Cómo está un potrero según la altura media de su última recorrida y las
 * alturas de referencia de su tipo de pastura (glosario, P18). No se guarda:
 * se calcula en el tablero.
 */
enum EstadoPotrero: string
{
    case Listo = 'listo';
    case EnCrecimiento = 'en_crecimiento';
    case BajoRemanente = 'bajo_remanente';
    case SinAlturas = 'sin_alturas';
    case NuncaMedido = 'nunca_medido';

    /**
     * Listo desde la altura de ingreso; bajo el remanente por debajo de la
     * de salida (la de salida justa todavía no lo es); en crecimiento entre
     * las dos.
     */
    public static function de(?float $alturaCm, ?TipoPastura $tipo): self
    {
        if ($alturaCm === null) {
            return self::NuncaMedido;
        }

        if ($tipo === null || ! $tipo->tieneAlturas()) {
            return self::SinAlturas;
        }

        return match (true) {
            $alturaCm >= $tipo->altura_ingreso_cm => self::Listo,
            $alturaCm < $tipo->altura_salida_cm => self::BajoRemanente,
            default => self::EnCrecimiento,
        };
    }

    public function etiqueta(): string
    {
        return __('enums.estado_potrero.'.$this->value);
    }

    /** Color de <x-ui.badge> y <x-ui.indicador>. */
    public function color(): string
    {
        return match ($this) {
            self::Listo => 'verde',
            self::EnCrecimiento => 'azul',
            self::BajoRemanente => 'ambar',
            self::SinAlturas, self::NuncaMedido => 'gris',
        };
    }
}
