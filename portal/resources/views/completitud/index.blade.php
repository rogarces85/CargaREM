@extends('layouts.panel')

@section('titulo', 'Completitud')

@section('contenido')
    @php
        $resumen = $matriz['resumen'];
        $avance = $resumen['exigidas'] > 0 ? (int) round($resumen['aprobadas'] * 100 / $resumen['exigidas']) : 0;

        /*
         * Cuántas celdas hay de cada estado, por serie. Solo cuentan las celdas
         * que no son «no aplica»: así el gráfico compara contra lo que de verdad
         * se pide. Un establecimiento al que no se le exige la serie no debe
         * contar como atraso de esa serie.
         */
        $conteosPorSerie = [];

        foreach ($matriz['filas'] as $fila) {
            foreach ($matriz['series'] as $serie) {
                $estado = $fila['celdas'][$serie->id]['estado'] ?? 'no_aplica';

                if ($estado === 'no_aplica') {
                    continue;
                }

                $conteosPorSerie[$serie->id][$estado] = ($conteosPorSerie[$serie->id][$estado] ?? 0) + 1;
            }
        }

        /*
         * Estilos por estado de celda. Clases completas, nunca construidas: el
         * escáner de Tailwind lee el archivo como texto y no resuelve
         * `bg-status-{{ $x }}` (constitución, principio 29).
         *
         * `relleno` es el mismo estado en versión sólida, para los segmentos de
         * la barra apilada. El orden de este mapa es el orden de apilado.
         */
        $celdas = [
            'aprobado' => [
                'clase' => 'border-status-ok/35 bg-status-ok/15 text-status-ok-fg',
                'relleno' => 'bg-status-ok',
                'icono' => 'check',
                'texto' => 'Aprobado',
            ],
            'en_proceso' => [
                'clase' => 'border-status-warn/35 bg-status-warn/15 text-status-warn-fg',
                'relleno' => 'bg-status-warn',
                'icono' => 'clock',
                'texto' => 'En proceso',
            ],
            'con_errores' => [
                'clase' => 'border-status-danger/35 bg-status-danger/15 text-status-danger-fg',
                'relleno' => 'bg-status-danger',
                'icono' => 'x',
                'texto' => 'Con errores',
            ],
            'sin_archivo' => [
                'clase' => 'border-status-idle/35 bg-status-idle/10 text-status-idle-fg',
                'relleno' => 'bg-status-idle/75',
                'icono' => 'minus',
                'texto' => 'Sin archivo',
            ],
            'sin_envio' => [
                'clase' => 'border-border bg-muted/60 text-muted-foreground',
                'relleno' => 'bg-status-idle/35',
                'icono' => 'minus',
                'texto' => 'Sin envío',
            ],
            'no_aplica' => [
                'clase' => 'border-transparent text-muted-foreground/30',
                'relleno' => null,
                'icono' => null,
                'texto' => 'No aplica',
            ],
        ];

        // Estados que se apilan en el gráfico: todos menos «no aplica».
        $apilables = array_filter($celdas, fn (array $c) => $c['relleno'] !== null);
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            titulo="Completitud del período"
            subtitulo="Qué entregó cada establecimiento y en qué estado. El semáforo se lee celda por celda."
            :migas="['Panel' => route('panel'), 'Completitud' => null]"
        />

        {{-- Indicadores --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat icono="building" rotulo="Establecimientos" :valor="$resumen['establecimientos']" tono="info" />
            <x-ui.stat icono="circle-check" rotulo="Series aprobadas" :valor="$resumen['aprobadas']" tono="ok" />
            <x-ui.stat icono="list" rotulo="Series exigidas" :valor="$resumen['exigidas']" tono="idle" />

            <x-ui.card>
                <div class="flex items-start justify-between gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-status-ok/10 text-status-ok-fg">
                        <x-ui.icon name="percent" class="size-4" />
                    </span>
                    <span class="text-2xl font-bold tabular leading-none">{{ $avance }}%</span>
                </div>

                <p class="mt-3 text-sm font-medium">Avance del período</p>

                <x-ui.progress
                    :valor="$resumen['aprobadas']"
                    :max="max(1, $resumen['exigidas'])"
                    :tono="$avance >= 100 ? 'ok' : ($avance >= 50 ? 'warn' : 'danger')"
                    etiqueta="Avance del período"
                    class="mt-2"
                />
            </x-ui.card>
        </div>

        {{-- Filtros --}}
        <x-ui.card class="p-5">
            <form method="GET" action="{{ route('completitud.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[8rem_8rem_1fr_auto] lg:items-end">
                <x-ui.field nombre="anio" etiqueta="Año">
                    <input type="number" name="anio" id="anio" value="{{ $anio }}" min="2000" max="2100" class="input tabular">
                </x-ui.field>

                <x-ui.field nombre="mes" etiqueta="Mes">
                    <select name="mes" id="mes" class="select">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected($mes === $m)>{{ sprintf('%02d', $m) }}</option>
                        @endfor
                    </select>
                </x-ui.field>

                <x-ui.field nombre="establecimiento_id" etiqueta="Establecimiento">
                    <select name="establecimiento_id" id="establecimiento_id" class="select">
                        <option value="">Todos</option>
                        @foreach ($establecimientos as $establecimiento)
                            <option value="{{ $establecimiento->id }}" @selected(($filtros['establecimiento_id'] ?? '') == $establecimiento->id)>
                                {{ $establecimiento->codigo }} — {{ $establecimiento->nombre }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <button type="submit" class="btn btn-primary h-10">
                    <x-ui.icon name="search" class="size-4" />
                    Ver
                </button>
            </form>
        </x-ui.card>

        {{-- Avance por serie: la lectura de un vistazo antes del detalle de la matriz. --}}
        @unless ($matriz['series']->isEmpty())
            <x-ui.card class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold">Avance por serie</h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Estado de cada serie entre los establecimientos a los que se les exige.
                            Cada barra se compone de los cinco estados del semáforo.
                        </p>
                    </div>

                    <x-ui.icon name="chart-column" class="size-4 shrink-0 text-muted-foreground" />
                </div>

                {{-- Leyenda: única para el gráfico y para la matriz de abajo. --}}
                <ul class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5">
                    @foreach ($apilables as $clave => $definicion)
                        <li class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span class="size-2.5 shrink-0 rounded-sm {{ $definicion['relleno'] }}" aria-hidden="true"></span>
                            {{ $definicion['texto'] }}
                        </li>
                    @endforeach
                </ul>

                <ul class="mt-4 space-y-3">
                    @foreach ($matriz['series'] as $serie)
                        @php
                            $conteo = $conteosPorSerie[$serie->id] ?? [];
                            $exigidasSerie = array_sum($conteo);
                            $aprobadasSerie = $conteo['aprobado'] ?? 0;
                            $pctSerie = $exigidasSerie > 0 ? (int) round($aprobadasSerie * 100 / $exigidasSerie) : 0;

                            // Desglose en texto: el color no puede ser la única señal (principio 30).
                            $desglose = collect($apilables)
                                ->map(fn (array $definicion, string $estado) => $definicion['texto'] . ' ' . ($conteo[$estado] ?? 0))
                                ->implode(', ');
                        @endphp

                        <li>
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm font-medium">{{ $serie->codigo }}</span>
                                <span class="tabular text-xs text-muted-foreground">
                                    {{ $aprobadasSerie }} / {{ $exigidasSerie }} · {{ $pctSerie }}%
                                </span>
                            </div>

                            {{--
                                Barra apilada. Sin componente propio a propósito: se usa una
                                vez. Si aparece una segunda, se extrae a `x-ui.chart-barra`.
                                El ancho va en `style` porque Tailwind no resuelve anchos
                                calculados; las clases de color sí son literales.
                            --}}
                            <div class="mt-1.5 flex h-2.5 overflow-hidden rounded-full bg-muted"
                                 role="img"
                                 aria-label="Serie {{ $serie->codigo }}: {{ $desglose }}, de {{ $exigidasSerie }} exigidas">
                                @foreach ($apilables as $estado => $definicion)
                                    @php
                                        $cantidad = $conteo[$estado] ?? 0;
                                        $ancho = $exigidasSerie > 0 ? round($cantidad * 100 / $exigidasSerie, 2) : 0;
                                    @endphp

                                    @if ($cantidad > 0)
                                        <div class="h-full {{ $definicion['relleno'] }}"
                                             style="width: {{ $ancho }}%"
                                             title="{{ $definicion['texto'] }}: {{ $cantidad }} de {{ $exigidasSerie }}"></div>
                                    @endif
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endunless

        {{-- Matriz --}}
        @if ($matriz['series']->isEmpty())
            <x-ui.empty
                icono="grid-3x3"
                titulo="Sin series exigidas en el alcance"
                texto="No hay establecimientos con series exigidas para este período y este usuario. Revise la matriz establecimiento × serie en Administración."
            />
        @else
            <x-ui.table>
                <caption class="sr-only">Matriz de completitud por establecimiento y serie</caption>
                <thead>
                    <tr>
                        <th scope="col">Establecimiento</th>
                        @foreach ($matriz['series'] as $serie)
                            <th scope="col" class="text-center" title="{{ $serie->nombre }}">{{ $serie->codigo }}</th>
                        @endforeach
                        <th scope="col" class="text-end">Avance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($matriz['filas'] as $fila)
                        <tr>
                            <td>
                                <span class="flex items-center gap-2.5">
                                    <x-ui.avatar :texto="$fila['establecimiento']->nombre" tamano="sm" />
                                    <span class="min-w-0">
                                        <span class="block font-medium">{{ $fila['establecimiento']->codigo }}</span>
                                        <span class="block truncate text-xs text-muted-foreground">{{ $fila['establecimiento']->nombre }}</span>
                                    </span>
                                </span>

                                @if ($fila['fuera_de_plazo'])
                                    <x-ui.badge tono="danger" :punto="false" class="mt-1">Fuera de plazo</x-ui.badge>
                                @endif
                            </td>

                            @foreach ($matriz['series'] as $serie)
                                @php
                                    $celda = $fila['celdas'][$serie->id] ?? ['estado' => 'no_aplica', 'envio_id' => null];
                                    $estilo = $celdas[$celda['estado']] ?? $celdas['no_aplica'];
                                @endphp

                                <td class="text-center">
                                    {{--
                                        La celda no es un enlace: el drill-down quedó fuera del
                                        alcance v2.0 (C-018) y hay un test que lo verifica.
                                        Para abrir el envío se usa la columna de avance o el
                                        listado de Envíos.
                                    --}}
                                    <span class="inline-flex size-7 items-center justify-center rounded-md border {{ $estilo['clase'] }}"
                                          title="{{ $estilo['texto'] }} · {{ $serie->codigo }}"
                                          aria-label="{{ $estilo['texto'] }} · {{ $serie->codigo }}">
                                        @if ($estilo['icono'])
                                            <x-ui.icon :name="$estilo['icono']" class="size-3.5" />
                                        @endif
                                    </span>
                                </td>
                            @endforeach

                            <td class="text-end tabular font-medium">{{ $fila['aprobadas'] }}/{{ $fila['exigidas'] }}</td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr class="border-t border-border bg-muted/40">
                        <th scope="row" class="text-start">Total aprobadas</th>
                        @foreach ($matriz['series'] as $serie)
                            <th scope="col" class="text-center tabular">{{ $matriz['totales'][$serie->id] ?? 0 }}</th>
                        @endforeach
                        <th scope="col" class="text-end tabular">{{ $resumen['aprobadas'] }}/{{ $resumen['exigidas'] }}</th>
                    </tr>
                </tfoot>
            </x-ui.table>

            {{--
                La leyenda de estados vive en la tarjeta «Avance por serie», que va
                justo arriba. Repetirla acá era la misma información dos veces en
                una pantalla. Cada celda conserva su texto en `title` y en
                `aria-label`, así que sigue sin depender del color (principio 30).
            --}}
        @endif

    </div>
@endsection
