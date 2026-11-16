@extends('layouts.panel')

@section('titulo', 'Envío ' . $envio->establecimiento->codigo)

@section('contenido')
    @php
        $bloqueantes = $envio->archivos->sum(fn ($a) => $a->validaciones->where('severidad', 'bloqueante')->count())
            + $envio->validaciones->where('severidad', 'bloqueante')->count();

        $advertencias = $envio->archivos->sum(fn ($a) => $a->validaciones->where('severidad', '!=', 'bloqueante')->count())
            + $envio->validaciones->where('severidad', '!=', 'bloqueante')->count();

        // Acciones disponibles según el estado y el permiso del usuario. Se
        // calcula en la vista porque es presentación; el servicio de transición
        // vuelve a validar cada una en el servidor.
        $acciones = [
            ['accion' => 'enviar', 'hab' => 'enviar', 'texto' => 'Enviar a revisión', 'clase' => 'btn-primary', 'icono' => 'upload'],
            ['accion' => 'aprobar', 'hab' => 'aprobar', 'texto' => 'Aprobar estadística', 'clase' => 'btn-primary', 'icono' => 'circle-check'],
            ['accion' => 'observar', 'hab' => 'observar', 'texto' => 'Observar', 'clase' => 'btn-secondary', 'icono' => 'circle-exclamation-point'],
        ];

        $disponibles = array_values(array_filter(
            $acciones,
            fn ($a) => auth()->user()->can($a['hab'], $envio)
        ));
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            :titulo="$envio->establecimiento->nombre"
            :migas="['Panel' => route('panel'), 'Envíos' => route('envios.index'), sprintf('%02d/%d', $envio->mes, $envio->anio) => null]"
        >
            <x-slot:acciones>
                <a href="{{ route('envios.reporte', $envio) }}" class="btn btn-secondary">
                    <x-ui.icon name="file-spreadsheet" class="size-4" />
                    Descargar reporte
                </a>
                <a href="{{ route('envios.index') }}" class="btn btn-ghost">
                    <x-ui.icon name="arrow-right" class="size-4 rotate-180" />
                    Volver
                </a>
            </x-slot:acciones>
        </x-ui.page-header>

        {{-- Contexto del envío: siempre visible, como pide el principio 24. --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
            <span class="inline-flex items-center gap-1.5">
                <x-ui.icon name="building" class="size-4" />
                Código {{ $envio->establecimiento->codigo }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <x-ui.icon name="calendar" class="size-4" />
                Período {{ sprintf('%02d/%d', $envio->mes, $envio->anio) }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <x-ui.icon name="clock" class="size-4" />
                Fecha límite: {{ $envio->fecha_limite?->format('d-m-Y') ?? 'sin plazo configurado' }}
            </span>

            <span class="flex flex-wrap items-center gap-1">
                <x-ui.estado :valor="$envio->estado" />
                @if ($envio->estaFueraDePlazo())
                    <x-ui.badge tono="danger">Fuera de plazo</x-ui.badge>
                @endif
            </span>
        </div>

        {{-- Indicadores --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat icono="ban" rotulo="Bloqueantes" :valor="$bloqueantes" :tono="$bloqueantes > 0 ? 'danger' : 'ok'"
                       :detalle="$bloqueantes > 0 ? 'impiden enviar a revisión' : 'sin bloqueantes'" />

            <x-ui.stat icono="triangle-exclamation-point" rotulo="Advertencias" :valor="$advertencias" tono="warn"
                       detalle="no impiden el envío" />

            <x-ui.stat icono="list" rotulo="Series exigidas" :valor="$exigidas->count()" tono="info" />

            <x-ui.stat icono="file-text" rotulo="Series subidas" :valor="$vigentes->count()"
                       :tono="$vigentes->count() >= $exigidas->count() ? 'ok' : 'idle'"
                       :detalle="'de ' . $exigidas->count() . ' exigidas'" />
        </div>

        {{-- Subida --}}
        @if ($puedeSubir)
            <x-ui.card class="p-5">
                <h2 class="text-sm font-semibold">Subir o reemplazar un archivo</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Subir de nuevo crea una versión nueva; la anterior queda en el historial.
                    Máximo {{ config('rem.archivos.max_kb') }} KB por archivo.
                </p>

                <form method="POST" action="{{ route('envios.subir', $envio) }}" enctype="multipart/form-data"
                      class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_1.6fr_auto] lg:items-end">
                    @csrf

                    <x-ui.field nombre="serie_id" etiqueta="Serie" :error="$errors->first('serie_id')" requerido>
                        <select name="serie_id" id="serie_id" class="select" required>
                            @foreach ($series as $serie)
                                <option value="{{ $serie->id }}">
                                    {{ $serie->codigo }} — {{ $serie->nombre }}
                                    @if ($vigentes->has($serie->id))
                                        (v{{ $vigentes[$serie->id]->version }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field nombre="archivo" etiqueta="Archivo Excel" :error="$errors->first('archivo')" requerido>
                        <input type="file" name="archivo" id="archivo" accept=".xls,.xlsx,.xlsm" required
                               class="w-full rounded-lg border border-input bg-card text-sm file:me-3 file:rounded-s-lg file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium">
                    </x-ui.field>

                    <button type="submit" class="btn btn-primary h-10">
                        <x-ui.icon name="upload" class="size-4" />
                        Subir y validar
                    </button>
                </form>
            </x-ui.card>
        @endif

        {{-- Series del período --}}
        <div class="space-y-2">
            <h2 class="text-sm font-semibold">Series del período</h2>

            <x-ui.table>
                <caption class="sr-only">Series exigidas y archivo vigente</caption>
                <thead>
                    <tr>
                        <th scope="col">Serie</th>
                        <th scope="col">Archivo vigente</th>
                        <th scope="col" class="text-center">Versión</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="text-center">Bloqueantes</th>
                        <th scope="col" class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exigidas as $serie)
                        @php $archivo = $vigentes->get($serie->id); @endphp
                        <tr>
                            <td>
                                <span class="block font-medium">{{ $serie->codigo }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ $serie->nombre }}</span>
                            </td>

                            <td class="max-w-[16rem] truncate text-muted-foreground" title="{{ $archivo?->nombre_original }}">
                                {{ $archivo?->nombre_original ?? '—' }}
                            </td>

                            <td class="text-center tabular text-muted-foreground">
                                {{ $archivo ? 'v' . $archivo->version : '—' }}
                            </td>

                            <td>
                                @if ($archivo)
                                    <x-ui.estado :valor="$archivo->estado" />
                                @else
                                    <x-ui.badge tono="idle">Sin archivo</x-ui.badge>
                                @endif
                            </td>

                            <td class="text-center tabular">
                                @if ($archivo)
                                    @php $n = $archivo->validaciones->where('severidad', 'bloqueante')->count(); @endphp
                                    <span class="{{ $n > 0 ? 'font-semibold text-status-danger-fg' : 'text-muted-foreground' }}">{{ $n }}</span>
                                @else
                                    <span class="text-muted-foreground">—</span>
                                @endif
                            </td>

                            <td class="text-end">
                                @if ($archivo)
                                    <a href="{{ route('envios.archivo.descargar', [$envio, $archivo]) }}" class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="download" class="size-3.5" />
                                        Descargar
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </div>

        {{-- Hallazgos --}}
        @if ($bloqueantes > 0 || $advertencias > 0)
            <div class="space-y-2">
                <h2 class="text-sm font-semibold">
                    Hallazgos de la validación
                    <span class="ml-1 font-normal text-muted-foreground">
                        {{ $bloqueantes }} bloqueante(s), {{ $advertencias }} advertencia(s)
                    </span>
                </h2>

                <x-ui.table class="tabla-compacta">
                    <caption class="sr-only">Hallazgos de validación del envío</caption>
                    <thead>
                        <tr>
                            <th scope="col">Etapa</th>
                            <th scope="col">Código</th>
                            <th scope="col">Severidad</th>
                            <th scope="col" class="hidden sm:table-cell">Hoja</th>
                            <th scope="col" class="hidden sm:table-cell">Celda</th>
                            <th scope="col">Mensaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($envio->validaciones->sortBy('etapa') as $hallazgo)
                            <tr>
                                <td class="text-muted-foreground">{{ $hallazgo->etapa }}</td>
                                <td><code class="rounded bg-muted px-1 py-0.5 text-[11px]">{{ $hallazgo->codigo }}</code></td>
                                <td><x-ui.estado :valor="$hallazgo->severidad" /></td>
                                <td class="hidden text-muted-foreground sm:table-cell">{{ $hallazgo->hoja ?? '—' }}</td>
                                <td class="hidden text-muted-foreground sm:table-cell">{{ $hallazgo->celda ?? '—' }}</td>
                                <td>{{ $hallazgo->mensaje }}</td>
                            </tr>
                        @endforeach

                        @foreach ($envio->archivos as $archivo)
                            @foreach ($archivo->validaciones->sortBy('etapa') as $hallazgo)
                                <tr>
                                    <td class="text-muted-foreground">{{ $hallazgo->etapa }}</td>
                                    <td><code class="rounded bg-muted px-1 py-0.5 text-[11px]">{{ $hallazgo->codigo }}</code></td>
                                    <td><x-ui.estado :valor="$hallazgo->severidad" /></td>
                                    <td class="hidden text-muted-foreground sm:table-cell">{{ $hallazgo->hoja ?? '—' }}</td>
                                    <td class="hidden text-muted-foreground sm:table-cell">{{ $hallazgo->celda ?? '—' }}</td>
                                    <td>
                                        <span class="text-xs font-medium text-muted-foreground">{{ $archivo->serie?->codigo }}:</span>
                                        {{ $hallazgo->mensaje }}
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">

            {{-- Acciones --}}
            <x-ui.card class="p-5">
                <h2 class="text-sm font-semibold">Acciones</h2>

                @forelse ($disponibles as $accion)
                    <form method="POST" action="{{ route('envios.transicion', $envio) }}" class="mt-3 first:mt-4">
                        @csrf
                        <input type="hidden" name="accion" value="{{ $accion['accion'] }}">
                        <button type="submit" class="btn {{ $accion['clase'] }} w-full">
                            <x-ui.icon :name="$accion['icono']" class="size-4" />
                            {{ $accion['texto'] }}
                        </button>
                    </form>
                @empty
                    <p class="mt-4 text-sm text-muted-foreground">
                        No hay acciones disponibles en el estado actual.
                    </p>
                @endforelse
            </x-ui.card>

            {{-- Historial --}}
            <x-ui.card class="p-5">
                <h2 class="text-sm font-semibold">Historial</h2>

                @if ($envio->transiciones->isEmpty())
                    <p class="mt-4 text-sm text-muted-foreground">Sin movimientos.</p>
                @else
                    <x-ui.timeline class="mt-5">
                        @foreach ($envio->transiciones->sortByDesc('created_at') as $transicion)
                            <x-ui.timeline-item
                                :tono="\App\Support\EstadoUi::de($transicion->estado_nuevo)['tono']"
                                :titulo="($transicion->estado_anterior ? \App\Support\EstadoUi::etiqueta($transicion->estado_anterior) : 'Inicio') . ' → ' . \App\Support\EstadoUi::etiqueta($transicion->estado_nuevo)"
                                :detalle="($transicion->actor?->name ?? 'sistema') . ' · ' . $transicion->created_at?->format('d-m-Y H:i') . ($transicion->motivo ? ' · ' . $transicion->motivo : '')"
                            />
                        @endforeach
                    </x-ui.timeline>
                @endif
            </x-ui.card>
        </div>

    </div>
@endsection
