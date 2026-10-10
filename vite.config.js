import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    experimental: {
        // Dentro del CSS, las fuentes y las imágenes van relativas al propio
        // CSS: así cargan también en una subcarpeta (P13), no desde /build/.
        renderBuiltUrl(archivo, { hostType }) {
            if (hostType === 'css') {
                return { relative: true };
            }
        },
    },
});
