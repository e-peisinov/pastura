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
| **Campo** | 1) El establecimiento agropecuario. 2) Un campo de un formulario. | En el dominio, "campo" es el establecimiento; en la UI se dice "dato" para lo otro cuando hace falta. |

## 1. El terreno

**Campo** — El establecimiento: un conjunto de potreros con nombre propio. *No
es* un potrero. *Evitar:* establecimiento (en pantalla), estancia.

**Potrero** — Una parcela del campo con su contorno y, en general, una sola
pastura. *Evitar:* lote, parcela.

**Contorno** — El polígono del potrero en el mapa. Se dibuja o se importa de un
KML/KMZ de Google Earth.

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

**kg MS/ha** — Kilos de materia seca por hectárea: la biomasa estimada a partir
de la altura con una fórmula `A × altura + B`.

## 3. Las personas

**Recorredor** — Quien sale al campo con la app y hace las recorridas. *En el
código:* `Rol::Recorredor`.

**Administrador** — Quien carga campos y potreros, revisa las recorridas y
administra los usuarios.

## 4. La app de campo

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
