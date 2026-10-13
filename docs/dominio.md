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
                │          *    *
                │          │    └──1 estados_fenologicos   (el que eligió el recorredor, P23)
users 1─────────┼──────────┘                                (quién hizo la recorrida)
                └──* curvas_biomasa 1──* muestras_curva    (por potrero y estado, P23)
                          *
                          └──1 estados_fenologicos
users 1──* permisos           (matriz por módulo, D49)
configuracion                 (fila única: nombre, logo, color, parámetros, área del disco)
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
- Un potrero **tiene historia** si tiene recorridas o curvas de biomasa
  (un campo, si alguno de sus potreros la tiene).

### tipos_pastura

| Columna | Tipo | Notas |
|---|---|---|
| `nombre` | string(100) | Único. |
| `altura_ingreso_cm` | decimal(5,1), null | Listo para pastorear desde acá. |
| `altura_salida_cm` | decimal(5,1), null | El remanente. Las dos alturas van juntas o ninguna; ingreso > salida. |
| `activo` | bool | Baja lógica: con potreros se desactiva, sin potreros se borra. |

La fórmula de kg MS/ha que tenía el tipo (P16) se eliminó: los kilos salen de
las curvas de biomasa de cada potrero (P23).

### estados_fenologicos

| Columna | Tipo | Notas |
|---|---|---|
| `nombre` | string(100) | Único (sin distinguir mayúsculas). |
| `activo` | bool | Baja lógica: con curvas o recorridas se desactiva, sin nada se borra. |

El estado de la pastura cuando se mide (P23). Se administra en la
pantalla de Curvas (P24); arranca con *Recién pastoreada*, *Rebrote* y *Pleno
crecimiento* (`EstadosFenologicosSeeder`). La app los ofrece en el orden en
que se cargaron.

### curvas_biomasa

| Columna | Tipo | Notas |
|---|---|---|
| `potrero_id` | FK potreros, restrict | |
| `estado_fenologico_id` | FK estados_fenologicos, restrict | |
| `fecha` | date | El día de los cortes. No puede ser futura. |
| `observaciones` | text, null | |
| `activo` | bool | **Vigente.** Una sola por potrero y estado: guardar o reactivar otra deja esta **reemplazada**. |

### muestras_curva

| Columna | Tipo | Notas |
|---|---|---|
| `curva_biomasa_id` | FK curvas_biomasa, cascade | |
| `altura_cm` | decimal(5,1) | La altura que dio el pasturómetro en ese punto. |
| `peso_seco_g` | decimal(7,2) | El pasto cortado bajo el disco, seco en estufa hasta peso constante. |

- Cada muestra vale `peso_seco_g / area_disco_m2 × 10` kg MS/ha (1 g/m² =
  10 kg/ha).
- La **recta** de la curva (A, B y R²) sale de sus muestras por mínimos
  cuadrados, al leer (regla 8; `RectaBiomasa`, y la misma cuenta en SQL en
  `Recorrida::subconsultaKg`). Una curva se guarda con **al menos 3 muestras
  y 2 alturas distintas**, así siempre tiene recta.
- Los kg MS/ha de un punto son `max(0, A × altura + B)` con la curva
  **vigente** de su potrero para el estado de su recorrida. Sin estado, o sin
  curva vigente para ese potrero y ese estado, **no hay kg MS/ha** (P23).

### configuracion

Fila única (marca blanca, D8): `nombre`, `logo_path`, `color_acento`, los
parámetros del seguimiento (P17): `dias_sin_medir` (7 al instalar) y
`puntos_minimos` (20 al instalar), y el **área del disco** `area_disco_m2`
(0,10 al instalar, P23): lo que corta cada muestra de una curva. El área se
edita en la pantalla de Curvas (P24), no en Configuración.

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
| `estado_fenologico_id` | FK estados_fenologicos, null, restrict | El que eligió el recorredor al empezar (P23); elige la curva de los kg. Null en las de antes. Se puede corregir desde la ficha. |
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
| `kg_ms_ha` | unsigned int, null | Lo que vio el recorredor en el celular. El panel no lo usa: calcula los kg MS/ha con la curva vigente (P23). |
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
| Curvas de biomasa | Editar | Ver |
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
| kg MS/ha calculados en el panel; la app baja la fórmula (P16) | Reemplazada por P23 |
| Curvas de biomasa por potrero y estado fenológico; la app pide el estado y baja las rectas (P23) | Hecha |
| Modo curva en la app (medir los puntos de corte y numerar las bolsas) | Pendiente |
| Auditoría de lo sensible (P18, P20) | Hecha |
| Reportes con CSV y PDF (P18, P21) | Hecha |
| Estados de la pastura y área del disco en Curvas (P24) | Hecha |
| Instrucciones en el panel y ayuda sin señal en la app (P25) | Hecha |
