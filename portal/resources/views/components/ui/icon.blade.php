@props(['name', 'class' => 'size-4'])

{{--
    Icono Lucide.

    Renderiza el marcador que `renderIcons()` reemplaza por el SVG. El nombre es
    el kebab-case del icono; si no está en el juego importado en
    `resources/js/ui/icons.js`, la consola avisa y no se dibuja nada.
--}}
<i data-lucide="{{ $name }}" {{ $attributes->class($class) }}></i>
