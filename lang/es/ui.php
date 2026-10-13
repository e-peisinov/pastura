<?php

/*
 * Textos de la capa de presentación compartida (componentes <x-ui.*>).
 * Los textos propios de cada pantalla van en su propio archivo.
 */
return [
    'modal' => [
        'cerrar' => 'Cerrar',
    ],

    'confirmacion' => [
        'cancelar' => 'Cancelar',
        'procesando' => 'Procesando...',
        'peligro' => [
            'titulo' => 'Confirmar eliminación',
            'boton' => 'Eliminar',
        ],
        'advertencia' => [
            'titulo' => 'Atención',
            'boton' => 'Continuar',
        ],
        'info' => [
            'titulo' => 'Confirmar',
            'boton' => 'Aceptar',
        ],
    ],

    'toasts' => [
        'cerrar' => 'Cerrar aviso',
    ],

    'panel' => [
        'ver_todo' => 'Ver todo',
    ],

    // <x-ui.grafico-columnas> y <x-ui.grafico-dispersion> (§11).
    'grafico' => [
        'ver_tabla' => 'Ver tabla',
        'periodo' => 'Período',
        'puntos' => 'Puntos',
        'recta' => 'Recta ajustada',
    ],

    'combobox' => [
        'placeholder' => 'Seleccionar...',
        'buscar' => 'Buscar...',
        'sin_resultados' => 'Sin resultados',
        'sin_opciones' => 'Sin opciones',
        'limpiar' => 'Limpiar selección',
    ],

    'paginacion' => [
        'etiqueta' => 'Paginación',
        'mostrando' => 'Mostrando :desde a :hasta de :total registros',
        'mostrando_uno' => 'Mostrando 1 registro',
        'sin_registros' => 'No hay registros para mostrar',
        'pagina_de' => 'Página :actual de :total',
        'anterior' => 'Anterior',
        'siguiente' => 'Siguiente',
        'ir_a' => 'Ir a la página :pagina',
    ],

    'busqueda' => [
        'minimo' => 'Escriba al menos :minimo caracteres para buscar',
        'sin_resultados' => 'No se encontraron resultados para ":texto"',
    ],

    // Botones y rótulos comunes a todos los ABM.
    'acciones' => [
        'nuevo' => 'Nuevo',
        'editar' => 'Modificar',
        'eliminar' => 'Eliminar',
        'desactivar' => 'Desactivar',
        'reactivar' => 'Reactivar',
        'guardar' => 'Guardar',
        'guardando' => 'Guardando...',
        'cancelar' => 'Cancelar',
        'cerrar' => 'Cerrar',
        'ver' => 'Ver',
        'acciones' => 'Acciones',
        'buscar' => 'Buscar',
        'limpiar_filtros' => 'Limpiar filtros',
    ],

    'descarte' => [
        'titulo' => 'Cambios sin guardar',
        'boton' => 'Descartar cambios',
        'pregunta' => 'Hay cambios sin guardar. ¿Desea descartarlos?',
    ],

    'estado' => [
        'activo' => 'Activo',
        'inactivo' => 'Inactivo',
        'activa' => 'Activa',
        'inactiva' => 'Inactiva',
    ],

    'tabla' => [
        'ordenar_por' => 'Ordenar por :columna',
    ],

    'selector' => [
        'buscar_cliente' => 'Buscar cliente por nombre, teléfono o documento...',
        'cambiar' => 'Cambiar',
    ],

    'filtros' => [
        'estado' => 'Estado',
        'todos' => 'Todos',
        'todas' => 'Todas',
        'activos' => 'Activos',
        'inactivos' => 'Inactivos',
        'activas' => 'Activas',
        'inactivas' => 'Inactivas',
    ],

    // Formato::antiguedad(): meses enteros en palabras.
    'tiempo' => [
        'menos_de_un_mes' => 'menos de un mes',
        'meses' => '{1} 1 mes|[2,*] :n meses',
        'anios' => '{1} 1 año|[2,*] :n años',
        'y' => 'y',
        // Duraciones cortas (Formato::duracion, D60)
        'menos_de_un_minuto' => 'menos de 1 min',
        'minutos' => ':n min',
        'horas' => ':n h',
        'dias' => ':n d',
    ],
];
