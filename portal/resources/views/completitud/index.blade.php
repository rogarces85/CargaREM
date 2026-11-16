@extends('layouts.panel')

@section('titulo', 'Completitud')

@section('contenido')
    @php
        $colores = [
            'aprobado'    => ['bg' => '#d4edda', 'texto' => '#155724', 'icono' => '✓', 'titulo' => 'Aprobado'],
            'en_proceso'  => ['bg' => '#fff3cd', 'texto' => '#856404', 'icono' => '·', 'titulo' => 'En proceso'],
            'con_errores' => ['bg' => '#f8d7da', 'texto' => '#721c24', 'icono' => '✕', 'titulo' => 'Con errores'],
            'sin_archivo' => ['bg' => '#f8f9fa', 'texto' => '#6c757d', 'icono' => '○', 'titulo' => 'Sin archivo'],
            'sin_envio'   => ['bg' => '#e2e3e5', 'texto' => '#383d41', 'icono' => '—', 'titulo' => 'Sin envío'],
            'no_aplica'   => ['bg' => 'transparent', 'texto' => '#dee2e6', 'icono' => '', 'titulo' => 'No aplica'],
        ];
        $resumen = $matriz['resumen'];
        $avance = $resumen['exigidas'] > 0 ? round($resumen['aprobadas'] * 100 / $resumen['exigidas']) : 0;
    @endphp

    <div class="content-header px-0">
        <h1 class="m-0">Completitud del período</h1>
        <p class="text-muted mb-0">
            Qué entregó cada establecimiento y en qué estado. Un clic en la celda abre el envío.
        </p>
    </div>

    <div class="row mt-3">
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-building"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Establecimientos</span>
                    <span class="info-box-number">{{ $resumen['establecimientos'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Series aprobadas</span>
                    <span class="info-box-number">{{ $resumen['aprobadas'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-secondary"><i class="fas fa-list"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Series exigidas</span>
                    <span class="info-box-number">{{ $resumen['exigidas'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-primary"><i class="fas fa-percentage"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Avance</span>
                    <span class="info-box-number">{{ $avance }}%</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <form method="GET" action="{{ route('completitud.index') }}" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="anio" class="form-label">Año</label>
                    <input type="number" name="anio" id="anio" class="form-control" value="{{ $anio }}" min="2000" max="2100">
                </div>
                <div class="col-md-2">
                    <label for="mes" class="form-label">Mes</label>
                    <select name="mes" id="mes" class="form-select">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected($mes === $m)>{{ sprintf('%02d', $m) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="establecimiento_id" class="form-label">Establecimiento</label>
                    <select name="establecimiento_id" id="establecimiento_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($establecimientos as $establecimiento)
                            <option value="{{ $establecimiento->id }}"
                                @selected(($filtros['establecimiento_id'] ?? '') == $establecimiento->id)>
                                {{ $establecimiento->codigo }} — {{ $establecimiento->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">Ver</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body table-responsive p-0">
            <table class="table table-bordered mb-0 text-center align-middle">
                <thead>
                    <tr>
                        <th class="text-start">Establecimiento</th>
                        @foreach ($matriz['series'] as $serie)
                            <th title="{{ $serie->nombre }}">{{ $serie->codigo }}</th>
                        @endforeach
                        <th>Avance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($matriz['filas'] as $fila)
                        <tr>
                            <td class="text-start">
                                <strong>{{ $fila['establecimiento']->codigo }}</strong>
                                <span class="text-muted small d-block">{{ $fila['establecimiento']->nombre }}</span>
                                @if ($fila['fuera_de_plazo'])
                                    <span class="badge bg-danger mt-1">Fuera de plazo</span>
                                @endif
                            </td>
                            @foreach ($matriz['series'] as $serie)
                                @php
                                    $celda = $fila['celdas'][$serie->id] ?? ['estado' => 'no_aplica', 'envio_id' => null];
                                    $c = $colores[$celda['estado']] ?? $colores['no_aplica'];
                                @endphp
                                <td style="background:{{ $c['bg'] }};color:{{ $c['texto'] }};"
                                    title="{{ $c['titulo'] }}">
                                    @if ($celda['estado'] !== 'no_aplica')
                                        <span style="color:{{ $c['texto'] }};font-weight:bold;">{{ $c['icono'] }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <strong>{{ $fila['aprobadas'] }}/{{ $fila['exigidas'] }}</strong>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $matriz['series']->count() + 2 }}" class="text-muted py-4">
                                No hay establecimientos con series exigidas en el alcance de su usuario.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($matriz['series']->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th class="text-start">Total aprobadas</th>
                            @foreach ($matriz['series'] as $serie)
                                <th>{{ $matriz['totales'][$serie->id] ?? 0 }}</th>
                            @endforeach
                            <th>{{ $resumen['aprobadas'] }}/{{ $resumen['exigidas'] }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="mt-3">
        <span class="badge bg-success">✓ Aprobado</span>
        <span class="badge bg-warning text-dark">· En proceso</span>
        <span class="badge bg-danger">✕ Con errores</span>
        <span class="badge bg-light text-dark">○ Sin archivo</span>
        <span class="badge bg-secondary">— Sin envío</span>
    </div>
@endsection