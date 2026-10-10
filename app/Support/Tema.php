<?php

namespace App\Support;

use App\Enums\ColorAcento;
use App\Models\Configuracion;
use Illuminate\Database\QueryException;

/**
 * Clases de acento del tema (estandares-ux.md §6, D7, D8).
 *
 * Ninguna vista escribe un color de acento a mano: lo pide acá. La paleta
 * activa la dice `configuracion.color_acento`. Las clases están escritas
 * LITERALES en cada paleta y nunca por interpolación: Tailwind arma el CSS
 * escaneando los archivos (tailwind.config.js incluye ./app/**\/*.php) y una
 * clase construida en tiempo de ejecución jamás llega al build.
 */
final class Tema
{
    private static ?ColorAcento $forzado = null;

    private static ?ColorAcento $resuelto = null;

    /**
     * Las paletas permitidas, con cada clase escrita literal.
     *
     * @var array<string, array<string, string>>
     */
    private const PALETAS = [
        'slate' => [
            'gradienteHeader' => 'from-slate-700 to-slate-800',
            'botonPrimario' => 'bg-slate-700 hover:bg-slate-800 text-white',
            'acentoTexto' => 'text-slate-700',
            'acentoFondo' => 'bg-slate-700 text-white',
            'acentoFondoSuave' => 'bg-slate-100 text-slate-800',
            'acentoBorde' => 'border-slate-700',
            'anilloFoco' => 'focus:ring-slate-500 focus:border-slate-500',
            'enlace' => 'text-slate-700 hover:text-slate-900',
            'opcionElegida' => 'peer-checked:bg-slate-700 peer-checked:text-white peer-focus-visible:ring-slate-500',
        ],
        'zinc' => [
            'gradienteHeader' => 'from-zinc-700 to-zinc-800',
            'botonPrimario' => 'bg-zinc-700 hover:bg-zinc-800 text-white',
            'acentoTexto' => 'text-zinc-700',
            'acentoFondo' => 'bg-zinc-700 text-white',
            'acentoFondoSuave' => 'bg-zinc-100 text-zinc-800',
            'acentoBorde' => 'border-zinc-700',
            'anilloFoco' => 'focus:ring-zinc-500 focus:border-zinc-500',
            'enlace' => 'text-zinc-700 hover:text-zinc-900',
            'opcionElegida' => 'peer-checked:bg-zinc-700 peer-checked:text-white peer-focus-visible:ring-zinc-500',
        ],
        'blue' => [
            'gradienteHeader' => 'from-blue-700 to-blue-800',
            'botonPrimario' => 'bg-blue-700 hover:bg-blue-800 text-white',
            'acentoTexto' => 'text-blue-700',
            'acentoFondo' => 'bg-blue-700 text-white',
            'acentoFondoSuave' => 'bg-blue-100 text-blue-800',
            'acentoBorde' => 'border-blue-700',
            'anilloFoco' => 'focus:ring-blue-500 focus:border-blue-500',
            'enlace' => 'text-blue-700 hover:text-blue-900',
            'opcionElegida' => 'peer-checked:bg-blue-700 peer-checked:text-white peer-focus-visible:ring-blue-500',
        ],
        'green' => [
            'gradienteHeader' => 'from-green-800 to-green-900',
            'botonPrimario' => 'bg-green-800 hover:bg-green-900 text-white',
            'acentoTexto' => 'text-green-800',
            'acentoFondo' => 'bg-green-800 text-white',
            'acentoFondoSuave' => 'bg-green-100 text-green-900',
            'acentoBorde' => 'border-green-800',
            'anilloFoco' => 'focus:ring-green-600 focus:border-green-600',
            'enlace' => 'text-green-800 hover:text-green-950',
            'opcionElegida' => 'peer-checked:bg-green-800 peer-checked:text-white peer-focus-visible:ring-green-600',
        ],
        'teal' => [
            'gradienteHeader' => 'from-teal-700 to-teal-800',
            'botonPrimario' => 'bg-teal-700 hover:bg-teal-800 text-white',
            'acentoTexto' => 'text-teal-700',
            'acentoFondo' => 'bg-teal-700 text-white',
            'acentoFondoSuave' => 'bg-teal-100 text-teal-800',
            'acentoBorde' => 'border-teal-700',
            'anilloFoco' => 'focus:ring-teal-500 focus:border-teal-500',
            'enlace' => 'text-teal-700 hover:text-teal-900',
            'opcionElegida' => 'peer-checked:bg-teal-700 peer-checked:text-white peer-focus-visible:ring-teal-500',
        ],
    ];

    /**
     * Muestra de color de cada paleta (el círculo del selector de
     * /configuracion, D48). Literal por paleta, como todo lo demás (§7).
     *
     * @var array<string, string>
     */
    private const MUESTRAS = [
        'slate' => 'bg-slate-700',
        'zinc' => 'bg-zinc-700',
        'blue' => 'bg-blue-700',
        'teal' => 'bg-teal-700',
        'green' => 'bg-green-800',
    ];

    /** Gradiente del header de modal: `bg-gradient-to-r` + esto. */
    public static function gradienteHeader(): string
    {
        return self::clase('gradienteHeader');
    }

    /** Botón primario (guardar, confirmar, nuevo). */
    public static function botonPrimario(): string
    {
        return self::clase('botonPrimario');
    }

    /** Texto con el color de acento (títulos de sección, iconos). */
    public static function acentoTexto(): string
    {
        return self::clase('acentoTexto');
    }

    /** Fondo pleno de acento con texto blanco (página activa de la paginación). */
    public static function acentoFondo(): string
    {
        return self::clase('acentoFondo');
    }

    /** Fondo suave de acento (opción resaltada del combobox, chips). */
    public static function acentoFondoSuave(): string
    {
        return self::clase('acentoFondoSuave');
    }

    /** Borde con el color de acento. */
    public static function acentoBorde(): string
    {
        return self::clase('acentoBorde');
    }

    /** Anillo de foco para inputs y combobox. */
    public static function anilloFoco(): string
    {
        return self::clase('anilloFoco');
    }

    /** Enlaces de texto. */
    public static function enlace(): string
    {
        return self::clase('enlace');
    }

    /** La opción elegida de <x-ui.opciones>: clases `peer-checked:` del radio oculto. */
    public static function opcionElegida(): string
    {
        return self::clase('opcionElegida');
    }

    /** Paleta activa según `configuracion.color_acento`. */
    public static function paleta(): ColorAcento
    {
        if (self::$forzado !== null) {
            return self::$forzado;
        }

        return self::$resuelto ??= self::leerDeConfiguracion();
    }

    /**
     * Fija la paleta sin pasar por la base (tests, previsualización en
     * /configuracion antes de guardar). `null` vuelve a leer de la base.
     */
    public static function forzar(?ColorAcento $paleta): void
    {
        self::$forzado = $paleta;
        self::$resuelto = null;
    }

    /** Olvida la paleta cacheada; la próxima llamada vuelve a leer la base. */
    public static function reiniciar(): void
    {
        self::$forzado = null;
        self::$resuelto = null;
    }

    /** Fondo pleno de una paleta dada, sin cambiar la activa: para elegirla. */
    public static function muestra(ColorAcento $paleta): string
    {
        return self::MUESTRAS[$paleta->value];
    }

    /** @return list<ColorAcento> */
    public static function paletasDisponibles(): array
    {
        return ColorAcento::cases();
    }

    private static function clase(string $rol): string
    {
        return self::PALETAS[self::paleta()->value][$rol];
    }

    private static function leerDeConfiguracion(): ColorAcento
    {
        try {
            return Configuracion::instancia()->color_acento ?? ColorAcento::Green;
        } catch (QueryException) {
            // Base sin migrar (instalación recién clonada): se arranca con la
            // paleta por defecto en vez de tirar la página abajo.
            return ColorAcento::Green;
        }
    }
}
