@props([
    'tono' => 'idle',
    'titulo',
    'detalle' => null,
])

{{--
    Hito de la línea de tiempo.

    El punto y el conector van en tono del estado; el conector se oculta solo en
    el último elemento (regla `.linea-tiempo > li:last-child` en app.css).
--}}
@php
    $puntos = [
        'ok' => 'bg-status-ok',
        'info' => 'bg-status-info',
        'warn' => 'bg-status-warn',
        'danger' => 'bg-status-danger',
        'idle' => 'bg-status-idle',
    ];
@endphp

<li {{ $attributes->class(['relative ps-8']) }}>
    <span
        class="absolute start-0 top-1 flex size-3 items-center justify-center rounded-full ring-4 ring-card {{ $puntos[$tono] ?? $puntos['idle'] }}"
        aria-hidden="true"
    ></span>

    <span
        class="linea-tiempo-conector absolute start-[5px] top-5 h-[calc(100%+0.6rem)] w-px bg-border"
        aria-hidden="true"
    ></span>

    <p class="text-sm font-medium">{{ $titulo }}</p>

    @if ($detalle)
        <p class="text-xs text-muted-foreground">{{ $detalle }}</p>
    @endif

    {{ $slot }}
</li>
