@extends('layouts.panel')

@section('titulo', 'Envío ' . $envio->establecimiento->codigo)

@section('contenido')
    @php
        $colores = [
            'recibido'    => 'secondary',
            'validado'    => 'info',
            'con_errores' => 'danger',
            'en_revision' => 'warning',
            'observado'   => 'warning',
            'aprobado'    => 'success',
            'cargado'     => 'success',
            'anulado'     => 'dark',
        ];
        $color = $colores[$envio->estado] ?? 'secondary';
        $bloqueantes = $envio->archivos->sum(fn ($a) => $a->validaciones->where('severidad', 'bloqueante')->count())
            + $envio->validaciones->where('severidad', 'bloqueante')->count();
        $advertencias = $envio->archivos->sum(fn ($a) => $a->validaciones->where('severidad', '!=', 'bloqueante')->count())
            + $envio->validaciones->where('severidad', '!=', 'bloqueante')->count();
    @endphp

    <div class="content-header px-0">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="m-0">{{ $envio->establecimiento->nombre }}</h1>
                <p class="text-muted mb-0">
                    Código {{ $envio->establecimiento->codigo }} ·
                    Período {{ sprintf('%02d/%d', $envio->mes, $envio->anio) }} ·
                    <span class="badge badge-{{ $color }}">{{ str_replace('_', ' ', $envio->estado) }}</span>
                </p>
            </div>
            <div>
                <a href="{{ route('envios.reporte', $envio) }}" class="btn btn-outline-success">
                    <i class="fas fa-file-excel"></i> Descargar reporte
                </a>
                <a href="{{ route('envios.index') }}" class="btn btn-link">Volver</a>
            </div>
        </div>
    </div>

    @if (session('ok'))
        <div class="alert alert-success mt-3">{{ session('ok') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mt-3">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row mt-2">
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-danger"><i class="fas fa-ban"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Bloqueantes</span>
                    <span class="info-box-number">{{ $bloqueantes }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Advertencias</span>
                    <span class="info-box-number">{{ $advertencias }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-file-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Series exigidas</span>
                    <span class="info-box-number">{{ $exigidas->count() }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Series subidas</span>
                    <span class="info-box-number">{{ $vigentes->count() }}</span>
                </div>
            </div>
        </div>
    </div>

    @if ($puedeSubir)
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Subir o reemplazar un archivo</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('envios.subir', $envio) }}" enctype="multipart/form-data" class="form-row align-items-end">
                    @csrf
                    <div class="form-group col-md-4">
                        <label for="serie_id">Serie</label>
                        <select name="serie_id" id="serie_id" class="form-control" required>
                            @foreach ($series as $serie)
                                <option value="{{ $serie->id }}">
                                    {{ $serie->codigo }} — {{ $serie->nombre }}
                                    @if ($vigentes->has($serie->id))
                                        (v{{ $vigentes[$serie->id]->version }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-5">
                        <label for="archivo">Archivo Excel</label>
                        <input type="file" name="archivo" id="archivo" class="form-control-file" accept=".xls,.xlsx,.xlsm" required>
                        <small class="form-text text-muted">
                            Máximo {{ config('rem.archivos.max_kb') }} KB. Subir de nuevo crea una versión nueva; la anterior queda en el historial.
                        </small>
                    </div>
                    <div class="form-group col-md-3">
                        <button type="submit" class="btn btn-primary btn-block">Subir y validar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title mb-0">Series del período</h3>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Serie</th>
                        <th>Archivo vigente</th>
                        <th>Versión</th>
                        <th>Estado</th>
                        <th class="text-center">Bloqueantes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exigidas as $serie)
                        @php
                            $archivo = $vigentes->get($serie->id);
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $serie->codigo }}</strong>
                                <span class="text-muted small d-block">{{ $serie->nombre }}</span>
                            </td>
                            <td>{{ $archivo?->nombre_original ?? '—' }}</td>
                            <td>{{ $archivo ? 'v'.$archivo->version : '—' }}</td>
                            <td>
                                @if ($archivo)
                                    <span class="badge badge-{{ $archivo->estado === 'valido' ? 'success' : ($archivo->estado === 'con_errores' ? 'danger' : 'secondary') }}">
                                        {{ str_replace('_', ' ', $archivo->estado) }}
                                    </span>
                                @else
                                    <span class="badge badge-light">sin archivo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                {{ $archivo ? $archivo->validaciones->where('severidad', 'bloqueante')->count() : '—' }}
                            </td>
                            <td class="text-right">
                                @if ($archivo)
                                    <a href="{{ route('envios.archivo.descargar', [$envio, $archivo]) }}" class="btn btn-sm btn-outline-secondary">
                                        Descargar
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($bloqueantes > 0 || $advertencias > 0)
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Hallazgos de la validación</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Etapa</th>
                            <th>Código</th>
                            <th>Severidad</th>
                            <th>Hoja</th>
                            <th>Celda</th>
                            <th>Mensaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($envio->validaciones->sortBy('etapa') as $hallazgo)
                            <tr class="table-warning">
                                <td>{{ $hallazgo->etapa }}</td>
                                <td><code>{{ $hallazgo->codigo }}</code></td>
                                <td>{{ $hallazgo->severidad }}</td>
                                <td>{{ $hallazgo->hoja ?? '—' }}</td>
                                <td>{{ $hallazgo->celda ?? '—' }}</td>
                                <td>{{ $hallazgo->mensaje }}</td>
                            </tr>
                        @endforeach
                        @foreach ($envio->archivos as $archivo)
                            @foreach ($archivo->validaciones->sortBy('etapa') as $hallazgo)
                                <tr class="{{ $hallazgo->severidad === 'bloqueante' ? 'table-danger' : '' }}">
                                    <td>{{ $hallazgo->etapa }}</td>
                                    <td><code>{{ $hallazgo->codigo }}</code></td>
                                    <td>{{ $hallazgo->severidad }}</td>
                                    <td>{{ $hallazgo->hoja ?? '—' }}</td>
                                    <td>{{ $hallazgo->celda ?? '—' }}</td>
                                    <td>
                                        <span class="text-muted small">{{ $archivo->serie?->codigo }}:</span>
                                        {{ $hallazgo->mensaje }}
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Acciones</h3>
                </div>
                <div class="card-body">
                    @php
                        $acciones = [
                            ['accion' => 'enviar', 'hab' => 'enviar', 'texto' => 'Enviar a revisión', 'clase' => 'primary'],
                            ['accion' => 'aprobar', 'hab' => 'aprobar', 'texto' => 'Aprobar', 'clase' => 'success'],
                            ['accion' => 'observar', 'hab' => 'observar', 'texto' => 'Observar', 'clase' => 'warning'],
                            ['accion' => 'cargar', 'hab' => 'cargar', 'texto' => 'Marcar como cargado', 'clase' => 'info'],
                            ['accion' => 'reabrir', 'hab' => 'reabrir', 'texto' => 'Reabrir', 'clase' => 'secondary'],
                        ];
                        $disponibles = array_values(array_filter(
                            $acciones,
                            fn ($a) => auth()->user()->can($a['hab'], $envio)
                        ));
                    @endphp

                    @forelse ($disponibles as $accion)
                        <form method="POST" action="{{ route('envios.transicion', $envio) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="accion" value="{{ $accion['accion'] }}">
                            <button type="submit" class="btn btn-{{ $accion['clase'] }} mr-2 mb-2">
                                {{ $accion['texto'] }}
                            </button>
                        </form>
                    @empty
                        <p class="text-muted mb-0">No hay acciones disponibles en el estado actual.</p>
                    @endforelse

                    @can('anular', $envio)
                        <form method="POST" action="{{ route('envios.transicion', $envio) }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="accion" value="anular">
                            <div class="form-row align-items-end">
                                <div class="form-group col-md-8 mb-0">
                                    <label for="motivo">Motivo para anular</label>
                                    <input type="text" name="motivo" id="motivo" class="form-control" required>
                                </div>
                                <div class="form-group col-md-4 mb-0">
                                    <button type="submit" class="btn btn-danger btn-block">Anular</button>
                                </div>
                            </div>
                        </form>
                    @endcan
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Historial</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($envio->transiciones->sortByDesc('created_at') as $transicion)
                            <li class="list-group-item">
                                <span class="text-muted small">{{ $transicion->created_at?->format('d-m-Y H:i') }}</span>
                                <strong class="d-block">
                                    {{ $transicion->estado_anterior ?? 'inicio' }} → {{ $transicion->estado_nuevo }}
                                </strong>
                                <span class="text-muted small">
                                    {{ $transicion->actor?->name ?? 'sistema' }}
                                    @if ($transicion->motivo) · {{ $transicion->motivo }} @endif
                                </span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">Sin movimientos.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
