# Dominio

Modelo de datos, reglas de negocio y alcance de la v1. El vocabulario está en
[glosario.md](glosario.md); las decisiones y su motivo, en
[decisiones.md](decisiones.md).

## Qué resuelve

**GeoGrass** (geograss.com.ar, P22) es el sistema. Un productor (por ahora, una sola organización, P1) mide la altura de sus
pasturas con un pasturómetro de disco y el celular. La **app de campo**
(`public/app/`, PWA offline, P9) calcula la altura del disco con la cámara y guarda cada
punto con GPS. El **panel** (este Laravel) organiza los campos y potreros, recibe
las recorridas que sube la app y las muestra en tablas y en el mapa.

## Modelo de datos

```
tipos_pastura 1──* potreros
campos 1──* potreros 1──* recorridas 1──* mediciones
                          *
                          │
users 1───────────────────┘   (quién hizo la recorrida)
users 1──* permisos           (matriz por módulo, D49)
configuracion                 (fila única: nombre, logo, color, parámetros)
auditoria                     (una fila por acción sensible, P18 y P20)
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
| `tipo_pastura_id` | FK tipos_pastura, null, restrict | El tipo de pastura (P16). |
| `contorno` | json, null | Geometría GeoJSON (`Polygon` o `MultiPolygon`, lon/lat WGS84). |
| `observaciones` | text, null | |
| `activo` | bool | Baja lógica. |

- La **superficie** se calcula del contorno (área geodésica, en hectáreas), no
  se guarda (regla 8). Sin contorno, no hay superficie.
- Un potrero **tiene historia** si tiene recorridas.

### tipos_pastura

| Columna | Tipo | Notas |
|---|---|---|
| `nombre` | string(100) | Único. |
| `altura_ingreso_cm` | decimal(5,1), null | Listo para pastorear desde acá. |
| `altura_salida_cm` | decimal(5,1), null | El remanente. Las dos alturas van juntas o ninguna; ingreso > salida. |
| `biomasa_a` | decimal(8,3), null | kg MS/ha por cm de altura. |
| `biomasa_b` | decimal(8,1), null | kg MS/ha a altura cero (puede ser negativo). A y B van juntos o ninguno. |
| `activo` | bool | Baja lógica: con potreros se desactiva, sin potreros se borra. |

Los kg MS/ha de un punto son `max(0, A × altura + B)` con la fórmula vigente
del tipo de pastura de su potrero (P16). Sin tipo o sin fórmula, no hay kg
MS/ha.

### configuracion

Fila única (marca blanca, D8): `nombre`, `logo_path`, `color_acento`, y los
parámetros del seguimiento (P17): `dias_sin_medir` (7 al instalar) y
`puntos_minimos` (20 al instalar).

### auditoria

Una fila por acción sensible (P18, P20): `user_id` (null = sistema),
`entidad`, `entidad_id` (sin clave foránea: la fila sobrevive al borrado),
`accion` (creado | modificado | eliminado), `datos` (la foto para leerla:
referencia y, en un cambio, cada campo con antes y después) y solo
`created_at`. La escriben los eventos de los modelos (trait `Auditable`);
el texto se arma al leer.

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
| `kg_ms_ha` | unsigned int, null | Lo que vio el recorredor en el celular. El panel no lo usa: calcula los kg MS/ha con la fórmula vigente del tipo de pastura (P16). |
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
| Reportes | Editar (con Ver alcanza, también para exportar) | Sin acceso |
| Configuración | Editar | Sin acceso |
| Auditoría | Editar (con Ver alcanza: no se edita nada) | Sin acceso |

## Alcance

| Pantalla / pieza | Estado |
|---|---|
| Piezas de UI del kit (10 → 18), layout, acceso | Hecha |
| Usuarios y permisos | Hecha |
| Inicio (tablero): estado de los potreros, para medir, últimas recorridas, para revisar (P18) | Hecha |
| Campos y potreros (ABM, contorno en el mapa, importar KML/KMZ) | Hecha |
| Recorridas (listado, detalle con mapa de puntos, anular, descartar puntos, CSV) | Hecha |
| API de sincronización + login en la app de campo (P9 a P11) | Hecha |
| Inicio de la app: hacer una recorrida o ir al panel sin volver a ingresar (P12) | Hecha |
| Configuración: organización, tipos de pastura, parámetros (P16, P17) | Hecha |
| kg MS/ha calculados en el panel; la app baja la fórmula (P16) | Hecha |
| Auditoría de lo sensible (P18, P20) | Hecha |
| Reportes con CSV y PDF (P18, P21) | Hecha |
