<?php

namespace App\Models;

use App\Enums\ColorAcento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila única con la identidad de la organización (marca blanca, D8).
 * Por ahora sin pantalla: se carga por seeder.
 */
class Configuracion extends Model
{
    use HasFactory;

    protected $table = 'configuracion';

    protected $fillable = [
        'nombre',
        'logo_path',
        'color_acento',
    ];

    protected function casts(): array
    {
        return [
            'color_acento' => ColorAcento::class,
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
        ]);
    }
}
