<?php

namespace App\Models;

use App\Support\Biomasa\RectaBiomasa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una muestra de una curva de biomasa (glosario, P23): la altura del disco
 * y el peso seco (estufa, peso constante) del pasto cortado justo debajo.
 */
class MuestraCurva extends Model
{
    protected $table = 'muestras_curva';

    protected $fillable = [
        'curva_biomasa_id',
        'altura_cm',
        'peso_seco_g',
    ];

    protected function casts(): array
    {
        return [
            'altura_cm' => 'float',
            'peso_seco_g' => 'float',
        ];
    }

    public function curva(): BelongsTo
    {
        return $this->belongsTo(CurvaBiomasa::class, 'curva_biomasa_id');
    }

    /** Los kg MS/ha que cortó, con el área del disco. */
    public function kgMsHa(float $areaDiscoM2): float
    {
        return RectaBiomasa::kgMsHaDeMuestra($this->peso_seco_g, $areaDiscoM2);
    }
}
