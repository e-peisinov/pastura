<?php

/*
 * Textos de la pantalla de usuarios (/admin/usuarios, D49): alta con
 * contraseña y rol, cambio de contraseña, baja lógica y la matriz de
 * permisos por módulo.
 */
return [
    'titulo' => 'Usuarios',
    'nuevo' => 'Nuevo usuario',
    'modificar' => 'Modificar usuario',
    'yo' => 'Usted',

    'columnas' => [
        'usuario' => 'Usuario',
        'rol' => 'Rol',
        'permisos' => 'Permisos',
        'estado' => 'Estado',
    ],

    'resumen' => [
        'editar' => '{0} Sin módulos para editar|{1} Edita 1 módulo|[2,*] Edita :count módulos',
        'ver' => '{1} ve 1|[2,*] ve :count',
        'sin_acceso' => '{1} sin acceso a 1|[2,*] sin acceso a :count',
    ],

    'campos' => [
        'nombre' => 'Nombre',
        'email' => 'Correo electrónico',
        'rol' => 'Rol',
        'contrasena' => 'Contraseña',
        'contrasena_confirmation' => 'Repetir contraseña',
        'permisos' => 'Permisos por módulo',
    ],

    'ayuda_rol' => 'El rol carga los permisos por omisión; después se ajustan módulo por módulo. Cambiar el rol vuelve a cargarlos.',
    'ayuda_contrasena' => 'Al menos 8 caracteres. El usuario puede cambiarla después desde su perfil.',
    'ayuda_permisos' => 'Sin acceso saca el módulo del menú. Ver deja entrar y mirar, sin ninguna acción. Editar es el acceso completo.',

    'contrasena' => [
        'titulo' => 'Cambiar la contraseña de :nombre',
        'boton' => 'Cambiar contraseña',
    ],

    'acciones' => [
        'contrasena' => 'Cambiar contraseña',
    ],

    'confirmar_desactivar' => '¿Está seguro de que desea dar de baja a :nombre? No podrá ingresar, pero sus órdenes y sus cobros quedan como están.',

    'sin_registros' => 'No hay usuarios cargados.',
    'sin_resultados' => 'No hay usuarios con ese estado.',

    'validacion' => [
        'ultimo_editor' => 'Es el único usuario activo que puede editar Usuarios: antes de recortarlo, dele Editar en Usuarios a otro.',
    ],

    'toasts' => [
        'creado' => 'Usuario creado.',
        'modificado' => 'Usuario modificado.',
        'contrasena' => 'Contraseña cambiada.',
        'desactivado' => 'Usuario dado de baja.',
        'reactivado' => 'Usuario reactivado.',
        'a_si_mismo' => 'No puede darse de baja a sí mismo.',
        'ultimo_editor' => 'Es el único usuario activo que puede editar Usuarios: no se lo puede dar de baja.',
    ],
];
