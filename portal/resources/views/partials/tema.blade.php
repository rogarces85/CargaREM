{{--
    Resuelve el tema antes del primer paint.

    Debe ir en el <head>, antes de cualquier hoja de estilos. Si corriera después,
    el navegador alcanza a pintar con los tokens claros y el usuario en oscuro ve
    un destello blanco en cada carga.

    Repite la lógica de `resources/js/ui/theme.js` a propósito: este script corre
    antes de que el bundle exista y no puede importar nada. Si una de las dos
    copias cambia, la otra tiene que cambiar igual. Las claves son las mismas:
    `rem-theme` y `rem-sidebar`.

    Se usa una función con try/catch porque `localStorage` lanza en modo privado
    de algunos navegadores, y quedarse sin tema es mejor que quedarse sin página.
--}}
<script>
    (function () {
        try {
            var VALIDAS = ['light', 'dark', 'system'];
            var guardada = localStorage.getItem('rem-theme');
            var preferencia = VALIDAS.indexOf(guardada) !== -1 ? guardada : 'system';
            var oscuro = preferencia === 'dark'
                || (preferencia === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            var raiz = document.documentElement;

            raiz.classList.add(oscuro ? 'dark' : 'light');
            raiz.dataset.themePreference = preferencia;

            if (localStorage.getItem('rem-sidebar') === 'collapsed') {
                raiz.classList.add('sidebar-collapsed');
            }
        } catch (e) {
            /* Sin almacenamiento local se queda con el tema claro por defecto. */
        }
    })();
</script>
