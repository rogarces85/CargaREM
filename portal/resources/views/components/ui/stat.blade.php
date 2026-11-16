@props([
    'icono',
    'rotulo',
    'valor',
    'tono' => 'idle',
    'detalle' => null,
])

{{--
    Tarjeta de indicador.

    La cifra va con `tabular` para que dos tarjetas contiguas no bailen al
    cambiar de número. El tono del icono sale del mismo vocabulario que las
    insignias, así que un indicador y su estado se leen igual.
--}}
@php
    $cajas = [
        'ok' => 'bg-status-ok/10 text-status-ok-fg',
        'info' => 'bg-status-info/10 text-status-info-fg',
        'warn' => 'bg-status-warn/10 text-status-warn-fg',
        'danger' => 'bg-status-danger/10 text-status-danger-fg',
        'idle' => 'bg-status-idle/10 text-status-idle-fg',
    ];

    $detalles = [
        'ok' => 'text-status-ok-fg',
        'info' => 'text-status-info-fg',
        'warn' => 'text-status-warn-fg',
        'danger' => 'text-status-danger-fg',
        'idle' => 'text-muted-foreground',
    ];
@endphp

<x-ui.card {{ $attributes }}>
    <div class="flex items-start justify-between gap-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $cajas[$tono] ?? $cajas['idle'] }}">
            <x-ui.icon :name="$icono" class="size-4" />
        </span>
        <span class="text-2xl font-bold tabular leading-none">{{ $valor }}</span>
    </div>

    <p class="mt-3 text-sm font-medium">{{ $rotulo }}</p>

    @if ($detalle)
        <p class="mt-0.5 text-xs {{ $detalles[$tono] ?? $detalles['idle'] }}">{{ $detalle }}</p>
    @endif
</x-ui.card>
