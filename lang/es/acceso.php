<?php

/*
 * Textos de las pantallas de acceso (D52): login, olvido y restablecimiento
 * de contraseña. Comparten el layout `layouts.guest`.
 */
return [
    'login' => [
        'titulo' => 'Ingresar al sistema',
        'subtitulo' => 'Ingrese su correo y contraseña para continuar.',
        'email' => 'Correo electrónico',
        'password' => 'Contraseña',
        'recordarme' => 'Recordarme en este equipo',
        'olvido' => '¿Olvidó su contraseña?',
        'boton' => 'Ingresar',
        'ingresando' => 'Ingresando...',
    ],

    'olvido' => [
        'titulo' => 'Recuperar la contraseña',
        'subtitulo' => 'Indique su correo y le enviamos un enlace para elegir una contraseña nueva.',
        'boton' => 'Enviar enlace',
        'enviando' => 'Enviando...',
        'volver' => 'Volver al ingreso',
    ],

    'restablecer' => [
        'titulo' => 'Nueva contraseña',
        'subtitulo' => 'Elija la contraseña con la que va a ingresar de ahora en más.',
        'password' => 'Contraseña nueva',
        'confirmacion' => 'Repetir la contraseña',
        'boton' => 'Guardar contraseña',
        'guardando' => 'Guardando...',
    ],
];
