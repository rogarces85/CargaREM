@extends('layouts.panel')

@section('titulo', 'Calendario de plazos')

@section('contenido')
    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            titulo="Calendario de plazos"
            :migas="['Panel' => route('panel'), 'Calendario de plazos' => null]"
        />

        <x-ui.alert tono="info">
            Cada período se recibe hasta el final del <strong>quinto día hábil del mes siguiente</strong>
            (sábados, domingos y feriados no cuentan).
        </x-ui.alert>

        @if (! $feriadosDelAnio)
            <x-ui.alert tono="warn">
                No hay feriados cargados para {{ $anio }}: el plazo se calcula solo con fines de semana y puede
                quedar uno o dos días más largo. Cárguelos en
                <a href="{{ route('admin.feriados.index') }}" class="link">Feriados</a>.
            </x-ui.alert>
        @endif

        @if (! $feriadosSiguiente)
            <x-ui.alert tono="warn">
                No hay feriados cargados para {{ $anio + 1 }}: las fechas de enero de {{ $anio + 1 }} en adelante
                pueden moverse cuando se publique el calendario oficial.
            </x-ui.alert>
        @endif

        {{-- Filtro por año --}}
        <x-ui.card class="p-5">
            <form method="GET" action="{{ route('admin.calendario.index') }}" class="flex flex-wrap items-end gap-3">
                <x-ui.field nombre="anio" etiqueta="Año" class="w-32">
                    <input type="number" name="anio" id="anio" min="2010" max="2100" value="{{ $anio }}" class="input tabular">
                </x-ui.field>

                <button type="submit" class="btn btn-primary h-10">
                    <x-ui.icon name="search" class="size-4" />
                    Ver
                </button>
            </form>
        </x-ui.card>

        <div class="grid gap-4 lg:grid-cols-3">

            {{-- Períodos del año --}}
            <div class="space-y-2 lg:col-span-2">
                <h2 class="text-sm font-semibold">Períodos de {{ $anio }}</h2>

                <x-ui.table>
                    <caption class="sr-only">Períodos de {{ $anio }} y su fecha límite</caption>
                    <thead>
                        <tr>
                            <th scope="col">Período</th>
                            <th scope="col">Fecha límite</th>
                            <th scope="col" class="hidden sm:table-cell">Día</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="hidden sm:table-cell">Origen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($meses as $fila)
                            <tr>
                                <td class="tabular font-medium">{{ sprintf('%02d/%d', $fila['mes'], $anio) }}</td>
                                <td class="tabular">{{ $fila['fecha']->format('d-m-Y') }}</td>
                                <td class="hidden capitalize text-muted-foreground sm:table-cell">{{ $fila['fecha']->translatedFormat('l') }}</td>
                                <td>
                                    @if ($fila['periodo'])
                                        <x-ui.estado :valor="$fila['periodo']->estado" />
                                    @else
                                        <span class="text-xs text-muted-foreground">Sin crear</span>
                                    @endif
                                </td>
                                <td class="hidden sm:table-cell">
                                    @if ($fila['periodo']?->fecha_limite_manual)
                                        <x-ui.badge tono="warn" :punto="false">Manual</x-ui.badge>
                                    @else
                                        <x-ui.badge tono="idle" :punto="false">Calculada</x-ui.badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>

                <x-ui.card class="p-5">
                    <form method="POST" action="{{ route('admin.calendario.aplicar') }}">
                        @csrf
                        <input type="hidden" name="anio" value="{{ $anio }}">

                        <button type="submit" class="btn btn-primary">
                            <x-ui.icon name="calendar-check" class="size-4" />
                            Crear o actualizar los períodos de {{ $anio }}
                        </button>

                        <p class="campo-ayuda">
                            Crea los que faltan y corrige la fecha de los que no tienen override manual.
                            No cambia el estado de los períodos existentes.
                        </p>
                    </form>
                </x-ui.card>
            </div>

            {{-- Feriados --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Feriados de {{ $anio }}</h2>
                    <a href="{{ route('admin.feriados.index') }}" class="btn btn-secondary btn-sm">Gestionar</a>
                </div>

                <x-ui.card :sin-relleno="true">
                    @if ($feriados->isEmpty())
                        <p class="px-4 py-6 text-center text-xs text-muted-foreground">Sin feriados cargados.</p>
                    @else
                        <ul class="divide-y divide-border">
                            @foreach ($feriados as $feriado)
                                <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                                    <span class="tabular text-sm">{{ $feriado->fecha->format('d-m-Y') }}</span>
                                    <span class="truncate text-xs text-muted-foreground">{{ $feriado->nombre }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>
        </div>

    </div>
@endsection
