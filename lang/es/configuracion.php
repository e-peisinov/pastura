<?php

/*
 * Textos de la pantalla de Configuración (P17): la organización (marca
 * blanca, D8), los tipos de pastura con sus alturas y su fórmula (P16) y los
 * parámetros del seguimiento.
 */
return [
    'titulo' => 'Configuración',

    'organizacion' => [
        'titulo' => 'Datos de la organización',
        'intro' => 'Nombre, logo y color con los que se ve el sistema. Es lo que aparece en el menú y en la pantalla de ingreso.',
        'modificar' => 'Modificar datos de la organización',
        'campos' => [
            'nombre' => 'Nombre',
            'logo' => 'Logo',
            'color_acento' => 'Color del sistema',
        ],
        'sin_logo' => 'Sin logo',
        'logo_ayuda' => 'PNG, JPG, WEBP o SVG de hasta 1 MB. Se muestra en el menú, en formato cuadrado.',
        'logo_cambiar' => 'Cambiar logo',
        'logo_elegir' => 'Elegir logo',
        'logo_quitar' => 'Quitar logo',
        'logo_subiendo' => 'Subiendo...',
        'logo_nuevo' => 'Logo nuevo (todavía no guardado)',
        'color_ayuda' => 'Se aplica a los botones, los títulos y el módulo activo del menú. El resto de la pantalla es gris siempre.',
        'validacion' => [
            'logo' => 'El logo tiene que ser una imagen (PNG, JPG, WEBP o SVG) de hasta 1 MB.',
        ],
        'toasts' => [
            'guardados' => 'Datos de la organización guardados.',
        ],
    ],

    // Tipos de pastura (P16): alturas de referencia y fórmula de kg MS/ha.
    'tipos' => [
        'titulo' => 'Tipos de pastura',
        'intro' => 'Con la altura de ingreso un potrero está listo para pastorear; debajo de la de salida (el remanente) no se sigue comiendo. Los kg MS/ha de cada punto se calculan con la fórmula vigente del tipo de pastura de su potrero.',
        'nuevo' => 'Nuevo tipo',
        'modificar' => 'Modificar tipo de pastura',
        'columnas' => [
            'nombre' => 'Tipo',
            'ingreso' => 'Ingreso',
            'salida' => 'Salida',
            'formula' => 'kg MS/ha',
            'uso' => 'Potreros',
            'estado' => 'Estado',
        ],
        'altura' => ':valor cm',
        'formula' => ':a × altura :signo :b',
        'sin_alturas' => 'Sin alturas',
        'sin_formula' => 'Sin fórmula',
        'uso' => '{0} Sin potreros|{1} 1 potrero|[2,*] :count potreros',
        'sin_registros' => 'No hay tipos de pastura cargados.',
        'campos' => [
            'nombre' => 'Nombre',
            'altura_ingreso_cm' => 'Altura de ingreso (cm)',
            'altura_salida_cm' => 'Altura de salida (cm)',
            'biomasa_a' => 'A (kg MS/ha por cm)',
            'biomasa_b' => 'B (kg MS/ha)',
        ],
        'ayuda_nombre' => 'Por ejemplo: alfalfa, festuca, verdeo de avena.',
        'alturas' => 'Alturas de referencia',
        'ayuda_alturas' => 'Las dos o ninguna. Sin alturas, los potreros de este tipo no dicen si están listos.',
        'formula_titulo' => 'Fórmula: kg MS/ha = A × altura + B',
        'ayuda_formula' => 'A y B, o ninguno. B puede ser negativo. Si da menos de cero, cuenta como cero. Cambiarla recalcula también las recorridas anteriores.',
        'validacion' => [
            'alturas_juntas' => 'Cargue las dos alturas o ninguna.',
            'ingreso_mayor' => 'La altura de ingreso tiene que ser mayor que la de salida.',
            'formula_junta' => 'Cargue A y B, o ninguno.',
        ],
        'confirmar_eliminar' => '¿Eliminar el tipo ":nombre"? Ningún potrero lo usa, así que se borra en el momento.',
        'confirmar_desactivar' => 'El tipo ":nombre" lo usan potreros: se desactiva y deja de ofrecerse para potreros nuevos, pero los que ya lo tienen lo conservan.',
        'toasts' => [
            'creado' => 'Tipo de pastura creado.',
            'modificado' => 'Tipo de pastura modificado.',
            'eliminado' => 'Tipo de pastura eliminado.',
            'desactivado' => 'El tipo lo usan potreros: se desactivó en vez de eliminarse.',
            'reactivado' => 'Tipo de pastura reactivado.',
        ],
    ],

    // Parámetros del seguimiento (P17).
    'parametros' => [
        'titulo' => 'Parámetros del seguimiento',
        'intro' => 'Cuándo un potrero queda sin medir y cuándo una recorrida queda para revisar.',
        'modificar' => 'Modificar parámetros',
        'campos' => [
            'dias_sin_medir' => 'Días para quedar sin medir',
            'puntos_minimos' => 'Puntos mínimos por recorrida',
        ],
        'ayuda_dias_sin_medir' => 'Un potrero activo cuya última recorrida tiene más días que esto queda sin medir.',
        'ayuda_puntos_minimos' => 'Una recorrida con menos puntos queda para revisar.',
        'dias' => '{1} 1 día|[2,*] :count días',
        'puntos' => '{1} 1 punto|[2,*] :count puntos',
        'toasts' => [
            'guardados' => 'Parámetros guardados.',
        ],
    ],
];
