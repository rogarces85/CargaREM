{{--
    Detalle de un resultado del Validador (RF-040.5).

    Se abre como panel lateral en escritorio y bajo la tabla en móvil. Lo que
    muestra, en el orden en que se lee:

    1. Qué regla es y en qué hoja.
    2. El mensaje del catálogo, segmentado (REM | sección | detalle).
    3. La comparación: valor registrado, el operador en palabras, valor esperado.
    4. La diferencia, si la hay.
    5. Las celdas leídas: lo que hay que abrir en el Excel para corregir.
    6. La evaluación literal del motor.

    Vive fuera de la tabla a propósito: una fila no tiene espacio para esto, y
    quien corrige necesita ver todo junto.
--}}
@php
    use App\Validador\DetalleResultado;

    $estado = $seleccionado['estado'] ?? '';
    $severidad = $seleccionado['severidad'] ?? '';
    $esHallazgo = $estado === 'HALLAZGO';

    $etiquetaEstado = [
        'CUMPLE' => 'Cumple',
        'HALLAZGO' => 'No cumple',
        'OMITIDA' => 'No aplica',
        'ERROR_TECNICO' => 'Fuera de rango',
    ][$estado] ?? $estado;

    $tono = match (true) {
        $estado === 'HALLAZGO' && $severidad === 'ERROR' => 'danger',
        $estado === 'HALLAZGO' => 'warn',
        $estado === 'CUMPLE' => 'ok',
        $estado === 'ERROR_TECNICO' => 'warn',
        default => 'idle',
    };

    $motivos = [
        'AMBOS_CERO' => 'Ambos lados de la comparación están en cero o vacíos: no hay datos que comparar.',
        'CONDICION_PREVIA_NO_CUMPLE' => 'La condición que habilita la regla no se cumple, así que no hay datos que comparar.',
        'SECCION_SIN_ACTIVIDAD' => 'Esta sección le corresponde al establecimiento, pero no trae registro este mes.',
        'HOJA_AUSENTE' => 'El archivo no trae la hoja que esta regla necesita.',
    ];

    $detalle = $seleccionado;
@endphp

<aside class="card space-y-5 p-5" aria-label="Detalle del resultado seleccionado">
    {{-- Encabezado: regla, estado y cierre. --}}
    <div class="flex items-start justify-between gap-3 border-b border-border pb-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">{{ $detalle['regla'] ?? '—' }}</code>
                <x-ui.badge :tono="$tono">{{ $etiquetaEstado }}</x-ui.badge>
                @if ($esHallazgo)
                    <span class="text-xs font-medium {{ $severidad === 'ERROR' ? 'text-status-danger-fg' : 'text-status-warn-fg' }}">
                        {{ $severidad === 'ERROR' ? 'Error' : 'Por revisar' }}
                    </span>
                @endif
            </div>

            <h3 class="mt-1.5 text-sm font-semibold">
                Hoja REM {{ $detalle['hoja'] ?? '—' }}
                @if (! empty($detalle['hoja2']) && ($detalle['hoja2'] ?? '') !== ($detalle['hoja'] ?? ''))
                    <span class="font-normal text-muted-foreground">→ {{ $detalle['hoja2'] }}</span>
                @endif
            </h3>
        </div>

        <a href="{{ route('validador.show', array_merge([$ejecucion], $filtro->conDetalle(null)->aConsulta())) }}"
           class="btn btn-ghost btn-sm shrink-0" aria-label="Cerrar el detalle">
            <x-ui.icon name="x" class="size-4" />
        </a>
    </div>

    {{-- Mensaje del catálogo, segmentado. --}}
    <div class="space-y-3">
        @foreach (DetalleResultado::segmentar((string) ($detalle['mensaje'] ?? '')) as $parte)
            <div>
                @if ($parte['rem'] !== '')
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {{ $parte['rem'] }}
                    </p>
                @endif

                @if ($parte['secciones'] !== '')
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ $parte['secciones'] }}</p>
                @endif

                <p class="mt-1 text-sm leading-relaxed">{{ $parte['detalle'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Comparación: lo que la regla leyó y lo que esperaba. --}}
    <div class="overflow-hidden rounded-lg border border-border">
        <div class="flex items-baseline justify-between gap-4 px-3.5 py-3">
            <div class="min-w-0">
                <p class="text-xs text-muted-foreground">Valor registrado</p>
                <p class="mt-0.5 font-mono text-sm break-words">{{ $detalle['expresion1'] ?? '—' }}</p>
                <p class="mt-0.5 font-mono text-xs text-muted-foreground">hoja {{ $detalle['hoja'] ?? '—' }}</p>
            </div>
            <p class="shrink-0 font-mono text-lg font-semibold tabular-nums">
                {{ DetalleResultado::numero($detalle['valor1'] ?? null) }}
            </p>
        </div>

        <div class="border-y border-border bg-muted px-3.5 py-1.5 text-xs text-muted-foreground">
            {{ DetalleResultado::leerOperador((string) ($detalle['operador'] ?? '')) }}
        </div>

        <div class="flex items-baseline justify-between gap-4 px-3.5 py-3">
            <div class="min-w-0">
                <p class="text-xs text-muted-foreground">Valor esperado</p>
                <p class="mt-0.5 font-mono text-sm break-words">{{ $detalle['expresion2'] ?? '—' }}</p>
                <p class="mt-0.5 font-mono text-xs text-muted-foreground">
                    hoja {{ $detalle['hoja2'] ?? $detalle['hoja'] ?? '—' }}
                </p>
            </div>
            <p class="shrink-0 font-mono text-lg tabular-nums text-muted-foreground">
                {{ DetalleResultado::numero($detalle['valor2'] ?? null) }}
            </p>
        </div>

        @if (($detalle['diferencia'] ?? null) !== null && (float) ($detalle['diferencia'] ?? 0) !== 0.0)
            <div class="flex items-baseline justify-between gap-4 border-t border-border px-3.5 py-2.5">
                <span class="text-xs text-muted-foreground">Diferencia</span>
                <span class="font-mono text-sm font-semibold tabular-nums {{ $severidad === 'ERROR' ? 'text-status-danger-fg' : 'text-status-warn-fg' }}">
                    {{ DetalleResultado::diferencia((float) $detalle['diferencia']) }}
                </span>
            </div>
        @endif
    </div>

    {{-- Por qué no se evaluó, cuando aplica. --}}
    @if (! empty($detalle['omision']))
        <p class="rounded-lg border border-border bg-muted px-3.5 py-3 text-sm text-muted-foreground">
            {{ $motivos[$detalle['omision']] ?? $detalle['omision'] }}
        </p>
    @endif

    {{-- Celdas leídas: lo que hay que abrir en el Excel. --}}
    @if (($detalle['celdas'] ?? []) !== [])
        <div>
            <h4 class="text-xs font-medium text-muted-foreground">Celdas leídas</h4>
            <ul class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($detalle['celdas'] as $celda)
                    <li class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground">
                        {{ $celda }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Evaluación literal del motor. --}}
    @if (! empty($detalle['evidencia']))
        <div>
            <h4 class="text-xs font-medium text-muted-foreground">Evaluación</h4>
            <p class="mt-1.5 font-mono text-xs leading-relaxed break-words text-muted-foreground">
                {{ $detalle['evidencia'] }}
            </p>
        </div>
    @endif

    @if (($detalle['bloquea'] ?? true) === false)
        <p class="text-xs leading-relaxed text-muted-foreground">
            Hay que corregirlo, pero este error no rechaza el REM.
        </p>
    @endif
</aside>
