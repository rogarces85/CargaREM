@props([
    'tono' => 'idle',
    'punto' => true,
])

{{--
    Insignia de estado.

    `tono` acepta el vocabulario cerrado de `App\Support\EstadoUi`: ok, info,
    warn, danger, idle, none. El color va por tokens, nunca por clase
    construida (constitución, principio 29), y el texto siempre acompaña al
    color (principio 30).

    Con `tono = none` no se pinta fondo ni borde: el estado simplemente no aplica.
--}}
@php
    // Clases completas por tono. No se interpola el nombre del token.
    $estilos = [
        'ok' => 'border-status-ok/25 bg-status-ok/10 text-status-ok-fg',
        'info' => 'border-status-info/25 bg-status-info/10 text-status-info-fg',
        'warn' => 'border-status-warn/25 bg-status-warn/10 text-status-warn-fg',
        'danger' => 'border-status-danger/25 bg-status-danger/10 text-status-danger-fg',
        'idle' => 'border-status-idle/25 bg-status-idle/10 text-status-idle-fg',
        'none' => 'border-transparent text-muted-foreground',
    ];

    $puntos = [
        'ok' => 'bg-status-ok',
        'info' => 'bg-status-info',
        'warn' => 'bg-status-warn',
        'danger' => 'bg-status-danger',
        'idle' => 'bg-status-idle',
        'none' => 'bg-muted-foreground/40',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
    $estilos[$tono] ?? $estilos['idle'],
]) }}>
    @if ($punto)
        <span class="size-1.5 shrink-0 rounded-full {{ $puntos[$tono] ?? $puntos['idle'] }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
