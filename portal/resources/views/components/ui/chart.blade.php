@props([
    'serie',
    'tipo' => 'area',
    'alto' => 280,
    'titulo' => null,
])

{{--
    Marcador de gráfico.

    El SVG lo dibuja ApexCharts en el cliente, cargado aparte con `import()`.
    El contenedor lleva `role="img"` y un `aria-label` con el resumen: un
    gráfico sin texto alternativo no comunica nada a quien no lo ve. Si el
    gráfico es la única forma de leer un dato, además hay que mostrarlo en
    texto o en tabla (principio 30).
--}}
<div
    data-chart="{{ $tipo }}"
    data-series="{{ $serie }}"
    data-height="{{ $alto }}"
    role="img"
    aria-label="{{ $titulo ?? 'Gráfico ' . $serie }}"
    {{ $attributes->class(['w-full']) }}
></div>
