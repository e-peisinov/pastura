# Dominio

Modelo de datos, reglas de negocio y alcance de la v1. El vocabulario está en
[glosario.md](glosario.md); las decisiones y su motivo, en
[decisiones.md](decisiones.md).

## Qué resuelve

Un productor (por ahora, una sola organización, P1) mide la altura de sus
pasturas con un pasturómetro de disco y el celular. La **app de campo**
(`poc/`, PWA offline) calcula la altura del disco con la cámara y guarda cada
punto con GPS. El **panel** (este Laravel) organiza los campos y potreros, recibe
las recorridas que sube la app y las muestra en tablas y en el mapa.

## Modelo de datos

```
campos 1──* potreros 1──* recorridas 1──* mediciones
                          *
                          │
users 1───────────────────┘   (quién hizo la recorrida)
users 1──* permisos           (matriz por módulo, D49)
configuracion                 (fila única: nombre, logo, color)
```

### campos

| Columna | Tipo | Notas |
|---|---|---|
| `nombre` | string(150) | Único entre los campos. |
| `localidad` | string(100), null | |
| `observaciones` | text, null | |
| `activo` | bool | Baja lógica (D20). |

Un campo **tiene historia** si alguno de sus potreros la tiene. Sin historia se
borra (con sus potreros); con historia se desactiva.

### potreros

| Columna | Tipo | Notas |
|---|---|---|
| `campo_id` | FK campos, restrict | |
| `nombre` | string(100) | Único dentro del campo. |
| `tipo_pastura` | string(100), null | Texto libre en la v1 (alfalfa, festuca, verdeo...). |
| `contorno` | json, null | Geometría GeoJSON (`Polygon` o `MultiPolygon`, lon/lat WGS84). |
| `observaciones` | text, null | |
| `activo` | bool | Baja lógica. |

- La **superficie** se calcula del contorno (área geodésica, en hectáreas), no
  se guarda (regla 8). Sin contorno, no hay superficie.
- Un potrero **tiene historia** si tiene recorridas.

### recorridas

| Columna | Tipo | Notas |
|---|---|---|
| `uuid` | char(36), único | Lo genera la app; hace idempotente la subida (P4). |
| `potrero_id` | FK potreros, restrict | |
| `user_id` | FK users, restrict | El recorredor que la hizo. |
| `iniciada_en` | datetime | Hora del celular al empezar. |
| `dispositivo` | string(150), null | Descripción del celular. |
| `observaciones` | text, null | |
| `activo` | bool | Una recorrida anulada (mala calibración, potrero equivocado) queda en `false` y no cuenta en ningún promedio. |

Lo que se muestra de una recorrida se **calcula** de sus mediciones activas
(regla 8): cantidad de puntos, altura media, kg MS/ha medio, hora del primer y
último punto.

### mediciones

| Columna | Tipo | Notas |
|---|---|---|
| `recorrida_id` | FK recorridas, cascade | |
| `uuid` | char(36), único | Lo genera la app. |
| `tomada_en` | datetime | |
| `altura_cm` | decimal(5,1) | Altura del disco (Y). |
| `distancia_cm` | decimal(5,1), null | Cámara–disco (Z). |
| `kg_ms_ha` | unsigned int, null | Biomasa estimada por la app, si tiene la fórmula cargada. |
| `latitud`, `longitud` | decimal(10,7), null | WGS84. Null si no había GPS. |
| `precision_m` | decimal(6,1), null | Precisión que informó el GPS. |
| `inclinacion_grados` | decimal(4,1), null | |
| `imagenes` | unsigned tinyint | Imágenes promediadas. |
| `dispersion_cm` | decimal(5,2), null | Dispersión entre imágenes. |
| `calibracion` | json, null | Copia de la calibración usada (a, c, R², gradiente). |
| `activo` | bool | Un punto descartado (por ejemplo, un error de lectura) queda en `false`. |

## Reglas de negocio

1. **Promedios**: la altura media y el kg MS/ha medio de una recorrida usan solo
   mediciones activas de una recorrida activa.
2. **Sincronización** (P4): la app sube una recorrida completa con sus
   mediciones. Si el `uuid` ya existe, se actualiza y las mediciones se suman
   o reemplazan por su propio `uuid`. Subir dos veces lo mismo no duplica nada.
3. **Pertenencia**: el recorredor ve y sube solo sus recorridas; quien
   administra ve todas. Lo dice el rol, no el permiso (como el mecánico del
   origen, D49).
4. Una recorrida solo se puede subir a un potrero activo de un campo activo.

## Roles

| Módulo | Administrador | Recorredor (por omisión) |
|---|---|---|
| Inicio (tablero) | Editar | Ver |
| Campos | Editar | Ver |
| Recorridas | Editar | Editar (solo las suyas) |
| Usuarios | Editar | Sin acceso |

## Alcance

| Pantalla / pieza | Estado |
|---|---|
| Piezas de UI del kit (10 → 18), layout, acceso | Hecha |
| Usuarios y permisos | Hecha |
| Inicio (tablero) | En curso |
| Campos y potreros (ABM, contorno en el mapa, importar KML/KMZ) | Hecha |
| Recorridas (listado, detalle con mapa de puntos, anular, descartar puntos, CSV) | Pendiente |
| API de sincronización + login en la app de campo | Pendiente |
| Reportes, configuración, auditoría | Más adelante |
