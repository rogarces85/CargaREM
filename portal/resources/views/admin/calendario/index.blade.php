@extends('layouts.panel')

@section('titulo', 'Calendario de plazos')

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">Calendario de plazos</h1>
        <p class="text-muted mb-0">
            Cada período se recibe hasta el final del <strong>quinto día hábil del mes siguiente</strong>
            (sábados, domingos y feriados no cuentan).
        </p>
    </div>

    @if (session('ok'))
        <div class="alert alert-success mt-3">{{ session('ok') }}</div>
    @endif

    @if (! $feriadosDelAnio)
        <div class="alert alert-warning mt-3">
            No hay feriados cargados para {{ $anio }}: el plazo se calcula solo con fines de semana y puede
            quedar uno o dos días más largo. Cárguelos en <a href="{{ route('admin.feriados.index') }}">Feriados</a>.
        </div>
    @endif

    @if (! $feriadosSiguiente)
        <div class="alert alert-warning mt-3">
            No hay feriados cargados para {{ $anio + 1 }}: las fechas de enero de {{ $anio + 1 }} en adelante
            pueden moverse cuando se publique el calendario oficial.
        </div>
    @endif

    <div class="card mt-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.calendario.index') }}" class="form-inline">
                <label for="anio" class="mr-2">Año</label>
                <input type="number" name="anio" id="anio" class="form-control mr-2" min="2010" max="2100" value="{{ $anio }}" style="width:110px">
                <button type="submit" class="btn btn-primary">Ver</button>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Períodos de {{ $anio }}</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Período</th>
                                <th>Fecha límite</th>
                                <th>Día</th>
                                <th>Estado del período</th>
                                <th>Origen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($meses as $fila)
                                <tr>
                                    <td>{{ sprintf('%02d/%d', $fila['mes'], $anio) }}</td>
                                    <td>{{ $fila['fecha']->format('d-m-Y') }}</td>
                                    <td>{{ $fila['fecha']->translatedFormat('l') }}</td>
                                    <td>
                                        @if ($fila['periodo'])
                                            {{ ucfirst($fila['periodo']->estado) }}
                                        @else
                                            <span class="text-muted">Sin crear</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($fila['periodo']?->fecha_limite_manual)
                                            <span class="badge badge-warning">Manual</span>
                                        @else
                                            <span class="badge badge-secondary">Calculada</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.calendario.aplicar') }}">
                        @csrf
                        <input type="hidden" name="anio" value="{{ $anio }}">
                        <button type="submit" class="btn btn-primary">
                            Crear o actualizar los períodos de {{ $anio }}
                        </button>
                        <small class="form-text text-muted d-block mt-2">
                            Crea los que faltan y corrige la fecha de los que no tienen override manual.
                            No cambia el estado de los períodos existentes.
                        </small>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Feriados de {{ $anio }}</h3>
                    <a href="{{ route('admin.feriados.index') }}" class="btn btn-sm btn-outline-secondary">Gestionar</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($feriados as $feriado)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $feriado->fecha->format('d-m-Y') }}</span>
                                <span class="text-muted">{{ $feriado->nombre }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">Sin feriados cargados.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
