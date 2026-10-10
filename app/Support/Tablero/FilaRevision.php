<?php

namespace App\Support\Tablero;

use App\Enums\MotivoRevision;
use App\Models\Recorrida;

/** Una recorrida para revisar y por qué (P18), en el orden de MotivoRevision. */
final readonly class FilaRevision
{
    /** @param  list<MotivoRevision>  $motivos */
    public function __construct(
        public Recorrida $recorrida,
        public array $motivos,
    ) {}

    public function tiene(MotivoRevision $motivo): bool
    {
        return in_array($motivo, $this->motivos, true);
    }
}
