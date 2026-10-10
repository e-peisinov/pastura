<?php

/*
 * Textos del tablero (/dashboard, D25, D45; bloques de P18).
 */
return [
    'titulo' => 'Inicio',
    'saludo' => 'Bienvenido, :nombre.',

    'bloques' => [
        'estado' => 'Estado de los potreros',
        'para_medir' => 'Potreros para medir',
        'ultimas' => 'Últimas recorridas',
        'mias' => 'Mis recorridas',
        'revisar' => 'Recorridas para revisar',
    ],

    'dias' => '{0} Hoy|{1} Ayer|[2,*] Hace :count días',
    'potrero' => ':campo · :potrero',

    'estado' => [
        'listos' => 'Listos para pastorear, los más altos primero',
        'filtrado' => ':estado, todos',
        'sin_listos' => 'Ningún potrero llegó a su altura de ingreso.',
        'sin_filtrados' => 'No hay potreros en estado ":estado".',
        'sin_alturas' => '{1} 1 potrero no tiene alturas de referencia: no se sabe si está listo.|[2,*] :count potreros no tienen alturas de referencia: no se sabe si están listos.',
        'cargar_alturas' => 'Cargarlas en Configuración',
        'ver_campos' => 'Ver campos',
    ],

    'para_medir' => [
        'sin_medir' => 'Sin medir',
        'nunca_medidos' => 'Nunca medidos',
        'lista' => 'Los que hace más tiempo que no se miden',
        'filtrado' => ':grupo, todos',
        'nunca' => 'Nunca medido',
        'ultima' => ':dias · :altura',
        'al_dia' => 'Todos los potreros se midieron en los últimos :dias días.',
        'sin_filtrados' => 'No hay potreros en ":grupo".',
    ],

    'recorridas' => [
        'hoy' => 'Hoy',
        'semana' => 'Últimos 7 días',
        'lista' => 'Las más recientes',
        'puntos' => '{0} Sin puntos|{1} 1 punto|[2,*] :count puntos',
        'sin_recorridas' => 'Todavía no hay recorridas.',
        'sin_mias' => 'Todavía no hizo recorridas.',
    ],

    'revisar' => [
        'lista' => 'De los últimos :dias días, la más nueva primero',
        'filtrado' => ':motivo, de los últimos :dias días',
        'sin_revisar' => 'No hay recorridas para revisar en los últimos :dias días.',
        'sin_filtradas' => 'No hay recorridas con ":motivo" en los últimos :dias días.',
    ],
];
