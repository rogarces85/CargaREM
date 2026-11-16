@props([
    'tono' => 'info',
    'icono' => null,
])

{{--
    Aviso dentro del flujo.

    Distinto de las notificaciones efímeras (Notyf): este se queda en la página
    porque el usuario debe leerlo antes de seguir. Para el resultado de una
    acción que ya pasó, conviene un aviso efímero.
--}}
@php
    $estilos = [
        'ok' => 'border-status-ok/30 bg-status-ok/10',
        'info' => 'border-status-info/30 bg-status-info/10',
        'warn' => 'border-status-warn/30 bg-status-warn/10',
        'danger' => 'border-status-danger/30 bg-status-danger/10',
    ];

    $textos = [
        'ok' => 'text-status-ok-fg',
        'info' => 'text-status-info-fg',
        'warn' => 'text-status-warn-fg',
        'danger' => 'text-status-danger-fg',
    ];

    $iconos = [
        'ok' => 'circle-check',
        'info' => 'circle-exclamation-point',
        'warn' => 'triangle-exclamation-point',
        'danger' => 'triangle-exclamation-point',
    ];
@endphp

<div {{ $attributes->class(['flex items-start gap-3 rounded-xl border p-3', $estilos[$tono] ?? $estilos['info']]) }}>
    <x-ui.icon
        :name="$icono ?? $iconos[$tono] ?? 'circle-exclamation-point'"
        class="mt-0.5 size-4 shrink-0 {{ $textos[$tono] ?? $textos['info'] }}"
    />

    <div class="min-w-0 text-sm {{ $textos[$tono] ?? $textos['info'] }}">
        {{ $slot }}
    </div>
</div>
