{{--
    Contenedor de tabla.

    La tabla en sí usa la clase `.tabla`, que define encabezados, filas, hover y
    separadores en un solo lugar. Sin eso, cada una de las diez tablas del
    portal repetiría nueve clases por celda. El `overflow-x-auto` mantiene la
    tabla usable en móvil sin romper el ancho de la página.
--}}
<div {{ $attributes->class(['overflow-x-auto rounded-xl border border-border bg-card']) }}>
    <table class="tabla">
        {{ $slot }}
    </table>
</div>
