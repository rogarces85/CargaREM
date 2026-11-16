@extends('layouts.panel')

@section('titulo', 'Panel')

@section('contenido')
    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            titulo="Panel"
            :subtitulo="'Servicio de Salud Osorno · ' . now()->translatedFormat('F Y')"
        />

        @php
            /*
             * Accesos por rol. No se muestran números inventados: el panel es un
             * punto de entrada, y las cifras viven en cada módulo, donde salen de
             * una consulta real. Un indicador falso en la portada se lee como
             * verdadero y nadie lo verifica.
             */
            $accesos = [];

            if (auth()->user()->canAny(['envios.ver.propio', 'envios.ver.ss'])) {
                $accesos[] = [
                    'href' => route('envios.index'),
                    'icono' => 'file-up',
                    'tono' => 'info',
                    'titulo' => 'Envíos',
                    'texto' => 'Crear un envío del período, subir cada serie y seguir su estado hasta la aprobación.',
                    'accion' => 'Abrir envíos',
                ];
            }

            if (auth()->user()->can('envios.ver.ss')) {
                $accesos[] = [
                    'href' => route('revision.index'),
                    'icono' => 'clipboard-check',
                    'tono' => 'warn',
                    'titulo' => 'Revisión',
                    'texto' => 'Bandeja de los establecimientos del Servicio. Observar una serie o aprobar la estadística completa.',
                    'accion' => 'Abrir revisión',
                ];
            }

            if (auth()->user()->canAny(['envios.ver.propio', 'envios.ver.ss'])) {
                $accesos[] = [
                    'href' => route('completitud.index'),
                    'icono' => 'grid-3x3',
                    'tono' => 'ok',
                    'titulo' => 'Completitud',
                    'texto' => 'Qué entregó cada establecimiento, serie por serie, y cuánto falta para cerrar el período.',
                    'accion' => 'Ver completitud',
                ];
            }

            $cajas = [
                'info' => 'bg-status-info/10 text-status-info-fg',
                'ok' => 'bg-status-ok/10 text-status-ok-fg',
                'warn' => 'bg-status-warn/10 text-status-warn-fg',
                'danger' => 'bg-status-danger/10 text-status-danger-fg',
            ];
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($accesos as $acceso)
                <a href="{{ $acceso['href'] }}"
                   class="group flex flex-col rounded-xl border border-border bg-card p-5 transition-colors hover:border-primary/40 hover:bg-accent/30">
                    <span class="flex size-10 items-center justify-center rounded-lg {{ $cajas[$acceso['tono']] }}">
                        <x-ui.icon :name="$acceso['icono']" class="size-5" />
                    </span>

                    <h2 class="mt-4 text-base font-semibold">{{ $acceso['titulo'] }}</h2>
                    <p class="mt-1 flex-1 text-sm text-muted-foreground">{{ $acceso['texto'] }}</p>

                    <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-primary">
                        {{ $acceso['accion'] }}
                        <x-ui.icon name="arrow-right" class="size-4 transition-transform group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>

        <x-ui.card class="p-5">
            <h2 class="text-sm font-semibold">Cómo funciona el envío</h2>

            <ol class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['icono' => 'file-up', 'titulo' => 'Se sube', 'texto' => 'Un archivo por serie exigida. Subir de nuevo crea una versión nueva; la anterior queda en el historial.'],
                    ['icono' => 'circle-check', 'titulo' => 'Se valida', 'texto' => 'El sistema revisa el archivo antes de dejarlo pasar. Un hallazgo bloqueante impide enviarlo a revisión.'],
                    ['icono' => 'clipboard-check', 'titulo' => 'Se revisa', 'texto' => 'El revisor aprueba la estadística completa u observa una serie concreta.'],
                    ['icono' => 'circle-x', 'titulo' => 'Se corrige', 'texto' => 'Una observación vuelve al establecimiento. Se resuelve sola cuando el encargado sube la corrección.'],
                ] as $paso)
                    <li class="flex gap-3">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <x-ui.icon :name="$paso['icono']" class="size-4" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ $paso['titulo'] }}</span>
                            <span class="mt-0.5 block text-xs text-muted-foreground">{{ $paso['texto'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>

    </div>
@endsection
