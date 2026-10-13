<?php

/*
 * Instrucciones (P25): cómo funciona GeoGrass, para el recorredor y para el
 * administrador. Cada sección es una lista de bloques que la vista dibuja:
 * texto, pasos (lista numerada), lista y nota. Se admite **negrita**.
 *
 * Si cambia algo que el usuario ve, se actualiza acá en el mismo cambio.
 */
return [
    'titulo' => 'Instrucciones',
    'intro' => 'Cómo se usa GeoGrass: la app de campo para medir y el panel para ver y administrar lo medido.',
    'indice' => 'En esta página',
    'volver_arriba' => 'Volver al índice',

    'grupos' => [
        'app' => 'La app de campo',
        'panel' => 'El panel',
        'ayuda' => 'Si algo no anda',
    ],

    'secciones' => [
        'como-funciona' => [
            'titulo' => 'Cómo funciona',
            'bloques' => [
                ['tipo' => 'texto', 'texto' => 'GeoGrass mide la altura de la pastura con el **pasturómetro** (el caño con el disco) y la cámara del celular. El disco se apoya sobre el pasto y sube con él; la cámara, en la punta del caño, mira el disco desde arriba.'],
                ['tipo' => 'texto', 'texto' => 'El disco tiene **cuatro marcadores** (cuadrados blancos y negros, números 0 a 3). Cuanto más alto queda el disco, más cerca está de la cámara y más grandes se ven los marcadores. La app traduce ese tamaño a centímetros con la **calibración**.'],
                ['tipo' => 'lista', 'items' => [
                    '**Recorrida**: una salida a un potrero en la que se toman varios puntos.',
                    '**Punto** (o medición): una altura, con su posición GPS y su hora.',
                    '**Curva de biomasa**: pasa la altura de un potrero a kg de materia seca por hectárea (kg MS/ha).',
                    '**Panel**: la página web donde se ven las recorridas, los potreros en el mapa, los reportes y se administra todo.',
                ]],
                ['tipo' => 'nota', 'texto' => 'La app funciona **sin señal**: guarda todo en el celular y lo sube sola cuando vuelve la conexión.'],
            ],
        ],

        'instalar' => [
            'titulo' => 'Instalar la app en el celular',
            'bloques' => [
                ['tipo' => 'texto', 'texto' => 'La app se abre desde el navegador en **:url** y se instala como cualquier aplicación, sin pasar por la tienda. Instalada, abre a pantalla completa, funciona sin señal y el celular conserva los datos.'],
                ['tipo' => 'pasos', 'titulo' => 'En Android (Chrome)', 'items' => [
                    'Abrí **:url** en Chrome, con señal.',
                    'Ingresá con tu email y tu contraseña del panel.',
                    'Andá a **Ajustes** (abajo a la derecha) y tocá **Instalar en el celular**. Si no aparece el botón, abrí el menú de Chrome (los tres puntos) y elegí **Instalar app** o **Agregar a la pantalla principal**.',
                    'Confirmá. Queda el ícono de GeoGrass en el celular: de ahí en más, abrila desde ese ícono.',
                ]],
                ['tipo' => 'pasos', 'titulo' => 'En iPhone (Safari)', 'items' => [
                    'Abrí **:url** en **Safari** (en iPhone, desde otro navegador no se puede instalar).',
                    'Tocá el botón **Compartir** (el cuadrado con la flecha hacia arriba).',
                    'Bajá y elegí **Agregar a inicio**. Tocá **Agregar**.',
                    'Abrí GeoGrass desde el ícono nuevo e ingresá con tu email y tu contraseña.',
                ]],
                ['tipo' => 'nota', 'texto' => 'En iPhone conviene **siempre** usarla instalada: si se usa desde Safari sin instalar, el teléfono puede borrar los datos guardados después de unos días sin abrirla.'],
                ['tipo' => 'texto', 'texto' => 'La primera vez la app pide permiso para usar la **cámara** y la **ubicación**. Hay que aceptar los dos: sin cámara no mide y sin ubicación los puntos quedan sin GPS.'],
            ],
        ],

        'recorrida' => [
            'titulo' => 'Una recorrida, paso a paso',
            'bloques' => [
                ['tipo' => 'pasos', 'items' => [
                    'En el **Inicio** de la app, tocá **Hacer una recorrida**.',
                    'Elegí el **campo**, el **potrero** y **cómo está la pastura** (recién pastoreada, rebrote...). La app propone el último estado que usaste en ese potrero.',
                    'Tocá **Empezar recorrida** y **Activar cámara**.',
                    'Apoyá el pasturómetro en el pasto, con el caño derecho: el chip **Nivel** se pone verde cuando la inclinación es de 5° o menos.',
                    'Con los marcadores a la vista, tocá **Medir**. La app toma varias imágenes, calcula la altura y guarda el punto con el GPS.',
                    'Caminá el potrero y repetí en distintos lugares. Cuantos más puntos, mejor el promedio.',
                    'Al terminar, tocá **Terminar**. La recorrida queda lista para subir.',
                ]],
                ['tipo' => 'lista', 'items' => [
                    'Arriba se ven el **GPS** (con su precisión) y el estado de la **subida**.',
                    'Si el disco no se lee bien, la app avisa y no guarda nada: hacen falta al menos 2 marcadores a la vista.',
                    'Un punto que salió mal se puede borrar en **Registros** mientras no se haya subido. Si ya está en el panel, se descarta desde la ficha de la recorrida.',
                    'Una recorrida con menos puntos que el mínimo, con puntos sin GPS o fuera del contorno del potrero queda **para revisar** en el panel.',
                ]],
            ],
        ],

        'calibrar' => [
            'titulo' => 'Calibrar la cámara',
            'bloques' => [
                ['tipo' => 'texto', 'texto' => 'Antes de la primera medición hay que **calibrar**: enseñarle a la app cuántos centímetros son el tamaño que ve de los marcadores. La calibración queda guardada **en ese celular**.'],
                ['tipo' => 'pasos', 'items' => [
                    'Andá a **Calibrar** y activá la cámara.',
                    'Apoyá el pasturómetro sobre un piso duro y plano, con el disco en el piso. Dejá **0** en **Altura conocida** y tocá **Capturar**.',
                    'Levantá el disco con tacos de altura conocida (por ejemplo 10, 20, 30 y 40 cm), escribí cada altura y capturá.',
                    'La tabla muestra para cada altura la estimada y el error. Si una fila tiene mucho error, borrala y capturala de nuevo.',
                ]],
                ['tipo' => 'nota', 'texto' => 'Con 2 alturas alcanza para medir; con 4 o 5 es mucho más preciso. Hay que **volver a calibrar** si se cambia de celular, se mueve la cámara en el caño o se cambia el caño.'],
            ],
        ],

        'senal' => [
            'titulo' => 'Con señal y sin señal',
            'bloques' => [
                ['tipo' => 'texto', 'texto' => 'En el campo casi nunca hay señal, y no hace falta: la app guarda cada punto en el celular en el momento y sube las recorridas cuando puede.'],
                ['tipo' => 'pasos', 'titulo' => 'Antes de salir (con señal)', 'items' => [
                    'Abrí la app. Si el Inicio avisa que no está lista para usar sin señal, esperá a que termine de prepararse.',
                    'En la recorrida nueva, tocá **Actualizar campos**: así el celular tiene los campos, los potreros y las curvas al día.',
                    'Revisá que la calibración esté hecha.',
                ]],
                ['tipo' => 'lista', 'titulo' => 'Cómo se suben las recorridas', 'items' => [
                    'Solas: cuando vuelve la señal, cuando se vuelve a abrir la app y una vez por minuto.',
                    'A mano: **Registros** → **Subir ahora**.',
                    'Cada recorrida dice si está **pendiente**, **en el panel** o **rechazada**. Subir dos veces la misma no la duplica.',
                    'Si el panel rechaza una, en **Registros** se ve el motivo. Si el problema es el potrero, se puede **cambiar el potrero** y vuelve a subir.',
                    'Si la sesión vence, las recorridas siguen guardadas en el celular: ingresá de nuevo y se suben.',
                ]],
                ['tipo' => 'nota', 'texto' => '**Borrar todos los datos** (en Ajustes) borra también lo que todavía no se subió. Usalo solo si sabés que todo está en el panel.'],
                ['tipo' => 'texto', 'texto' => 'Para entrar al **panel** desde la app hace falta señal: **Ir al panel** abre tu sesión sin volver a escribir la contraseña. Para volver a la app desde el panel: menú del usuario → **Abrir la app de campo**.'],
            ],
        ],

        'curvas' => [
            'titulo' => 'Curvas de biomasa',
            'bloques' => [
                ['tipo' => 'texto', 'texto' => 'La altura sola no dice cuánto pasto hay: la misma altura pesa distinto según el potrero y según cómo esté la pastura. Por eso los kg MS/ha salen de una **curva de biomasa** propia de cada **potrero** y de cada **estado de la pastura**.'],
                ['tipo' => 'pasos', 'titulo' => 'Cómo se arma una curva', 'items' => [
                    'En el potrero, en unos **15 puntos**, medí la altura con el pasturómetro.',
                    'En cada punto, cortá todo el pasto que quedó **justo debajo del disco** y guardalo en una bolsa numerada.',
                    'Secá cada bolsa en estufa **hasta peso constante** y pesala en gramos.',
                    'En el panel, en **Curvas de biomasa** → **Nueva curva**, elegí el potrero, el estado de la pastura y la fecha de los cortes, y cargá cada muestra: la altura y el peso seco.',
                ]],
                ['tipo' => 'lista', 'items' => [
                    'Una curva se guarda con al menos **3 muestras** y **2 alturas distintas**. Con la recta, el panel muestra el **R²**: cerca de 1 la recta explica muy bien las muestras; por debajo de 0,7 conviene revisarlas.',
                    'Hay **una curva vigente** por potrero y estado. Al cargar una nueva, la anterior queda **reemplazada** (se puede volver a hacer vigente).',
                    'Corregir una curva recalcula los kilos de todas las recorridas de ese potrero y ese estado.',
                    '**Sin curva no hay kilos**: la recorrida muestra la altura, pero no los kg MS/ha.',
                ]],
                ['tipo' => 'texto', 'texto' => 'En la misma pantalla se administran los **estados de la pastura** (los que ofrece la app al empezar una recorrida) y el **área del disco**, que pasa los gramos de cada muestra a kg MS/ha: con 0,1 m², cada gramo seco son 100 kg MS/ha.'],
            ],
        ],

        'tablero' => [
            'titulo' => 'Inicio del panel',
            'bloques' => [
                ['tipo' => 'texto', 'texto' => 'El **Inicio** resume cómo están los potreros según su última altura medida y las alturas de su tipo de pastura: **listos** para pastorear, **en crecimiento**, **bajo el remanente**, **sin medir** hace muchos días o **nunca medidos**. También muestra las últimas recorridas y las que quedaron **para revisar**.'],
            ],
        ],

        'campos' => [
            'titulo' => 'Campos y potreros',
            'bloques' => [
                ['tipo' => 'pasos', 'items' => [
                    'En **Campos**, creá el campo y sus potreros.',
                    'A cada potrero dale su **tipo de pastura** (de ahí salen las alturas de ingreso y de salida).',
                    'Cargá el **contorno**: dibujalo en el mapa (polígono o rectángulo) o importá un archivo **KML o KMZ** de Google Earth.',
                ]],
                ['tipo' => 'texto', 'texto' => 'Con el contorno, el panel calcula la superficie, controla que los puntos caigan dentro del potrero y puede sumar la disponibilidad forrajera del campo.'],
            ],
        ],

        'panel-recorridas' => [
            'titulo' => 'Revisar las recorridas',
            'bloques' => [
                ['tipo' => 'texto', 'texto' => 'En **Recorridas** están todas las que subió la app, con su altura promedio y sus kg MS/ha. La ficha de cada una muestra los puntos en el mapa.'],
                ['tipo' => 'lista', 'items' => [
                    '**Descartar un punto** que salió mal: deja de contar en el promedio, pero se conserva.',
                    '**Cambiar el estado de la pastura** si se eligió mal al empezar: cambia la curva y los kilos.',
                    '**Anular** una recorrida entera: deja de contar en los promedios del potrero y se puede restaurar.',
                    '**Exportar CSV** con lo que muestra el filtro.',
                ]],
            ],
        ],

        'reportes' => [
            'titulo' => 'Reportes',
            'bloques' => [
                ['tipo' => 'texto', 'texto' => 'En **Reportes** se elige un período y se ven la evolución de un potrero, los potreros de un campo, la **disponibilidad forrajera** (kg MS/ha × hectáreas, de los potreros con contorno y curva) y la actividad de cada recorredor. Todo se puede bajar en **CSV** o **PDF**.'],
            ],
        ],

        'usuarios' => [
            'titulo' => 'Usuarios y permisos',
            'bloques' => [
                ['tipo' => 'texto', 'texto' => 'En **Usuarios** se crean las cuentas (las mismas sirven para la app y el panel), se cambia la contraseña y se desactiva a quien ya no trabaja.'],
                ['tipo' => 'lista', 'items' => [
                    'Cada usuario tiene, para cada módulo, **Sin acceso**, **Ver** o **Editar**.',
                    'El **administrador** nace con Editar en todo; el **recorredor**, con lo justo para hacer recorridas y consultar.',
                    'Para hacer recorridas desde la app hace falta **Editar en Recorridas**.',
                    'Estas instrucciones las ve todo usuario, sin permiso.',
                ]],
            ],
        ],

        'configuracion' => [
            'titulo' => 'Configuración',
            'bloques' => [
                ['tipo' => 'lista', 'items' => [
                    'El **nombre, el logo y el color** con los que se ve el sistema.',
                    'Los **tipos de pastura**, con su altura de ingreso (listo para pastorear) y de salida (remanente).',
                    'Cuántos **días** pasan para que un potrero quede sin medir y cuántos **puntos** como mínimo tiene que tener una recorrida para no quedar para revisar.',
                ]],
            ],
        ],

        'auditoria' => [
            'titulo' => 'Auditoría',
            'bloques' => [
                ['tipo' => 'texto', 'texto' => 'Registra quién hizo qué y cuándo en lo sensible: campos y potreros, curvas y estados de la pastura, recorridas anuladas, puntos descartados, usuarios y sus permisos, y configuración.'],
            ],
        ],

        'problemas' => [
            'titulo' => 'Problemas frecuentes',
            'bloques' => [
                ['tipo' => 'lista', 'items' => [
                    '**"No se pudo leer bien el disco"**: que se vean al menos 2 marcadores enteros, sin sombra fuerte ni pasto encima, y con la lente limpia.',
                    '**La cámara no arranca**: revisá que el navegador tenga permiso de cámara (en los ajustes del celular) y cerrá otras apps que la estén usando.',
                    '**Puntos sin GPS**: activá la ubicación del celular y dale permiso a la app. Al aire libre, esperá unos segundos a que el chip GPS se ponga verde (±10 m o menos).',
                    '**La recorrida no sube**: mirá en Registros si está pendiente (falta señal: se sube sola) o rechazada (dice el motivo). Si la sesión venció, ingresá de nuevo.',
                    '**No aparece un potrero o un estado nuevo**: con señal, tocá **Actualizar campos**.',
                    '**Una recorrida no tiene kg MS/ha**: falta la curva de biomasa de ese potrero para ese estado, o la recorrida no tiene estado.',
                    '**Las alturas no parecen reales**: volvé a calibrar, sobre piso duro y con tacos medidos.',
                ]],
            ],
        ],
    ],
];
