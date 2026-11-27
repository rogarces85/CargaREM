@extends('layouts.panel')

@section('titulo', 'Envíos')

@section('contenido')
    @php
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $ordenes = [
            'periodo'         => 'Período (más reciente primero)',
            'comuna'          => 'Comuna → establecimiento → período',
            'establecimiento' => 'Establecimiento → período',
            'estado'          => 'Estado → período',
        ];

        $hayFiltros = collect($filtros)->filter(fn ($valor) => $valor !== null && $valor !== '')->isNotEmpty();
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            titulo="Envíos"
            subtitulo="Archivos REM por establecimiento y período de su alcance."
            :migas="['Panel' => route('panel'), 'Envíos' => null]"
        />

        @if ($sinAsignacion)
            <x-ui.alert tono="warn">
                No tiene establecimientos asignados, así que no hay envíos que mostrar.
                Pídale al administrador que le asigne al menos uno en <strong>Administración → Asignaciones</strong>.
            </x-ui.alert>
        @endif

        @if ($puedeCrear)
            <x-ui.card class="p-5">
                <h2 class="text-sm font-semibold">Nuevo envío</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    El período se elige de los que están abiertos o en revisión. Un período cerrado solo acepta
                    cargas con una apertura de plazo aprobada.
                </p>

                @error('envio')
                    <p class="campo-error mt-2">
                        <x-ui.icon name="circle-exclamation-point" class="mt-px size-3.5 shrink-0" />
                        <span>{{ $message }}</span>
                    </p>
                @enderror

                <form method="POST" action="{{ route('envios.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_auto] lg:items-end">
                    @csrf

                    <x-ui.field nombre="establecimiento_id" etiqueta="Establecimiento" :error="$errors->first('establecimiento_id')" requerido>
                        <select name="establecimiento_id" id="establecimiento_id" class="select" required>
                            @foreach ($establecimientos as $establecimiento)
                                <option value="{{ $establecimiento->id }}">
                                    {{ $establecimiento->codigo }} — {{ $establecimiento->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field nombre="periodo" etiqueta="Período" :error="$errors->first('periodo')" requerido>
                        <select name="periodo" id="periodo" class="select" required>
                            @forelse ($periodos as $periodo)
                                <option value="{{ $periodo->anio }}-{{ $periodo->mes }}">
                                    {{ sprintf('%02d/%d', $periodo->mes, $periodo->anio) }} · {{ \App\Support\EstadoUi::etiqueta($periodo->estado) }}
                                </option>
                            @empty
                                <option value="">No hay períodos abiertos</option>
                            @endforelse
                        </select>
                    </x-ui.field>

                    <button type="submit" class="btn btn-primary h-10" @disabled($periodos->isEmpty())>
                        <x-ui.icon name="plus" class="size-4" />
                        Crear envío
                    </button>
                </form>
            </x-ui.card>
        @endif

        {{-- Filtros (RF-047.1) y orden (RF-047.2). --}}
        <x-ui.card class="p-5">
            <form method="GET" action="{{ route('envios.index') }}"
                  class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <x-ui.field nombre="anio" etiqueta="Año">
                    <select name="anio" id="anio" class="select">
                        <option value="">Todos</option>
                        @foreach ($anios as $anio)
                            <option value="{{ $anio }}" @selected(($filtros['anio'] ?? '') == $anio)>{{ $anio }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field nombre="mes" etiqueta="Mes">
                    <select name="mes" id="mes" class="select">
                        <option value="">Todos</option>
                        @foreach ($meses as $numero => $nombre)
                            <option value="{{ $numero }}" @selected(($filtros['mes'] ?? '') == $numero)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field nombre="comuna" etiqueta="Comuna">
                    <select name="comuna" id="comuna" class="select">
                        <option value="">Todas</option>
                        @foreach ($comunas as $comuna)
                            <option value="{{ $comuna }}" @selected(($filtros['comuna'] ?? '') === $comuna)>{{ $comuna }}</option>
                        @endforeach
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

                <x-ui.field nombre="estado" etiqueta="Estado">
                    <select name="estado" id="estado" class="select">
                        <option value="">Todos</option>
                        @foreach (\App\Models\Envio::ESTADOS as $estado)
                            <option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>
                                {{ \App\Support\EstadoUi::etiqueta($estado) }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field nombre="plazo" etiqueta="Plazo">
                    <select name="plazo" id="plazo" class="select">
                        <option value="">Todos</option>
                        <option value="en_plazo" @selected(($filtros['plazo'] ?? '') === 'en_plazo')>En plazo</option>
                        <option value="fuera" @selected(($filtros['plazo'] ?? '') === 'fuera')>Fuera de plazo</option>
                        <option value="apertura" @selected(($filtros['plazo'] ?? '') === 'apertura')>Con apertura autorizada</option>
                    </select>
                </x-ui.field>

                <x-ui.field nombre="orden" etiqueta="Ordenar por">
                    <select name="orden" id="orden" class="select">
                        @foreach ($ordenes as $valor => $texto)
                            <option value="{{ $valor }}" @selected(($filtros['orden'] ?? 'periodo') === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field nombre="q" etiqueta="Buscar">
                    <input type="search" name="q" id="q" value="{{ $filtros['q'] ?? '' }}"
                           placeholder="Código o nombre" class="input">
                </x-ui.field>

                <div class="flex flex-wrap gap-2 lg:col-span-4">
                    <button type="submit" class="btn btn-primary">
                        <x-ui.icon name="search" class="size-4" />
                        Filtrar
                    </button>

                    @if ($hayFiltros)
                        <a href="{{ route('envios.index') }}" class="btn btn-ghost">Limpiar filtros</a>
                    @endif

                    <span class="ms-auto self-center text-xs text-muted-foreground">
                        {{ $envios->total() }} envío(s) con este filtro
                    </span>
                </div>
            </form>
        </x-ui.card>

        @if ($envios->isEmpty())
            <x-ui.empty
                :titulo="$hayFiltros ? 'No hay envíos con ese filtro' : 'Todavía no hay envíos'"
                :texto="$hayFiltros
                    ? 'Pruebe con otro período, otra comuna o quite los filtros.'
                    : 'Cree uno con el formulario de arriba. Aparecerá aquí con su estado y podrá subir las series una por una.'"
            />
        @else
            <x-ui.table>
                <caption class="sr-only">Envíos por período, comuna y establecimiento</caption>
                <thead>
                    <tr>
                        <th scope="col">Período</th>
                        <th scope="col">Comuna</th>
                        <th scope="col">Establecimiento</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="text-center">Series</th>
                        <th scope="col" class="hidden sm:table-cell">Actualizado</th>
                        <th scope="col" class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($envios as $envio)
                        <tr>
                            <td class="tabular font-medium">{{ sprintf('%02d/%d', $envio->mes, $envio->anio) }}</td>

                            <td class="text-muted-foreground">{{ $envio->establecimiento->comuna ?: '—' }}</td>

                            <td>
                                <span class="flex items-center gap-2.5">
                                    <x-ui.avatar :texto="$envio->establecimiento->nombre" tamano="sm" />
                                    <span class="min-w-0">
                                        <span class="block font-medium">{{ $envio->establecimiento->codigo }}</span>
                                        <span class="block truncate text-xs text-muted-foreground">{{ $envio->establecimiento->nombre }}</span>
                                    </span>
                                </span>
                            </td>

                            <td>
                                <span class="flex flex-wrap items-center gap-1">
                                    <x-ui.estado :valor="$envio->estado" />

                                    @if ($envio->estaFueraDePlazo())
                                        <x-ui.badge tono="danger">Fuera de plazo</x-ui.badge>
                                    @endif

                                    @if ($envio->conApertura())
                                        <x-ui.badge tono="info" :punto="false">Con apertura</x-ui.badge>
                                    @endif
                                </span>
                            </td>

                            <td class="text-center tabular">{{ $envio->archivosVigentes->count() }}</td>

                            <td class="hidden whitespace-nowrap text-xs text-muted-foreground sm:table-cell">
                                {{ $envio->updated_at?->format('d-m-Y H:i') }}
                            </td>

                            <td class="text-end">
                                <a href="{{ route('envios.show', $envio) }}" class="btn btn-secondary btn-sm">
                                    Abrir
                                    <x-ui.icon name="arrow-right" class="size-3.5" />
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <div>{{ $envios->links() }}</div>
        @endif

    </div>
@endsection
