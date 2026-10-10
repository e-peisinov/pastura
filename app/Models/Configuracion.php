<?php

namespace App\Models;

use App\Enums\ColorAcento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila única con la identidad de la organización (marca blanca, D8) y los
 * parámetros del seguimiento (P17). Se edita en /configuracion.
 */
class Configuracion extends Model
{
    use HasFactory;

    /** Valores con los que arranca una instalación (P17). */
    public const DIAS_SIN_MEDIR = 7;

    public const PUNTOS_MINIMOS = 20;

    protected $table = 'configuracion';

    protected $fillable = [
        'nombre',
        'logo_path',
        'color_acento',
        'dias_sin_medir',
        'puntos_minimos',
    ];

    protected function casts(): array
    {
        return [
            'color_acento' => ColorAcento::class,
            'dias_sin_medir' => 'integer',
            'puntos_minimos' => 'integer',
        ];
    }

    /**
     * La única fila. Si todavía no existe (base recién migrada), devuelve una
     * instancia con valores por defecto sin persistirla.
     */
    public static function instancia(): self
    {
        return static::query()->first() ?? new static([
            'nombre' => 'Pasturómetro',
            'color_acento' => ColorAcento::Green,
            'dias_sin_medir' => self::DIAS_SIN_MEDIR,
            'puntos_minimos' => self::PUNTOS_MINIMOS,
        ]);
    }
}
