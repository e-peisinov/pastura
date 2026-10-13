<?php

/*
 * Textos de la auditoría (P18; D64 del kit): quién anuló recorridas o
 * descartó puntos, cambió contornos, dio de baja potreros o campos, cambió
 * rol, permisos o contraseña de un usuario, o tocó los tipos de pastura, los
 * estados de la pastura, las curvas de biomasa (P23) y los parámetros.
 */
return [
    'titulo' => 'Auditoría',
    'sistema' => 'Sistema',
    'ir' => 'Ir al origen',
    'sin_registros' => 'No hay acciones registradas en el período.',
    'sin_resultados' => 'No hay acciones que coincidan con el filtro.',

    'periodo' => [
        'desde' => 'Desde',
        'hasta' => 'Hasta',
    ],

    'filtros' => [
        'que' => 'Qué',
        'accion' => 'Acción',
        'usuario' => 'Usuario',
    ],

    'columnas' => [
        'fecha' => 'Fecha',
        'usuario' => 'Usuario',
        'que' => 'Acción',
        'detalle' => 'Detalle',
    ],

    // "estado de válida a anulada"
    'cambio' => ':campo de :antes a :despues',
    'contrasena_nueva' => 'contraseña nueva',
    'permiso' => 'permiso en :modulo',
    'hectareas' => ':valor ha',
    'sin_contorno' => 'sin contorno',

    'campos' => [
        'activo' => 'estado',
        'potrero_id' => 'potrero',
        'tipo_pastura_id' => 'tipo de pastura',
        'contorno' => 'superficie',
        'rol' => 'rol',
        'nombre' => 'nombre',
        'altura_ingreso_cm' => 'altura de ingreso (cm)',
        'altura_salida_cm' => 'altura de salida (cm)',
        'biomasa_a' => 'A de la fórmula',
        'biomasa_b' => 'B de la fórmula',
        'dias_sin_medir' => 'días para quedar sin medir',
        'puntos_minimos' => 'puntos mínimos por recorrida',
        'area_disco_m2' => 'área del disco (m²)',
        'estado_fenologico_id' => 'estado de la pastura',
        'fecha' => 'fecha de los cortes',
        'recta' => 'recta',
    ],

    // Cómo se lee `activo` en cada entidad.
    'estados' => [
        'recorrida' => ['si' => 'válida', 'no' => 'anulada'],
        'curva_biomasa' => ['si' => 'vigente', 'no' => 'reemplazada'],
        'medicion' => ['si' => 'activo', 'no' => 'descartado'],
        'otro' => ['si' => 'activo', 'no' => 'inactivo'],
    ],

    'descripciones' => [
        'recorrida' => [
            'modificado' => 'Modificó la recorrida de :potrero del :fecha (:recorredor): :cambios.',
        ],
        'medicion' => [
            'modificado' => 'Modificó el punto de las :hora (:altura) de la recorrida de :potrero del :fecha: :cambios.',
        ],
        'potrero' => [
            'modificado' => 'Modificó el potrero :potrero: :cambios.',
            'eliminado' => 'Eliminó el potrero :potrero.',
        ],
        'campo' => [
            'modificado' => 'Modificó el campo «:nombre»: :cambios.',
            'eliminado' => 'Eliminó el campo «:nombre».',
        ],
        'usuario' => [
            'modificado' => 'Modificó el usuario :nombre (:email): :cambios.',
        ],
        'tipo_pastura' => [
            'creado' => 'Creó el tipo de pastura «:nombre».',
            'modificado' => 'Modificó el tipo de pastura «:nombre»: :cambios.',
            'eliminado' => 'Eliminó el tipo de pastura «:nombre».',
        ],
        'configuracion' => [
            'modificado' => 'Modificó los parámetros del seguimiento: :cambios.',
        ],
        'estado_fenologico' => [
            'creado' => 'Creó el estado de la pastura «:nombre».',
            'modificado' => 'Modificó el estado de la pastura «:nombre»: :cambios.',
            'eliminado' => 'Eliminó el estado de la pastura «:nombre».',
        ],
        'curva_biomasa' => [
            'creado' => 'Creó la curva de biomasa de :potrero (:nombre, :fecha).',
            'modificado' => 'Modificó la curva de biomasa de :potrero (:nombre, :fecha): :cambios.',
            'eliminado' => 'Eliminó la curva de biomasa de :potrero (:nombre, :fecha).',
        ],
    ],
];
