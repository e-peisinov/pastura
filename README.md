# GeoGrass

[geograss.com.ar](https://geograss.com.ar). Medición de la altura del disco de un pasturómetro con la cámara del celular,
usando los marcadores ArUco (4×4, números 0 a 3) impresos en el disco.
Funciona sin conexión y georreferencia cada medición.

- `public/app/`: la app de campo (PWA, sin build). Se publica con el panel
  en el mismo servidor, en `/app`, y sube las recorridas por la API.
- El resto es el panel en Laravel (ver `CLAUDE.md` y `docs/`).

## Cómo funciona la medición

Cada marcador se ve con un tamaño `t` inversamente proporcional a su distancia
a la cámara. La altura del disco sale de `h = c + a / t`, con `a` y `c`
ajustados en la calibración (2 o más alturas conocidas sobre piso duro). Una
corrección de perspectiva aprendida en la misma calibración compensa que la
cámara no quede paralela al disco. No hace falta conocer la focal del celular
ni el tamaño exacto de los marcadores.

## Probar localmente

La cámara exige https o `localhost`. Con XAMPP, abrir
http://localhost/pastura/public/app/ e ingresar con un usuario del panel. Con
`?prueba` el bucle de cámara corre aunque la pestaña esté oculta (pruebas
automáticas). `public/app/muestras/disco-prueba.png` es un disco con los
mismos marcadores para mostrar en un monitor.
