<?php

/*
 * Textos de Reportes (P18, P21; molde: el módulo 39 del kit).
 */
return [
    'titulo' => 'Reportes',
    'sin_datos' => 'No hay recorridas en el período.',

    'acciones' => [
        'csv' => 'Exportar CSV',
        'pdf' => 'Descargar PDF',
    ],

    'periodo' => [
        'desde' => 'Desde',
        'hasta' => 'Hasta',
        'atajos' => [
            'mes' => 'Este mes',
            'trimestre' => 'Últimos 3 meses',
            'semestre' => 'Últimos 6 meses',
            'anio' => 'Últimos 12 meses',
        ],
    ],

    'resumen' => [
        'recorridas' => 'Recorridas',
        'puntos' => 'Puntos',
        'potreros' => 'Potreros medidos',
        'hectareas' => 'Hectáreas medidas',
        'ayuda' => [
            'recorridas' => 'Las válidas, con algún punto.',
            'puntos' => 'Sin contar los descartados.',
            'potreros' => 'Con al menos una recorrida.',
            'hectareas' => 'La superficie de esos potreros.',
        ],
    ],

    'comparacion' => [
        'sube' => 'Subió :porcentaje %',
        'baja' => 'Bajó :porcentaje %',
        'igual' => 'Igual',
        'sin_base' => 'Sin datos antes',
        'antes' => 'antes :valor',
        'periodo' => 'Comparado con el período anterior del mismo largo: del :desde al :hasta.',
    ],

    'filtros' => [
        'campo' => 'Campo',
        'potrero' => 'Potrero',
    ],

    'evolucion' => [
        'titulo' => 'Evolución de un potrero',
        'serie' => 'Altura media',
        'ayuda' => 'Una columna por recorrida. El crecimiento es lo que cambió la altura desde la recorrida anterior, dividido los días que pasaron; si bajó, se pastoreó o se cortó.',
        'elegir' => 'Elija un campo y un potrero para ver cómo creció.',
        'sin_datos' => 'Este potrero no tiene recorridas en el período.',
        'subio' => 'creció :valor cm/día',
        'bajo' => 'bajó :valor cm/día',
        'sin_tasa' => 'primera del período',
        'kg_dia' => ':valor kg MS/ha/día',
    ],

    'potreros' => [
        'titulo' => 'Potreros del campo',
        'ayuda' => 'La altura de la última recorrida del período, los más altos primero.',
        'detalle' => ':fecha · :kg',
        'detalle_sin_kg' => ':fecha',
        'sin_datos' => '{1} 1 potrero sin recorridas en el período.|[2,*] :count potreros sin recorridas en el período.',
        'elegir' => 'Elija un campo para comparar sus potreros.',
    ],

    'disponibilidad' => [
        'titulo' => 'Disponibilidad forrajera',
        'ayuda' => 'kg MS/ha de la última recorrida del período × hectáreas, sumado por campo. Solo los potreros con contorno y con fórmula de kg MS/ha.',
        'valor' => ':valor kg MS',
        'detalle' => ':con_dato de :potreros potreros con dato',
        'sin_datos' => 'Ningún potrero medido en el período tiene contorno y fórmula de kg MS/ha.',
    ],

    'recorredores' => [
        'titulo' => 'Actividad por recorredor',
        'detalle' => '{1} 1 punto|[2,*] :count puntos',
    ],

    'kg' => ':valor kg MS/ha',

    'exportar' => [
        'resumen' => 'Resumen del :desde al :hasta (comparado con el :antes_desde al :antes_hasta)',
        'evolucion' => 'Evolución de :potrero',
        'potreros' => 'Potreros de :campo',
        'columnas' => [
            'resumen' => ['dato' => 'Dato', 'periodo' => 'Período', 'anterior' => 'Período anterior', 'variacion' => 'Variación (%)'],
            'evolucion' => ['fecha' => 'Fecha', 'altura' => 'Altura media (cm)', 'kg' => 'kg MS/ha', 'tasa_cm' => 'Crecimiento (cm/día)', 'tasa_kg' => 'Crecimiento (kg MS/ha/día)'],
            'potreros' => ['potrero' => 'Potrero', 'altura' => 'Altura (cm)', 'kg' => 'kg MS/ha', 'fecha' => 'Fecha'],
            'disponibilidad' => ['campo' => 'Campo', 'kg' => 'kg MS', 'con_dato' => 'Potreros con dato', 'potreros' => 'Potreros'],
            'recorredores' => ['recorredor' => 'Recorredor', 'recorridas' => 'Recorridas', 'puntos' => 'Puntos'],
        ],
    ],
];
