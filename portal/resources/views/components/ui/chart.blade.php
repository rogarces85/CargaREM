@props([
    'titulo',
    'tipo' => 'barra',
    'series' => [],
    'categorias' => [],
    'valores' => [],
    'alto' => 260,
    'resumen' => null,
])

{{--
    Marcador de gráfico.

    El SVG lo dibuja ApexCharts en el cliente, cargado aparte con `import()` y
    solo en las páginas que declaran `[data-chart]`. El contenedor lleva
    `role="img"` y un `aria-label` con el resumen: un gráfico sin texto
    alternativo no comunica nada a quien no lo ve.

    **El dato no vive solo aquí.** La pantalla que usa este componente muestra
    siempre la misma información en texto o en tabla (principio 30). Si el
    JavaScript no carga —o el navegador lo bloquea—, la cifra sigue estando.
--}}
@php
    $config = [
        'tipo'       => $tipo,
        'series'     => array_values($series),
        'categorias' => array_values($categorias),
        'valores'    => array_values($valores),
        'alto'       => (int) $alto,
    ];

    $descripcion = $resumen ?: ($titulo . ': ' . collect($categorias)->map(fn ($c, $i) => $c . ' ' . ($valores[$i] ?? 0))->implode(', '));
@endphp

<div
    data-chart
    data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE) }}"
    role="img"
    aria-label="{{ $descripcion }}"
    {{ $attributes->class(['w-full']) }}
></div>
