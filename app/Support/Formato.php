<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Formatos de cara al usuario (docs/convenciones.md, "Localización"):
 * moneda `$ 1.234,56`, fechas `dd/mm/aaaa`, kilómetros `47.000`.
 */
final class Formato
{
    public static function moneda(float|int|string|null $monto): string
    {
        return '$ '.number_format((float) $monto, 2, ',', '.');
    }

    public static function numero(float|int|string|null $valor, int $decimales = 0): string
    {
        return number_format((float) $valor, $decimales, ',', '.');
    }

    /** Stock y cantidades: sin decimales si es entero, hasta 2 si no. */
    public static function cantidad(float|int|string|null $valor): string
    {
        $valor = (float) $valor;

        return self::numero($valor, floor($valor) == $valor ? 0 : 2);
    }

    public static function fecha(CarbonInterface|string|null $fecha): string
    {
        if ($fecha === null) {
            return '';
        }

        return Carbon::parse($fecha)->format('d/m/Y');
    }

    /**
     * Antigüedad en palabras a partir de meses enteros: "menos de un mes",
     * "7 meses", "4 años", "3 años y 2 meses". Para el seguimiento de
     * neumáticos (D18): la antigüedad es el eje confiable y se lee mejor así
     * que como "40 meses".
     */
    public static function antiguedad(int $meses): string
    {
        $meses = max(0, $meses);
        $anios = intdiv($meses, 12);
        $resto = $meses % 12;

        if ($anios === 0) {
            return $resto === 0
                ? __('ui.tiempo.menos_de_un_mes')
                : trans_choice('ui.tiempo.meses', $resto, ['n' => $resto]);
        }

        $texto = trans_choice('ui.tiempo.anios', $anios, ['n' => $anios]);

        if ($resto > 0) {
            $texto .= ' '.__('ui.tiempo.y').' '.trans_choice('ui.tiempo.meses', $resto, ['n' => $resto]);
        }

        return $texto;
    }

    /** Hora `HH:MM` a partir de un `time` de MySQL (`HH:MM:SS`) o de un Carbon. */
    public static function hora(CarbonInterface|string|null $hora): string
    {
        if ($hora === null || $hora === '') {
            return '';
        }

        if ($hora instanceof CarbonInterface) {
            return $hora->format('H:i');
        }

        return substr($hora, 0, 5);
    }

    /**
     * Una duración en palabras cortas, con dos unidades como mucho: "45 min",
     * "2 h 15 min", "3 d 4 h". Menos de un minuto es "menos de 1 min". Para
     * los tiempos de la orden (D60): se lee de un vistazo en el historial.
     */
    public static function duracion(int $segundos): string
    {
        $minutos = intdiv(max(0, $segundos), 60);

        if ($minutos < 1) {
            return __('ui.tiempo.menos_de_un_minuto');
        }

        $dias = intdiv($minutos, 1440);
        $horas = intdiv($minutos % 1440, 60);
        $minutos %= 60;

        if ($dias > 0) {
            return trim(__('ui.tiempo.dias', ['n' => $dias]).($horas > 0 ? ' '.__('ui.tiempo.horas', ['n' => $horas]) : ''));
        }

        if ($horas > 0) {
            return trim(__('ui.tiempo.horas', ['n' => $horas]).($minutos > 0 ? ' '.__('ui.tiempo.minutos', ['n' => $minutos]) : ''));
        }

        return __('ui.tiempo.minutos', ['n' => $minutos]);
    }
}
