/**
 * Mapas del panel (P5): dos componentes Alpine sobre Leaflet.
 *
 *   mapaContorno  edita el contorno de un potrero: dibujarlo, moverle los
 *                 vértices, borrarlo o importarlo de un KML/KMZ de Google
 *                 Earth. El contorno (GeoJSON) viaja al componente Livewire
 *                 por $wire.entangle; la superficie se muestra al instante con
 *                 la misma fórmula que App\Support\Geo\Superficie.
 *
 *   mapaVista     solo lectura: contornos de potreros y puntos de medición
 *                 coloreados por altura, con su referencia.
 *
 * El contenedor del mapa va con wire:ignore: Livewire no lo redibuja.
 */
import L from 'leaflet';
import '@geoman-io/leaflet-geoman-free';
import { kml } from '@tmcw/togeojson';
import JSZip from 'jszip';

const RADIO_M = 6378137;

/** Área en hectáreas de un Polygon o MultiPolygon (igual que Superficie.php). */
export function hectareas(geometria) {
    if (!geometria) return null;
    const poligonos = geometria.type === 'Polygon' ? [geometria.coordinates]
        : geometria.type === 'MultiPolygon' ? geometria.coordinates : [];
    let total = 0;
    let hay = false;
    const rad = (g) => (g * Math.PI) / 180;
    for (const anillos of poligonos) {
        anillos.forEach((anillo, i) => {
            if (!Array.isArray(anillo) || anillo.length < 3) return;
            hay = true;
            let suma = 0;
            for (let k = 0; k < anillo.length; k++) {
                const [lon1, lat1] = anillo[k];
                const [lon2, lat2] = anillo[(k + 1) % anillo.length];
                suma += rad(lon2 - lon1) * (2 + Math.sin(rad(lat1)) + Math.sin(rad(lat2)));
            }
            const area = Math.abs((suma * RADIO_M * RADIO_M) / 2);
            total += i === 0 ? area : -area;
        });
    }
    return hay ? Math.max(0, total) / 10000 : null;
}

function capasBase() {
    const satelite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: 'Imágenes © Esri',
    });
    const nombres = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
    });
    const calles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap',
    });
    return { satelite: L.layerGroup([satelite, nombres]), calles };
}

function crearMapa(elemento) {
    const mapa = L.map(elemento, { zoomControl: true, attributionControl: true });
    const base = capasBase();
    base.satelite.addTo(mapa);
    L.control.layers({ 'Satélite': base.satelite, 'Mapa': base.calles }, null, { position: 'topright' }).addTo(mapa);
    // Centro por omisión: la región pampeana, hasta que haya algo que mostrar.
    mapa.setView([-34.6, -61.5], 6);
    return mapa;
}

/** La primera geometría de área de un FeatureCollection, unida si hay varias. */
function geometriaDeArea(coleccion) {
    const poligonos = [];
    for (const f of coleccion.features || []) {
        const g = f.geometry;
        if (!g) continue;
        if (g.type === 'Polygon') poligonos.push(g.coordinates);
        if (g.type === 'MultiPolygon') poligonos.push(...g.coordinates);
        if (g.type === 'GeometryCollection') {
            for (const sub of g.geometries || []) {
                if (sub.type === 'Polygon') poligonos.push(sub.coordinates);
                if (sub.type === 'MultiPolygon') poligonos.push(...sub.coordinates);
            }
        }
    }
    if (!poligonos.length) return null;
    return poligonos.length === 1
        ? { type: 'Polygon', coordinates: poligonos[0] }
        : { type: 'MultiPolygon', coordinates: poligonos };
}

/** Lee un .kml o un .kmz y devuelve el FeatureCollection. */
export async function leerKml(archivo) {
    let texto;
    if (/\.kmz$/i.test(archivo.name)) {
        const zip = await JSZip.loadAsync(archivo);
        const kmlArchivo = Object.values(zip.files).find((f) => /\.kml$/i.test(f.name));
        if (!kmlArchivo) throw new Error('kmz-sin-kml');
        texto = await kmlArchivo.async('text');
    } else {
        texto = await archivo.text();
    }
    const doc = new DOMParser().parseFromString(texto, 'text/xml');
    return kml(doc);
}

/** Redondea a 7 decimales (~1 cm): lo que guarda la base y alcanza de sobra. */
function redondear(geometria) {
    const r = (c) => (Array.isArray(c[0]) ? c.map(r) : [Math.round(c[0] * 1e7) / 1e7, Math.round(c[1] * 1e7) / 1e7]);
    return { type: geometria.type, coordinates: r(geometria.coordinates) };
}

const ESTILO_POTRERO = { color: '#facc15', weight: 2, fillOpacity: 0.15 };
const ESTILO_VECINO = { color: '#ffffff', weight: 1, dashArray: '4 4', fillOpacity: 0.05, interactive: false };

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.data('mapaContorno', ({ contorno, vecinos = [] }) => ({
        contorno,
        hectareas: null,
        error: null,
        mapa: null,
        capa: null,

        init() {
            this.$nextTick(() => {
                this.mapa = crearMapa(this.$refs.mapa);
                this.capa = L.featureGroup().addTo(this.mapa);

                const fondo = L.featureGroup().addTo(this.mapa);
                for (const v of vecinos) {
                    if (v.contorno) {
                        L.geoJSON(v.contorno, { style: ESTILO_VECINO }).bindTooltip(v.nombre).addTo(fondo);
                    }
                }

                this.mapa.pm.setLang('es');
                this.mapa.pm.addControls({
                    position: 'topleft',
                    drawMarker: false, drawCircleMarker: false, drawPolyline: false,
                    drawRectangle: true, drawPolygon: true, drawCircle: false, drawText: false,
                    cutPolygon: true, rotateMode: false, dragMode: true, editMode: true, removalMode: true,
                });
                this.mapa.pm.setPathOptions(ESTILO_POTRERO);

                this.mapa.on('pm:create', (e) => {
                    e.layer.setStyle(ESTILO_POTRERO);
                    this.capa.addLayer(e.layer);
                    this.engancharCapa(e.layer);
                    this.sincronizar();
                });
                this.mapa.on('pm:remove', () => this.sincronizar());
                this.mapa.on('pm:cut', (e) => {
                    this.capa.removeLayer(e.originalLayer);
                    this.capa.addLayer(e.layer);
                    this.engancharCapa(e.layer);
                    this.sincronizar();
                });

                this.dibujar(this.contorno);
                if (this.capa.getLayers().length) {
                    this.mapa.fitBounds(this.capa.getBounds(), { padding: [20, 20] });
                } else if (fondo.getLayers().length) {
                    this.mapa.fitBounds(fondo.getBounds(), { padding: [20, 20] });
                }
                // El modal termina de abrir después: Leaflet tiene que volver a medir el contenedor.
                setTimeout(() => this.mapa.invalidateSize(), 250);
            });
        },

        engancharCapa(capa) {
            capa.on('pm:edit pm:dragend', () => this.sincronizar());
        },

        dibujar(geometria) {
            this.capa.clearLayers();
            this.hectareas = hectareas(geometria);
            if (!geometria) return;
            L.geoJSON(geometria, { style: ESTILO_POTRERO }).eachLayer((capa) => {
                this.capa.addLayer(capa);
                this.engancharCapa(capa);
            });
        },

        /** Lo dibujado pasa a GeoJSON (Polygon o MultiPolygon) y a Livewire. */
        sincronizar() {
            const poligonos = [];
            this.capa.eachLayer((capa) => {
                const g = capa.toGeoJSON().geometry;
                if (g.type === 'Polygon') poligonos.push(g.coordinates);
                if (g.type === 'MultiPolygon') poligonos.push(...g.coordinates);
            });
            const geometria = !poligonos.length ? null
                : poligonos.length === 1 ? { type: 'Polygon', coordinates: poligonos[0] }
                : { type: 'MultiPolygon', coordinates: poligonos };
            this.contorno = geometria ? redondear(geometria) : null;
            this.hectareas = hectareas(this.contorno);
        },

        async importar(evento) {
            const archivo = evento.target.files[0];
            evento.target.value = '';
            this.error = null;
            if (!archivo) return;
            try {
                const geometria = geometriaDeArea(await leerKml(archivo));
                if (!geometria) {
                    this.error = 'sin-poligono';
                    return;
                }
                this.contorno = redondear(geometria);
                this.dibujar(this.contorno);
                this.mapa.fitBounds(this.capa.getBounds(), { padding: [20, 20] });
            } catch (e) {
                this.error = 'archivo';
            }
        },

        borrar() {
            this.capa.clearLayers();
            this.sincronizar();
        },

        destroy() {
            this.mapa?.remove();
        },
    }));

    Alpine.data('mapaVista', ({ potreros = [], puntos = [], escala = null }) => ({
        mapa: null,
        referencia: referenciaAltura(escala),

        init() {
            this.$nextTick(() => {
                this.mapa = crearMapa(this.$refs.mapa);
                const todo = L.featureGroup().addTo(this.mapa);

                for (const p of potreros) {
                    if (!p.contorno) continue;
                    L.geoJSON(p.contorno, { style: { ...ESTILO_POTRERO, fillOpacity: p.resaltado ? 0.2 : 0.08 } })
                        .bindTooltip(p.nombre, { sticky: true })
                        .addTo(todo);
                }

                for (const m of puntos) {
                    L.circleMarker([m.lat, m.lon], {
                        radius: 7,
                        weight: 2,
                        color: m.activo ? '#ffffff' : '#9ca3af',
                        fillColor: m.activo ? colorAltura(m.altura, escala) : '#9ca3af',
                        fillOpacity: m.activo ? 0.95 : 0.5,
                        dashArray: m.activo ? null : '2 2',
                    }).bindTooltip(m.etiqueta).addTo(todo);
                }

                if (todo.getLayers().length) {
                    this.mapa.fitBounds(todo.getBounds(), { padding: [24, 24], maxZoom: 17 });
                }
                setTimeout(() => this.mapa.invalidateSize(), 250);
            });
        },

        destroy() {
            this.mapa?.remove();
        },
    }));
});

/**
 * Escala secuencial de un solo tono para la altura: más alto, más oscuro.
 * `escala` = { min, max } de la recorrida; sin escala, de 0 a 30 cm.
 */
export const COLORES_ALTURA = ['#f7fcb9', '#addd8e', '#78c679', '#31a354', '#006837'];

/** Los tramos de la escala para la referencia del mapa: color y rango en cm ("10,0–11,6"). */
export function referenciaAltura(escala) {
    const min = escala?.min ?? 0;
    const max = escala?.max ?? 30;
    const cm = (v) => v.toLocaleString('es-AR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    if (max <= min) {
        return [{ color: colorAltura(min, escala), texto: cm(min) }];
    }
    const paso = (max - min) / COLORES_ALTURA.length;
    return COLORES_ALTURA.map((color, i) => ({
        color,
        texto: `${cm(min + i * paso)}–${cm(min + (i + 1) * paso)}`,
    }));
}

export function colorAltura(altura, escala) {
    const min = escala?.min ?? 0;
    const max = escala?.max ?? 30;
    const t = max > min ? Math.min(1, Math.max(0, (altura - min) / (max - min))) : 0.5;
    return COLORES_ALTURA[Math.min(COLORES_ALTURA.length - 1, Math.floor(t * COLORES_ALTURA.length))];
}
