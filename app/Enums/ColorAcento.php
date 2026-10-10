<?php

namespace App\Enums;

/**
 * Paletas de acento permitidas para la marca blanca (D8). Las clases de
 * Tailwind de cada una se escriben literales en App\Support\Tema; acá solo
 * viven los valores que acepta configuracion.color_acento.
 */
enum ColorAcento: string
{
    case Slate = 'slate';
    case Zinc = 'zinc';
    case Blue = 'blue';
    case Teal = 'teal';
    case Green = 'green';

    public function etiqueta(): string
    {
        return __('enums.color_acento.'.$this->value);
    }
}
