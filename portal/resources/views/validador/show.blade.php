@extends('layouts.panel')

@section('titulo', 'Revisión del Validador')

@section('contenido')
    @php
        $resumen = $ejecucion->resumen();
        $metadatos = $ejecucion->metadatos();
        $error = $resultado['error'] ?? null;
        $detalles = $resultado['detalles'] ?? [];

        $tonos = [
            'APROBADO'            => 'ok',
            'CON_OBSERVACIONES'   => 'warn',
            'RECHAZADO'           => 'danger',
        ];

        $etiquetas = [
            'APROBADO'          => 'Aprobado',
            'CON_OBSERVACIONES' => 'Con observaciones',
            'RECHAZADO'         => 'Rechazado',
        ];
    @endphp

    {{-- Con el detalle abierto la página necesita más ancho: el detalle ocupa
         todo el ancho bajo la tabla. --}}
    <div class="mx-auto space-y-6 {{ $seleccionado !== null ? 'max-w-6xl' : 'max-w-5xl' }}">

        <x-ui.page-header
            :titulo="$ejecucion->nombre_original"
            :subtitulo="'Revisado el ' . $ejecucion->created_at?->format('d-m-Y H:i')
                . ' · catálogo ' . $ejecucion->reglas_version
                . ' · ' . number_format($ejecucion->duracion_ms / 1000, 1, ',', '.') . ' s'"
            :migas="['Panel' => route('panel'), 'Validador' => route('validador.index'), 'Revisión' => null]"
        >
            <x-slot:acciones>
                @if (! $error)
                    <a href="{{ route('validador.reporte', $ejecucion) }}" class="btn btn-secondary">
                        <x-ui.icon name="file-spreadsheet" class="size-4" />
                        Descargar reporte
                    </a>
                @endif

                <a href="{{ route('validador.index') }}" class="btn btn-ghost">
                    <x-ui.icon name="arrow-right" class="size-4 rotate-180" />
                    Volver
                </a>
            </x-slot:acciones>
        </x-ui.page-header>

        @if ($error)
            <x-ui.alert tono="danger">
                <p class="font-medium">{{ $error }}</p>

                @if ($detalles !== [])
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-xs">
                        @foreach ($detalles as $detalle)
                            <li>{{ $detalle }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.alert>
        @else
            {{-- Veredicto grande: es lo primero que se mira. --}}
            <x-ui.card class="flex flex-wrap items-center justify-between gap-4 p-5">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 items-center justify-center rounded-xl
                        @class([
                            'bg-status-ok/10 text-status-ok-fg' => $ejecucion->veredicto() === 'APROBADO',
                            'bg-status-warn/10 text-status-warn-fg' => $ejecucion->veredicto() === 'CON_OBSERVACIONES',
                            'bg-status-danger/10 text-status-danger-fg' => $ejecucion->veredicto() === 'RECHAZADO',
                        ])">
                        <x-ui.icon :name="$ejecucion->veredicto() === 'APROBADO' ? 'circle-check' : 'triangle-exclamation-point'" class="size-5" />
                    </span>

                    <div>
                        <p class="text-base font-semibold">{{ $etiquetas[$ejecucion->veredicto()] ?? $ejecucion->veredicto() }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ $resumen['cumplen'] ?? 0 }} regla(s) cumplen de {{ $resumen['evaluadas'] ?? 0 }} evaluadas
                            ({{ $resumen['porcentaje'] ?? 0 }} %).
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 text-xs">
                    @if (($resumen['errores'] ?? 0) > 0)
                        <x-ui.badge tono="danger">{{ $resumen['errores'] }} error(es)</x-ui.badge>
                    @endif
                    @if (($resumen['revisar'] ?? 0) > 0)
                        <x-ui.badge tono="warn">{{ $resumen['revisar'] }} a revisar</x-ui.badge>
                    @endif
                    @if (($resumen['omitidas'] ?? 0) > 0)
                        <x-ui.badge tono="idle">{{ $resumen['omitidas'] }} omitida(s)</x-ui.badge>
                    @endif
                    @if (($resumen['fueraDeAlcance'] ?? 0) > 0)
                        <x-ui.badge tono="idle">{{ $resumen['fueraDeAlcance'] }} fuera de alcance</x-ui.badge>
                    @endif
                </div>
            </x-ui.card>

            {{-- Contexto leído del archivo. --}}
            <x-ui.card class="p-5">
                <h2 class="text-sm font-semibold">Qué se revisó</h2>

                <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Establecimiento</dt>
                        <dd class="text-end font-medium">
                            {{ $metadatos['codigo'] ?? '—' }}
                            @if (! empty($metadatos['establecimiento']))
                                · {{ $metadatos['establecimiento'] }}
                            @endif
                        </dd>
                    </div>

                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Tipo</dt>
                        <dd class="text-end font-medium">{{ $metadatos['tipo'] ?? '—' }}</dd>
                    </div>

                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Serie</dt>
                        <dd class="text-end font-medium">{{ $metadatos['serie'] ?? '—' }}</dd>
                    </div>

                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Mes</dt>
                        <dd class="text-end font-medium">{{ $metadatos['mes'] ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($ejecucion->resultado['errorVersion'] ?? null)
                    <x-ui.alert tono="warn" class="mt-4">
                        <span class="font-medium">Versión de plantilla.</span>
                        {{ $ejecucion->resultado['errorVersion'] }}
                    </x-ui.alert>
                @endif

                @if (($ejecucion->resultado['hojasAusentes'] ?? []) !== [])
                    <x-ui.alert tono="info" class="mt-4">
                        <span class="font-medium">Hojas que el archivo no trae:</span>
                        {{ implode(', ', $ejecucion->resultado['hojasAusentes']) }}.
                        Las reglas que las necesitan no se pudieron evaluar.
                    </x-ui.alert>
                @endif

                @if ($ejecucion->resultado['serieSoloPortada'] ?? false)
                    <x-ui.alert tono="info" class="mt-4">
                        Esta serie no tiene reglas de contenido en el catálogo: solo se revisó la portada.
                    </x-ui.alert>
                @endif
            </x-ui.card>

            {{-- Resultados y filtros (RF-040.4). --}}
            <div class="space-y-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-sm font-semibold">Resultados</h2>
                    <p class="text-xs text-muted-foreground" aria-live="polite">
                        {{ count($visibles) }} de {{ $total }} resultados
                        @if ($filtro->hayFiltrosActivos())
                            · <a href="{{ route('validador.show', $ejecucion) }}" class="underline underline-offset-2">Ver todo</a>
                        @endif
                    </p>
                </div>

                {{-- Filtros: buscador, hoja y dos grupos de chips. --}}
                <form method="GET" action="{{ route('validador.show', $ejecucion) }}"
                      class="flex flex-wrap items-center gap-x-4 gap-y-3">
                    {{-- Los chips se envían como campos ocultos para no perderlos al buscar. --}}
                    @foreach ($filtro->severidades as $severidad)
                        <input type="hidden" name="severidades[]" value="{{ $severidad }}">
                    @endforeach
                    @foreach ($filtro->estados as $estado)
                        <input type="hidden" name="estados[]" value="{{ $estado }}">
                    @endforeach

                    <div class="relative min-w-56 flex-1">
                        <label for="q" class="sr-only">Buscar en los resultados</label>
                        <x-ui.icon name="search" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input id="q" name="q" type="search" value="{{ $filtro->busqueda }}"
                               placeholder="Buscar celda, hoja, regla o texto del resultado"
                               class="input ps-9">
                    </div>

                    <div class="flex items-center gap-1.5">
                        <label for="hoja" class="text-xs text-muted-foreground">Hoja</label>
                        <select id="hoja" name="hoja" class="input">
                            <option value="">Todas</option>
                            @foreach ($hojas as $h)
                                <option value="{{ $h['hoja'] }}" @selected($filtro->hoja === $h['hoja'])>
                                    {{ $h['hoja'] }} ({{ $h['hallazgos'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn btn-secondary">Buscar</button>
                </form>

                {{-- Chips de estado y severidad: son enlaces, así que funcionan sin JavaScript. --}}
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Estado">
                        @foreach (\App\Validador\FiltroResultados::ESTADOS as $estado)
                            @php $activo = in_array($estado, $filtro->estados, true); @endphp
                            <a href="{{ route('validador.show', array_merge([$ejecucion], $filtro->conEstado($estado)->aConsulta())) }}"
                               aria-pressed="{{ $activo ? 'true' : 'false' }}"
                               class="btn btn-sm {{ $activo ? 'btn-primary' : 'btn-ghost' }}">
                                {{ ['HALLAZGO' => 'No cumple', 'CUMPLE' => 'Cumple', 'OMITIDA' => 'No aplica', 'ERROR_TECNICO' => 'Fuera de rango'][$estado] }}
                            </a>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Severidad">
                        @foreach (\App\Validador\FiltroResultados::SEVERIDADES as $severidad)
                            @php $activo = in_array($severidad, $filtro->severidades, true); @endphp
                            <a href="{{ route('validador.show', array_merge([$ejecucion], $filtro->conSeveridad($severidad)->aConsulta())) }}"
                               aria-pressed="{{ $activo ? 'true' : 'false' }}"
                               class="btn btn-sm {{ $activo ? 'btn-primary' : 'btn-ghost' }}">
                                {{ ['ERROR' => 'Error', 'REVISAR' => 'Por revisar', 'INDICADOR' => 'Indicador'][$severidad] }}
                            </a>
                        @endforeach
                    </div>
                </div>

                @if ($visibles === [])
                    <x-ui.alert tono="ok">
                        @if ($filtro->hayFiltrosActivos())
                            Ningún resultado coincide con el filtro.
                            <a href="{{ route('validador.show', $ejecucion) }}" class="underline underline-offset-2">Ver todo</a>.
                        @else
                            Ninguna regla encontró problemas en este archivo. Recuerde que esto no reemplaza la
                            validación del portal ni la revisión del Equipo REM.
                        @endif
                    </x-ui.alert>
                @else
                    <div class="space-y-4">
                        <x-ui.card class="min-w-0 p-0">
                            <div class="overflow-x-auto">
                                <table class="tabla tabla-registros">
                                    <caption class="sr-only">Resultados del Validador. Cada fila abre su detalle.</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Hoja</th>
                                            <th scope="col">Regla</th>
                                            <th scope="col">Resultado</th>
                                            <th scope="col">Comparación</th>
                                            <th scope="col">Mensaje</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($visibles as $r)
                                            @php $abierto = ($filtro->detalle ?? null) === ($r['regla'] ?? null); @endphp
                                            <tr @class(['bg-muted/60' => $abierto])>
                                                <td class="whitespace-nowrap text-muted-foreground">
                                                    {{ $r['hoja'] ?? '—' }}
                                                    @if (! empty($r['hoja2']) && ($r['hoja2'] ?? '') !== ($r['hoja'] ?? ''))
                                                        → {{ $r['hoja2'] }}
                                                    @endif
                                                </td>

                                                <td class="whitespace-nowrap">
                                                    {{-- La regla es el enlace: es lo que identifica la fila. --}}
                                                    <a href="{{ route('validador.show', array_merge([$ejecucion], $filtro->conDetalle($r['regla'] ?? null)->aConsulta())) }}#detalle"
                                                       class="font-mono text-xs underline-offset-2 hover:underline"
                                                       @if ($abierto) aria-current="true" @endif>
                                                        {{ $r['regla'] ?? '—' }}
                                                    </a>
                                                </td>

                                                <td class="whitespace-nowrap">
                                                    {{-- Una sola columna: la severidad solo
                                                         significa algo cuando hay hallazgo; el
                                                         resto de los casos se lee por su estado. --}}
                                                    @php
                                                        $estadoFila = $r['estado'] ?? '';
                                                        $esHallazgoFila = $estadoFila === 'HALLAZGO';

                                                        if ($esHallazgoFila) {
                                                            $etiqueta = ($r['severidad'] ?? '') === 'ERROR' ? 'Error' : 'Por revisar';
                                                        } else {
                                                            $etiqueta = [
                                                                'CUMPLE' => 'Cumple',
                                                                'OMITIDA' => 'No aplica',
                                                                'ERROR_TECNICO' => 'Fuera de rango',
                                                            ][$estadoFila] ?? $estadoFila;
                                                        }

                                                        $tono = match (true) {
                                                            $esHallazgoFila && ($r['severidad'] ?? '') === 'ERROR' => 'danger',
                                                            $esHallazgoFila => 'warn',
                                                            $estadoFila === 'CUMPLE' => 'ok',
                                                            $estadoFila === 'ERROR_TECNICO' => 'warn',
                                                            default => 'idle',
                                                        };
                                                    @endphp
                                                    <x-ui.badge :tono="$tono">{{ $etiqueta }}</x-ui.badge>
                                                </td>

                                                <td class="font-mono text-xs text-muted-foreground">
                                                    <span class="block max-w-[26ch] break-words">{{ $r['expresion1'] ?? '' }} {{ $r['operador'] ?? '' }} {{ $r['expresion2'] ?? '' }}</span>
                                                </td>

                                                <td class="celda-mensaje">
                                                    <div class="mensaje-regla">
                                                        @foreach (\App\Validador\DetalleResultado::segmentar((string) ($r['mensaje'] ?? '')) as $parte)
                                                            <div>
                                                                @if ($parte['rem'] !== '')
                                                                    <span class="mr-1.5 font-mono text-[11px] text-muted-foreground">{{ $parte['rem'] }}</span>
                                                                @endif
                                                                @if ($parte['secciones'] !== '')
                                                                    <span class="text-[11px] text-muted-foreground">{{ $parte['secciones'] }}</span>
                                                                @endif
                                                                <span class="block">{{ $parte['detalle'] }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                    @if (! empty($r['evidencia']))
                                                        <p class="evidencia-regla font-mono break-words">
                                                            {{ $r['evidencia'] }}
                                                        </p>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-ui.card>

                        {{-- El detalle va debajo, a todo el ancho: al lado la
                             tabla se queda sin espacio para seis columnas y el
                             mensaje deja de leerse. El enlace salta aquí. --}}
                        @if ($seleccionado !== null)
                            <div id="detalle" class="scroll-mt-24">
                                <x-validador.detalle :seleccionado="$seleccionado" :ejecucion="$ejecucion" :filtro="$filtro" />
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <x-ui.alert tono="info">
                Este resultado es una ayuda a la calidad de la estadística: <strong>no cambió el estado de ningún
                envío</strong> ni se guardó en el expediente del período.
            </x-ui.alert>
        @endif

    </div>
@endsection
