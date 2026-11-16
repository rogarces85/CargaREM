@props([
    'texto' => '',
    'tono' => 'primary',
    'tamano' => 'md',
])

{{--
    Avatar con iniciales.

    No hay fotos en el portal: mostrar iniciales evita un icono genérico igual
    para todo el mundo y da una pista de identidad en las tablas. El nombre
    completo va en `title` y como texto oculto para lectores de pantalla.
--}}
@php
    $tamanos = [
        'sm' => 'size-7 text-[10px]',
        'md' => 'size-8 text-xs',
        'lg' => 'size-11 text-sm',
    ];

    $tonos = [
        'primary' => 'bg-primary/10 text-primary',
        'muted' => 'bg-muted text-muted-foreground',
    ];

    // Iniciales: la primera letra de las dos primeras palabras si hay más de
    // una ("Hospital Base" → HB). Con una sola palabra se usan sus dos primeras
    // letras. Tomar siempre los dos primeros caracteres daría "12" para el
    // código 123100, que no distingue nada.
    $palabras = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $iniciales = count($palabras) > 1
        ? mb_substr($palabras[0], 0, 1) . mb_substr($palabras[1], 0, 1)
        : mb_substr($palabras[0] ?? '', 0, 2);
@endphp

<span
    {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center rounded-full font-semibold uppercase',
        $tamanos[$tamano] ?? $tamanos['md'],
        $tonos[$tono] ?? $tonos['primary'],
    ]) }}
    aria-hidden="true"
>{{ $iniciales }}</span>

