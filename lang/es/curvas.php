<?php

/*
 * Curvas de biomasa (P23): las muestras cortadas en un potrero en un
 * estado fenológico y la recta que pasa de la altura del disco a kg MS/ha.
 */
return [
    'titulo' => 'Curvas de biomasa',
    'nueva' => 'Nueva curva',
    'modificar' => 'Modificar curva',
    'intro' => 'En cada potrero y en cada estado de la pastura: la altura del disco y el peso seco del pasto cortado debajo. La recta pasa de centímetros a kg MS/ha.',
    'sin_registros' => 'Todavía no hay curvas. Cargue las muestras de un potrero para empezar a ver kg MS/ha.',
    'sin_resultados' => 'No hay curvas que coincidan con el filtro.',

    'filtros' => [
        'campo' => 'Campo',
        'potrero' => 'Potrero',
        'estado' => 'Estado de la pastura',
        'vigencia' => 'Vigencia',
        'vigentes' => 'Vigentes',
        'reemplazadas' => 'Reemplazadas',
    ],

    'columnas' => [
        'potrero' => 'Potrero',
        'estado' => 'Estado de la pastura',
        'fecha' => 'Fecha de los cortes',
        'muestras' => 'Muestras',
        'recta' => 'Recta',
        'r2' => 'R²',
        'vigencia' => 'Vigencia',
    ],

    'vigente' => 'Vigente',
    'reemplazada' => 'Reemplazada',
    'muestras_count' => '{1} :count muestra|[2,*] :count muestras',
    // kg MS/ha = 108 × cm + 250
    'recta' => 'kg MS/ha = :a × cm :signo :b',
    'sin_recta' => 'Sin recta',
    'r2_ayuda' => 'Qué tan bien explica la recta las muestras: cerca de 1, muy bien; por debajo de 0,7, conviene revisar las muestras.',
    'hacer_vigente' => 'Hacer vigente',

    'detalle' => [
        'grafico' => 'Muestras y recta',
        'eje_x' => 'Altura del disco (cm)',
        'eje_y' => 'kg MS/ha',
        'area_disco' => 'Con el disco de :valor m²: cada gramo seco son :kg kg MS/ha.',
        'observaciones' => 'Observaciones',
    ],

    'campos' => [
        'campo' => 'Campo',
        'potrero' => 'Potrero',
        'estado' => 'Estado de la pastura',
        'fecha' => 'Fecha de los cortes',
        'observaciones' => 'Observaciones',
        'muestras' => 'Muestras',
        'altura' => 'Altura (cm)',
        'peso' => 'Peso seco (g)',
        'kg' => 'kg MS/ha',
    ],

    'ayuda_muestras' => 'Una fila por corte: la altura que dio el pasturómetro y el peso del pasto cortado bajo el disco, seco en estufa hasta peso constante. Las filas vacías no se guardan.',
    'ayuda_reemplaza' => 'Al guardar, esta curva reemplaza a la vigente del :fecha para ese potrero y ese estado.',
    'agregar_muestra' => 'Agregar muestra',
    'quitar_muestra' => 'Quitar muestra',
    'vista_previa' => 'Con estas muestras',
    'sin_muestras' => 'Cargue las muestras para ver la recta.',

    'validacion' => [
        'pocas_muestras' => 'Hacen falta al menos :minimo muestras completas.',
        'alturas_iguales' => 'Las muestras tienen que tener al menos dos alturas distintas.',
        'muestra_incompleta' => 'Complete la altura y el peso, o deje la fila vacía.',
        'altura' => 'La altura va de 0 a 999,9 cm.',
        'peso' => 'El peso seco va de 0,01 a 99.999,99 g.',
    ],

    'confirmar_eliminar' => '¿Está seguro de que desea eliminar la curva de :potrero (:estado, :fecha)? Las recorridas de ese estado quedan sin kg MS/ha hasta que haya otra.',

    'toasts' => [
        'creada' => 'Curva guardada.',
        'creada_reemplaza' => 'Curva guardada. Reemplaza a la del :fecha.',
        'modificada' => 'Curva modificada: los kg MS/ha se recalcularon.',
        'eliminada' => 'Curva eliminada.',
        'vigente' => 'La curva vuelve a ser la vigente para ese potrero y ese estado.',
    ],

    // Estados de la pastura (P23; acá desde P24): cada potrero tiene una curva por estado.
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

    // Área del disco (P23; acá desde P24): pasa los gramos de cada muestra a kg MS/ha.
    'area_disco' => [
        'titulo' => 'Área del disco',
        'intro' => 'Lo que corta cada muestra de una curva.',
        'modificar' => 'Modificar el área del disco',
        'campo' => 'Área del disco (m²)',
        'ayuda' => 'La superficie del pasto que se corta bajo el disco en cada muestra. Con 0,1 m², cada gramo seco son 100 kg MS/ha. Cambiarla recalcula los kilos de todas las curvas.',
        'metros' => ':valor m²',
        'equivalencia' => 'Cada gramo seco son :kg kg MS/ha.',
        'toasts' => [
            'guardada' => 'Área del disco guardada.',
        ],
    ],
];
