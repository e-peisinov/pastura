<?php

/*
 * Textos de la pantalla de Configuración (P17): la organización (marca
 * blanca, D8), los tipos de pastura con sus alturas (P16), los estados de la
 * pastura (P23) y los parámetros.
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

    // Tipos de pastura (P16): alturas de referencia. Los kg MS/ha son de las curvas (P23).
    'tipos' => [
        'titulo' => 'Tipos de pastura',
        'intro' => 'Con la altura de ingreso un potrero está listo para pastorear; debajo de la de salida (el remanente) no se sigue comiendo. Los kg MS/ha no son del tipo: salen de las curvas de biomasa de cada potrero.',
        'nuevo' => 'Nuevo tipo',
        'modificar' => 'Modificar tipo de pastura',
        'columnas' => [
            'nombre' => 'Tipo',
            'ingreso' => 'Ingreso',
            'salida' => 'Salida',
            'uso' => 'Potreros',
            'estado' => 'Estado',
        ],
        'altura' => ':valor cm',
        'sin_alturas' => 'Sin alturas',
        'uso' => '{0} Sin potreros|{1} 1 potrero|[2,*] :count potreros',
        'sin_registros' => 'No hay tipos de pastura cargados.',
        'campos' => [
            'nombre' => 'Nombre',
            'altura_ingreso_cm' => 'Altura de ingreso (cm)',
            'altura_salida_cm' => 'Altura de salida (cm)',
        ],
        'ayuda_nombre' => 'Por ejemplo: alfalfa, festuca, verdeo de avena.',
        'alturas' => 'Alturas de referencia',
        'ayuda_alturas' => 'Las dos o ninguna. Sin alturas, los potreros de este tipo no dicen si están listos.',
        'validacion' => [
            'alturas_juntas' => 'Cargue las dos alturas o ninguna.',
            'ingreso_mayor' => 'La altura de ingreso tiene que ser mayor que la de salida.',
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

    // Estados de la pastura (P23): cada potrero tiene una curva de biomasa por estado.
    'estados' => [
        'titulo' => 'Estados de la pastura',
        'intro' => 'El recorredor elige uno al empezar la recorrida y cada potrero tiene una curva de biomasa por estado: la misma altura no pesa lo mismo recién pastoreada que en plena primavera.',
        'nuevo' => 'Nuevo estado',
        'modificar' => 'Modificar estado de la pastura',
        'columnas' => [
            'nombre' => 'Estado',
            'uso' => 'Uso',
            'estado' => 'Activo',
        ],
        'uso' => ':curvas · :recorridas',
        'curvas' => '{0} Sin curvas|{1} 1 curva|[2,*] :count curvas',
        'recorridas' => '{0} sin recorridas|{1} 1 recorrida|[2,*] :count recorridas',
        'sin_registros' => 'No hay estados cargados. Sin estados, la app no pregunta cómo está la pastura y no hay kg MS/ha.',
        'campos' => [
            'nombre' => 'Nombre',
        ],
        'ayuda_nombre' => 'Por ejemplo: recién pastoreada, rebrote, pleno crecimiento.',
        'confirmar_eliminar' => '¿Eliminar el estado ":nombre"? No lo usa ninguna curva ni recorrida, así que se borra en el momento.',
        'confirmar_desactivar' => 'El estado ":nombre" lo usan curvas o recorridas: se desactiva y la app deja de ofrecerlo, pero lo que ya lo tiene lo conserva.',
        'toasts' => [
            'creado' => 'Estado de la pastura creado.',
            'modificado' => 'Estado de la pastura modificado.',
            'eliminado' => 'Estado de la pastura eliminado.',
            'desactivado' => 'El estado lo usan curvas o recorridas: se desactivó en vez de eliminarse.',
            'reactivado' => 'Estado de la pastura reactivado.',
        ],
    ],

    // Parámetros del seguimiento (P17) y área del disco (P23).
    'parametros' => [
        'titulo' => 'Parámetros',
        'intro' => 'Cuándo un potrero queda sin medir, cuándo una recorrida queda para revisar y qué superficie corta cada muestra de una curva.',
        'modificar' => 'Modificar parámetros',
        'campos' => [
            'dias_sin_medir' => 'Días para quedar sin medir',
            'puntos_minimos' => 'Puntos mínimos por recorrida',
            'area_disco_m2' => 'Área del disco (m²)',
        ],
        'ayuda_area_disco' => 'Lo que se corta en cada muestra de una curva de biomasa. Con 0,1 m², cada gramo seco son 100 kg MS/ha. Cambiarla recalcula los kilos de todas las curvas.',
        'metros' => ':valor m²',
        'ayuda_dias_sin_medir' => 'Un potrero activo cuya última recorrida tiene más días que esto queda sin medir.',
        'ayuda_puntos_minimos' => 'Una recorrida con menos puntos queda para revisar.',
        'dias' => '{1} 1 día|[2,*] :count días',
        'puntos' => '{1} 1 punto|[2,*] :count puntos',
        'toasts' => [
            'guardados' => 'Parámetros guardados.',
        ],
    ],
];
