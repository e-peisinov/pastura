<?php

namespace App\Models;

use App\Enums\Nivel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El nivel de un usuario sobre un módulo (D49). `modulo` es la clave de la
 * lista de App\Support\Modulos.
 */
class Permiso extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'modulo', 'nivel'];

    protected function casts(): array
    {
        return [
            'nivel' => Nivel::class,
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
