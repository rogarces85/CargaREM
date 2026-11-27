@extends('layouts.panel')

@section('titulo', 'Panel')

@section('contenido')
    @php
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
                'titulo' => 'Equipo REM',
                'texto' => 'Bandeja de sus establecimientos asignados. Observar una serie, aprobar o pedir una nueva subida.',
                'accion' => 'Abrir bandeja',
            ];
        }

        if (auth()->user()->canAny(['envios.ver.propio', 'envios.ver.ss'])) {
            $accesos[] = [
                'href' => route('completitud.index'),
                'icono' => 'chart-column',
                'tono' => 'ok',
                'titulo' => 'Completitud',
                'texto' => 'Qué entregó cada establecimiento, serie por serie, y cuánto falta para cerrar el período.',
                'accion' => 'Ver completitud',
            ];
        }

        if (auth()->user()->canAny(['aperturas.solicitar', 'aperturas.resolver'])) {
            $accesos[] = [
                'href' => route('aperturas.index'),
                'icono' => 'calendar-plus',
                'tono' => 'warn',
                'titulo' => auth()->user()->can('aperturas.resolver') ? 'Aperturas de plazo' : 'Apertura de plazo',
                'texto' => auth()->user()->can('aperturas.resolver')
                    ? 'Solicitudes del equipo y ventanas que usted abre para cargar fuera de plazo.'
                    : 'Pida abrir un período que ya cerró. La decisión es del administrador.',
                'accion' => 'Ver aperturas',
            ];
        }

        if (auth()->user()->can('validador.usar')) {
            $accesos[] = [
                'href' => route('validador.index'),
                'icono' => 'shield-check',
                'tono' => 'info',
                'titulo' => 'Validador',
                'texto' => 'Revise un Excel REM con las reglas de calidad de la Unidad. No cambia el estado de ningún envío.',
                'accion' => 'Abrir validador',
            ];
        }

        $cajas = [
            'info' => 'bg-status-info/10 text-status-info-fg',
            'ok' => 'bg-status-ok/10 text-status-ok-fg',
            'warn' => 'bg-status-warn/10 text-status-warn-fg',
            'danger' => 'bg-status-danger/10 text-status-danger-fg',
        ];

        $nombreRol = \App\Support\RolUi::etiqueta(auth()->user()->getRoleNames()->first());
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            titulo="Panel"
            :subtitulo="$nombreRol . ' · Servicio de Salud Osorno · ' . now()->translatedFormat('F Y')"
        />

        @foreach ($panel['avisos'] as $aviso)
            <x-ui.alert tono="warn">{{ $aviso }}</x-ui.alert>
        @endforeach

        {{-- Indicadores (RF-048). Salen de una consulta real: si un envío no
             existe, la tarjeta muestra 0 y no un número de relleno. --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($panel['tarjetas'] as $tarjeta)
                <x-ui.stat
                    :icono="$tarjeta['icono']"
                    :rotulo="$tarjeta['rotulo']"
                    :valor="$tarjeta['valor']"
                    :tono="$tarjeta['tono']"
                    :detalle="$tarjeta['detalle']"
                />
            @endforeach
        </div>

        {{-- Gráficos con su respaldo en texto (principio 30). Sin JavaScript, o
             con el gráfico bloqueado, la cifra sigue estando en la tabla. --}}
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($panel['graficos'] as $grafico)
                <x-ui.card class="p-5">
                    <h2 class="text-sm font-semibold">{{ $grafico['titulo'] }}</h2>

                    <p class="mt-1 text-xs text-muted-foreground">{{ $grafico['resumen'] }}</p>

                    <div class="mt-3">
                        <x-ui.chart
                            :titulo="$grafico['titulo']"
                            :tipo="$grafico['tipo']"
                            :series="$grafico['series']"
                            :categorias="$grafico['categorias']"
                            :valores="$grafico['valores']"
                            :resumen="$grafico['resumen']"
                        />
                    </div>

                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs text-muted-foreground">
                            Ver los datos en tabla
                        </summary>

                        <div class="mt-2 overflow-x-auto">
                            <table class="tabla">
                                <caption class="sr-only">{{ $grafico['titulo'] }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Categoría</th>
                                        <th scope="col" class="text-end">{{ $grafico['series'][0] ?? 'Total' }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($grafico['categorias'] as $indice => $categoria)
                                        <tr>
                                            <td>{{ \App\Support\EstadoUi::etiqueta($categoria) }}</td>
                                            <td class="text-end tabular">{{ $grafico['valores'][$indice] ?? 0 }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </x-ui.card>
            @endforeach
        </div>

        {{-- Accesos por rol. --}}
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
                    ['icono' => 'circle-check', 'titulo' => 'Se avisa', 'texto' => 'El Equipo REM asignado se entera de la subida: una serie suelta o el envío completo.'],
                    ['icono' => 'clipboard-check', 'titulo' => 'Se revisa', 'texto' => 'El revisor aprueba la estadística completa, observa una serie o pide que se vuelva a subir.'],
                    ['icono' => 'clock', 'titulo' => 'Se cuida el plazo', 'texto' => 'El plazo es el quinto día hábil del mes siguiente. Fuera de plazo solo se carga con una apertura aprobada.'],
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
