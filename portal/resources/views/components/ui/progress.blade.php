@props([
    'valor' => 0,
    'max' => 100,
    'tono' => 'ok',
    'etiqueta' => null,
])

{{--
    Barra de avance.

    `role="progressbar"` con valor accesible: una barra sin texto alternativo no
    comunica nada a un lector de pantalla. El porcentaje visible dice lo mismo
    que la barra, así que no depende solo del color (principio 30).
--}}
@php
    $porcentaje = $max > 0 ? max(0, min(100, (int) round($valor * 100 / $max))) : 0;

    $rellenos = [
        'ok' => 'bg-status-ok',
        'warn' => 'bg-status-warn',
        'danger' => 'bg-status-danger',
        'info' => 'bg-status-info',
    ];
@endphp

<div {{ $attributes }}>
    <div class="h-2 overflow-hidden rounded-full bg-muted">
        <div
            class="h-full rounded-full transition-[width] duration-300 {{ $rellenos[$tono] ?? $rellenos['ok'] }}"
            style="width: {{ $porcentaje }}%"
            role="progressbar"
            aria-valuenow="{{ $porcentaje }}"
            aria-valuemin="0"
            aria-valuemax="100"
            @if ($etiqueta) aria-label="{{ $etiqueta }}" @endif
        ></div>
    </div>
</div>
