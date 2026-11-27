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

    <div class="mx-auto max-w-5xl space-y-6">

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

            {{-- Hallazgos. --}}
            <div class="space-y-2">
                <h2 class="text-sm font-semibold">
                    Hallazgos
                    <span class="font-normal text-muted-foreground">
                        {{ count($hallazgos) }} en total
                    </span>
                </h2>

                @if ($hallazgos === [])
                    <x-ui.alert tono="ok">
                        Ninguna regla encontró problemas en este archivo. Recuerde que esto no reemplaza la
                        validación del portal ni la revisión del Equipo REM.
                    </x-ui.alert>
                @else
                    <x-ui.card class="p-0">
                        <div class="overflow-x-auto">
                            <table class="tabla">
                                <caption class="sr-only">Hallazgos del Validador</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Regla</th>
                                        <th scope="col">Severidad</th>
                                        <th scope="col">Hoja</th>
                                        <th scope="col">Comparación</th>
                                        <th scope="col">Mensaje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($hallazgos as $hallazgo)
                                        <tr>
                                            <td>
                                                <code class="rounded bg-muted px-1 py-0.5 text-[11px]">{{ $hallazgo['regla'] }}</code>
                                            </td>

                                            <td>
                                                <x-ui.badge :tono="$hallazgo['severidad'] === 'ERROR' ? 'danger' : 'warn'">
                                                    {{ $hallazgo['severidad'] }}
                                                </x-ui.badge>
                                            </td>

                                            <td class="text-muted-foreground">
                                                {{ $hallazgo['hoja'] ?? '—' }}
                                                @if (! empty($hallazgo['hoja2']) && $hallazgo['hoja2'] !== $hallazgo['hoja'])
                                                    → {{ $hallazgo['hoja2'] }}
                                                @endif
                                            </td>

                                            <td class="whitespace-nowrap font-mono text-xs text-muted-foreground">
                                                {{ $hallazgo['expresion1'] ?? '' }}
                                                {{ $hallazgo['operador'] ?? '' }}
                                                {{ $hallazgo['expresion2'] ?? '' }}
                                            </td>

                                            <td>
                                                <span class="block">{{ $hallazgo['mensaje'] }}</span>
                                                @if (! empty($hallazgo['evidencia']))
                                                    <span class="mt-0.5 block text-xs text-muted-foreground">
                                                        {{ $hallazgo['evidencia'] }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-ui.card>
                @endif
            </div>

            <x-ui.alert tono="info">
                Este resultado es una ayuda a la calidad de la estadística: <strong>no cambió el estado de ningún
                envío</strong> ni se guardó en el expediente del período.
            </x-ui.alert>
        @endif

    </div>
@endsection
