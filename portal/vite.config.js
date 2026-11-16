import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    /*
     * Rutas relativas dentro del CSS compilado.
     *
     * Sin esto, `laravel-vite-plugin` fija `base` en `/{buildDirectory}/` y las
     * fuentes quedan referenciadas como `/build/assets/inter-*.woff2`. El
     * portal no vive en la raíz del dominio —en local se sirve desde
     * `/Cargas/portal/public`— así que esa ruta da 404. Con `./`, Vite escribe
     * la referencia relativa al propio CSS y el artefacto funciona en la raíz o
     * en cualquier subdirectorio, sin tocar `.env` ni reconstruir por entorno.
     *
     * Los enlaces <script> y <link> los arma Laravel desde el manifiesto, no
     * Vite, así que esta opción no los afecta.
     */
    base: './',

    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
