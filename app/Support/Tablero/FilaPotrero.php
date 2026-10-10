<?php

namespace App\Support\Tablero;

use App\Enums\EstadoPotrero;
use App\Models\Potrero;
use App\Models\Recorrida;

/**
 * Un potrero en el tablero: su última recorrida activa con puntos (con el
 * resumen de conResumen()), hace cuántos días fue, su estado de altura y si
 * quedó sin medir (P18).
 */
final readonly class FilaPotrero
{
    public function __construct(
        public Potrero $potrero,
        public ?Recorrida $ultima,
        public ?int $dias,
        public EstadoPotrero $estado,
        public bool $sinMedir,
    ) {}

    public function alturaCm(): ?float
    {
        return $this->ultima?->altura_media;
    }

    public function kgMsHa(): ?float
    {
        return $this->ultima?->kg_ms_ha_medio;
    }
}
