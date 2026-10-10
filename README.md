# Pasturómetro

Medición de la altura del disco de un pasturómetro con la cámara del celular,
usando los marcadores ArUco (4×4, números 0 a 3) impresos en el disco.
Funciona sin conexión y georreferencia cada medición.

- `poc/`: prueba de concepto de la app de campo (PWA, sin build). Se publica
  en GitHub Pages en cada push a `main`.

## Cómo funciona la medición

Cada marcador se ve con un tamaño `t` inversamente proporcional a su distancia
a la cámara. La altura del disco sale de `h = c + a / t`, con `a` y `c`
ajustados en la calibración (2 o más alturas conocidas sobre piso duro). Una
corrección de perspectiva aprendida en la misma calibración compensa que la
cámara no quede paralela al disco. No hace falta conocer la focal del celular
ni el tamaño exacto de los marcadores.

## Probar localmente

La cámara exige https o `localhost`:

```
python -m http.server 8931 --bind 127.0.0.1 --directory poc
```

y abrir http://127.0.0.1:8931/. Con `?prueba` el bucle de cámara corre aunque
la pestaña esté oculta (pruebas automáticas). `poc/muestras/disco-prueba.png`
es un disco con los mismos marcadores para mostrar en un monitor.
