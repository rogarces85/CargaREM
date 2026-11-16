@extends('layouts.panel')

@section('titulo', 'Envíos')

@section('contenido')
    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            titulo="Envíos"
            subtitulo="Archivos REM por establecimiento y período."
            :migas="['Panel' => route('panel'), 'Envíos' => null]"
        />

        @if ($puedeCrear)
            <x-ui.card class="p-5">
                <h2 class="text-sm font-semibold">Nuevo envío</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    El período se elige de los que están abiertos o en revisión. Un período cerrado no acepta cargas.
                </p>

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

        @if ($envios->isEmpty())
            <x-ui.empty
                titulo="Todavía no hay envíos"
                texto="Cree uno con el formulario de arriba. Aparecerá aquí con su estado y podrá subir las series una por una."
            />
        @else
            <x-ui.table>
                <caption class="sr-only">Envíos por período y establecimiento</caption>
                <thead>
                    <tr>
                        <th scope="col">Período</th>
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
