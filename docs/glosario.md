# Glosario del negocio

El vocabulario común del proyecto: qué quiere decir cada término cuando lo
usamos en una conversación, en un documento o en el código. Las reglas (cómo
se calcula, qué se valida) no van acá: están en [dominio.md](dominio.md).

- Ante una palabra que se presta a confusión, vale la definición de acá.
- Los nombres nuevos del código usan el término canónico de acá.
- Un término nuevo se agrega en el momento en que se resuelve.

## Palabras con más de un sentido

| Palabra | Sentidos | Cómo distinguirlos |
|---|---|---|
| **Altura** | 1) Altura del disco sobre el suelo (Y), que es la altura comprimida de la pastura. 2) Largo del caño (X). | "Altura" sola es siempre la del disco. Para el caño, decir "largo del caño". |
| **Distancia** | 1) Cámara–disco (Z), la que mide la app. 2) Distancia recorrida en el potrero. | En el código y en pantalla, "distancia" es Z. |
| **Pasturómetro** | 1) El instrumento: el caño con el disco. 2) El nombre viejo del sistema, hasta P22. | El sistema se llama **GeoGrass**; "pasturómetro" es solo el instrumento. Quedan con el nombre viejo identificadores técnicos que no se cambian: la base del celular (`pasturometro` en IndexedDB), el repo y la base `pastura`. |
| **Campo** | 1) El establecimiento agropecuario. 2) Un campo de un formulario. | En el dominio, "campo" es el establecimiento; en la UI se dice "dato" para lo otro cuando hace falta. |
| **Calibración** | 1) La de la cámara: pasar del tamaño de los marcadores a centímetros. 2) La de los cortes: pasar de centímetros a kg MS/ha (lo que dice el agrónomo, "calibrar el lote"). | "Calibración" es siempre la de la cámara. Lo de los cortes es la **curva de biomasa** (P23). |
| **Estado** | 1) El estado fenológico de la pastura (recién pastoreada, rebrote...). 2) El estado de un potrero en el tablero (listo, en crecimiento, bajo el remanente). 3) Activo o inactivo. | En el dominio se dice **estado de la pastura** (o fenológico) para el primero y **estado del potrero** para el segundo; nunca "estado" solo. |

## 1. El terreno

**Campo** — El establecimiento: un conjunto de potreros con nombre propio. *No
es* un potrero. *Evitar:* establecimiento (en pantalla), estancia.

**Potrero** — Una parcela del campo con su contorno y, en general, una sola
pastura. *Evitar:* lote, parcela.

**Contorno** — El polígono del potrero en el mapa. Se dibuja o se importa de un
KML/KMZ de Google Earth.

**Tipo de pastura** — La pastura que tiene un potrero (alfalfa, festuca,
verdeo de avena...), con sus alturas de referencia (P16). No tiene kilos:
esos son de las curvas de biomasa (P23). Se administra en Configuración.
*En el código:* `tipos_pastura`, `TipoPastura`. *Evitar:* especie, cultivo.

**Estado de la pastura** (o **estado fenológico**) — Cómo está la pastura
cuando se mide: recién pastoreada, rebrote, pleno crecimiento... La misma
altura no pesa lo mismo en cada uno, porque cambia cómo se comprime bajo el
disco. El recorredor lo elige al empezar la recorrida. Se administra en
Curvas (P24). *En el código:* `estados_fenologicos`, `EstadoFenologico`.
*Evitar:* "estado" solo, etapa.

## 2. La medición

**Pasturómetro** — El caño con el disco que se apoya sobre la pastura; el disco
sube con el pasto y la app mide a qué altura quedó.

**Disco** — El plato del pasturómetro, con los cuatro marcadores ArUco.

**Recorrida** — Una salida de un recorredor a un potrero en la que toma varios
puntos. Es lo que la app sube al panel. *En el código:* `recorridas`.
*Evitar:* monitoreo, muestreo, sesión.

**Medición** (o **punto**) — Una lectura de altura con su posición GPS y su
hora. Pertenece a una recorrida. *En el código:* `mediciones`.

**Calibración** — El ajuste que hace la app con alturas conocidas para pasar
del tamaño de los marcadores a centímetros. Cada medición guarda una copia.
*No es* la curva de biomasa.

**Curva de biomasa** — La relación entre la altura del disco y los kg MS/ha
de un potrero en un estado de la pastura (P23). Sale de unas 15 muestras: en
cada punto se mide con el pasturómetro y se corta el pasto debajo del disco.
Su **recta** (`kg MS/ha = A × altura + B`, con su R²) se calcula de las
muestras. Hay una **vigente** por potrero y estado; las anteriores quedan
**reemplazadas**. Mateo le dice "calibrar el lote". *En el código:*
`curvas_biomasa`, `CurvaBiomasa`, `RectaBiomasa`. *Evitar:* calibración,
fórmula.

**Muestra** — Un punto de una curva de biomasa: la altura del disco y el
peso seco (en gramos, secado en estufa hasta peso constante) del pasto
cortado justo debajo. *En el código:* `muestras_curva`, `MuestraCurva`.
*No es* una medición: la medición es de una recorrida y no se corta nada.

**Área del disco** — Lo que corta una muestra, en m² (0,10 al instalar).
Pasa los gramos a kg MS/ha: con 0,10 m², gramos × 100. Se edita en Curvas
(P24).

**kg MS/ha** — Kilos de materia seca por hectárea: la biomasa estimada a partir
de la altura con la recta de la curva de biomasa vigente del potrero para el
estado de la recorrida. La calcula el panel al leer (P23). Sin curva, no hay
kilos.

## 3. El seguimiento

**Altura de ingreso** — La altura desde la que un potrero está **listo para
pastorear**. Es del tipo de pastura.

**Altura de salida** (o **remanente**) — La altura debajo de la cual no se
sigue comiendo un potrero. Un potrero medido por debajo está **bajo el
remanente**. Es del tipo de pastura. *Evitar:* altura de corte.

**En crecimiento** — Un potrero cuya última altura medida está entre la de
salida y la de ingreso.

**Sin medir** — Un potrero activo cuya última recorrida activa tiene más días
que los que dice Configuración (P17). Uno que no tiene ninguna está **nunca
medido**.

**Recorrida para revisar** — Una recorrida con menos puntos que el mínimo, con
puntos sin GPS o con puntos fuera del contorno del potrero (P18). No es un
estado guardado: se calcula.

**Tasa de crecimiento** — Lo que creció un potrero por día entre dos
recorridas seguidas, en cm/día o en kg MS/ha/día.

**Disponibilidad forrajera** — Los kg de materia seca que hay en un potrero
o en un campo: kg MS/ha × hectáreas. Se calcula, no se guarda.

## 4. Las personas

**Recorredor** — Quien sale al campo con la app y hace las recorridas. *En el
código:* `Rol::Recorredor`.

**Administrador** — Quien carga campos y potreros, revisa las recorridas y
administra los usuarios.

## 5. La app de campo

**Subir** (o **sincronizar**) — Mandar una recorrida del celular al panel.
Se puede repetir sin duplicar nada (P4). *En el código:* `SincronizadorRecorridas`,
`Sincro` en la app.

**Cola de subida** — Las recorridas guardadas en el celular que el panel
todavía no tiene en su última versión. Cada una está **pendiente**, **en el
panel** o **rechazada** (el panel no la aceptó y dice por qué). A una rechazada se le
puede **cambiar el potrero** desde la app: es la misma recorrida, que vuelve
a la cola y el panel actualiza por su uuid.

**Ingreso** — Entrar a la app con el usuario y la contraseña del panel. Deja
un **token** guardado en el celular para subir sin volver a escribir la
contraseña (P10). *Evitar:* login (en pantalla).

**Inicio (de la app)** — La primera pantalla después de ingresar: elegir
entre **Hacer una recorrida** e **Ir al panel** (P12). No confundir con el
**Inicio del panel**, que es el tablero.

**Ir al panel** — Abrir el panel desde la app con la sesión ya iniciada, sin
volver a escribir la contraseña. Usa un enlace de un solo uso que vence en
un minuto (P12). Necesita señal.

**Instrucciones** — El instructivo del panel: cómo se usa todo GeoGrass. Lo
ve cualquier usuario, sin permiso (P25). *No confundir* con la **Ayuda** de
la app, que es la guía corta para leer sin señal y lleva al instructivo.
