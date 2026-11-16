@extends('layouts.panel')

@section('titulo', 'Revisión')

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">Bandeja de revisión</h1>
        <p class="text-muted mb-0">
            Envíos de los establecimientos del Servicio de Salud. Los más antiguos primero.
        </p>
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

    <div class="card mt-3">
        <div class="card-body">
            <form method="GET" action="{{ route('revision.index') }}" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="estado" class="form-label">Estado</label>
                    <select name="estado" id="estado" class="form-select">
                        @foreach ($estados as $valor => $texto)
                            <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === (string) $valor)>
                                {{ $texto }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
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

                <div class="col-md-2">
                    <label for="anio" class="form-label">Año</label>
                    <input type="number" name="anio" id="anio" class="form-control"
                           value="{{ $filtros['anio'] ?? '' }}" min="2000" max="2100" placeholder="Todos">
                </div>

                <div class="col-md-2">
                    <label for="dias" class="form-label">Antigüedad</label>
                    <select name="dias" id="dias" class="form-select">
                        <option value="">Cualquiera</option>
                        @foreach ([3, 7, 15, 30] as $dias)
                            <option value="{{ $dias }}" @selected(($filtros['dias'] ?? '') == $dias)>
                                Más de {{ $dias }} días
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="fuera_de_plazo" class="form-label">Plazo</label>
                    <select name="fuera_de_plazo" id="fuera_de_plazo" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filtros['fuera_de_plazo'] ?? '') == '1')>Fuera de plazo</option>
                    </select>
                </div>

                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="col-md-1">
                    <a href="{{ route('revision.index') }}" class="btn btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Establecimiento</th>
                        <th>Período</th>
                        <th>Estado</th>
                        <th class="text-center">Series</th>
                        <th class="text-center">Observaciones</th>
                        <th>Esperando desde</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($envios as $envio)
                        <tr>
                            <td>
                                <strong>{{ $envio->establecimiento->codigo }}</strong>
                                <span class="text-muted small d-block">{{ $envio->establecimiento->nombre }}</span>
                            </td>
                            <td>{{ sprintf('%02d/%d', $envio->mes, $envio->anio) }}</td>
                            <td>
                                <span class="badge bg-{{ $envio->estado === 'aprobado' ? 'success' : ($envio->estado === 'observado' ? 'warning' : 'info') }}">
                                    {{ str_replace('_', ' ', $envio->estado) }}
                                </span>
                                @if ($envio->estaFueraDePlazo())
                                    <span class="badge bg-danger d-block mt-1">Fuera de plazo</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $envio->archivosVigentes->count() }}</td>
                            <td class="text-center">
                                @if ($envio->abiertas > 0)
                                    <span class="badge bg-warning text-dark">{{ $envio->abiertas }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                {{ $envio->fecha_envio?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="text-end">
                                <a href="{{ route('envios.show', $envio) }}" class="btn btn-sm btn-outline-secondary">
                                    Ver
                                </a>

                                @can('observar', $envio)
                                    <button type="button" class="btn btn-sm btn-warning btn-observar me-1"
                                            data-id="{{ $envio->id }}"
                                            data-referencia="{{ $envio->establecimiento->codigo }} · {{ sprintf('%02d/%d', $envio->mes, $envio->anio) }}"
                                            data-series="{{ $envio->archivosVigentes->map(fn ($a) => ['id' => $a->serie_id, 'codigo' => $a->serie?->codigo])->toJson() }}"
                                            data-bs-toggle="modal" data-bs-target="#modalObservar">
                                        Observar
                                    </button>
                                @endcan

                                @can('aprobar', $envio)
                                    <button type="button" class="btn btn-sm btn-success btn-aprobar me-1"
                                            data-id="{{ $envio->id }}"
                                            data-referencia="{{ $envio->establecimiento->codigo }} · {{ sprintf('%02d/%d', $envio->mes, $envio->anio) }}"
                                            data-abiertas="{{ $envio->abiertas }}"
                                            data-bs-toggle="modal" data-bs-target="#modalAprobar">
                                        Aprobar
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No hay envíos con ese filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $envios->links() }}</div>

    {{-- Modal observar --}}
    <div class="modal fade" id="modalObservar" tabindex="-1" aria-labelledby="tituloObservar" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" id="formObservar" action="">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tituloObservar">Observar envío</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Envío <strong id="referenciaObservar"></strong></p>
                        <div class="mb-3">
                            <label for="serieObservar" class="form-label">¿Sobre qué serie?</label>
                            <select name="serie_id" id="serieObservar" class="form-select">
                                <option value="">Todo el envío</option>
                            </select>
                            <small class="form-text text-muted">
                                Si elige una serie, la observación se resuelve sola cuando el encargado suba la corrección.
                            </small>
                        </div>

                        <div class="mb-0">
                            <label for="texto" class="form-label">¿Qué debe corregir el establecimiento?</label>
                            <textarea name="texto" id="texto" rows="4" class="form-control" maxlength="2000" required></textarea>
                            <small class="form-text text-muted">
                                La observación vuelve al establecimiento y queda en la bitácora.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Observar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal aprobar --}}
    <div class="modal fade" id="modalAprobar" tabindex="-1" aria-labelledby="tituloAprobar" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" id="formAprobar" action="">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tituloAprobar">Aprobar envío</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Envío <strong id="referenciaAprobar"></strong></p>
                        <div class="alert alert-warning d-none" id="avisoObservaciones">
                            Este envío tiene observaciones sin resolver. No se puede aprobar hasta resolverlas.
                        </div>
                        <div class="mb-0">
                            <label for="comentario" class="form-label">Comentario (opcional)</label>
                            <input type="text" name="comentario" id="comentario" class="form-control" maxlength="500">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" id="botonAprobar">Aprobar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.btn-observar').forEach(function (boton) {
            boton.addEventListener('click', function () {
                const id = this.dataset.id;
                document.getElementById('formObservar').action = '/revision/' + id + '/observar';
                document.getElementById('referenciaObservar').textContent = this.dataset.referencia;
                document.getElementById('texto').value = '';

                const select = document.getElementById('serieObservar');
                select.length = 1;

                JSON.parse(this.dataset.series || '[]').forEach(function (serie) {
                    const opcion = document.createElement('option');
                    opcion.value = serie.id;
                    opcion.textContent = serie.codigo;
                    select.appendChild(opcion);
                });
            });
        });

        document.querySelectorAll('.btn-aprobar').forEach(function (boton) {
            boton.addEventListener('click', function () {
                const id = this.dataset.id;
                const abiertas = parseInt(this.dataset.abiertas || '0', 10);

                document.getElementById('formAprobar').action = '/revision/' + id + '/aprobar';
                document.getElementById('referenciaAprobar').textContent = this.dataset.referencia;

                document.getElementById('avisoObservaciones').classList.toggle('d-none', abiertas === 0);
                document.getElementById('botonAprobar').disabled = abiertas > 0;
            });
        });
    </script>
@endpush