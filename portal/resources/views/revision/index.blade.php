@extends('layouts.panel')

@section('titulo', 'Revisión')

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">Bandeja de revisión</h1>
        <p class="text-muted mb-0">
            Envíos de los establecimientos de su Servicio de Salud. Los más antiguos primero.
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
            <form method="GET" action="{{ route('revision.index') }}" class="form-row align-items-end">
                <div class="form-group col-md-2">
                    <label for="estado">Estado</label>
                    <select name="estado" id="estado" class="form-control">
                        @foreach ($estados as $valor => $texto)
                            <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === (string) $valor)>
                                {{ $texto }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group col-md-3">
                    <label for="establecimiento_id">Establecimiento</label>
                    <select name="establecimiento_id" id="establecimiento_id" class="form-control">
                        <option value="">Todos</option>
                        @foreach ($establecimientos as $establecimiento)
                            <option value="{{ $establecimiento->id }}"
                                @selected(($filtros['establecimiento_id'] ?? '') == $establecimiento->id)>
                                {{ $establecimiento->codigo }} — {{ $establecimiento->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group col-md-2">
                    <label for="anio">Año</label>
                    <input type="number" name="anio" id="anio" class="form-control"
                           value="{{ $filtros['anio'] ?? '' }}" min="2000" max="2100" placeholder="Todos">
                </div>

                <div class="form-group col-md-2">
                    <label for="dias">Antigüedad</label>
                    <select name="dias" id="dias" class="form-control">
                        <option value="">Cualquiera</option>
                        @foreach ([3, 7, 15, 30] as $dias)
                            <option value="{{ $dias }}" @selected(($filtros['dias'] ?? '') == $dias)>
                                Más de {{ $dias }} días
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group col-md-3">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="{{ route('revision.index') }}" class="btn btn-link">Limpiar</a>
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
                        <th class="text-right">Acciones</th>
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
                                <span class="badge badge-{{ $envio->estado === 'aprobado' ? 'success' : ($envio->estado === 'observado' ? 'warning' : 'info') }}">
                                    {{ str_replace('_', ' ', $envio->estado) }}
                                </span>
                            </td>
                            <td class="text-center">{{ $envio->archivosVigentes->count() }}</td>
                            <td class="text-center">
                                @if ($envio->abiertas > 0)
                                    <span class="badge badge-warning">{{ $envio->abiertas }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                {{ $envio->fecha_envio?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="text-right">
                                <a href="{{ route('envios.show', $envio) }}" class="btn btn-sm btn-outline-secondary">
                                    Ver
                                </a>

                                @can('observar', $envio)
                                    <button type="button" class="btn btn-sm btn-warning btn-observar"
                                            data-id="{{ $envio->id }}"
                                            data-referencia="{{ $envio->establecimiento->codigo }} · {{ sprintf('%02d/%d', $envio->mes, $envio->anio) }}"
                                            data-toggle="modal" data-target="#modalObservar">
                                        Observar
                                    </button>
                                @endcan

                                @can('aprobar', $envio)
                                    <button type="button" class="btn btn-sm btn-success btn-aprobar"
                                            data-id="{{ $envio->id }}"
                                            data-referencia="{{ $envio->establecimiento->codigo }} · {{ sprintf('%02d/%d', $envio->mes, $envio->anio) }}"
                                            data-abiertas="{{ $envio->abiertas }}"
                                            data-toggle="modal" data-target="#modalAprobar">
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
    <div class="modal fade" id="modalObservar" tabindex="-1" role="dialog" aria-labelledby="tituloObservar" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" id="formObservar" action="">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tituloObservar">Observar envío</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Envío <strong id="referenciaObservar"></strong></p>
                        <div class="form-group mb-0">
                            <label for="texto">¿Qué debe corregir el establecimiento?</label>
                            <textarea name="texto" id="texto" rows="4" class="form-control" maxlength="2000" required></textarea>
                            <small class="form-text text-muted">
                                La observación vuelve al establecimiento y queda en la bitácora.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Observar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal aprobar --}}
    <div class="modal fade" id="modalAprobar" tabindex="-1" role="dialog" aria-labelledby="tituloAprobar" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" id="formAprobar" action="">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tituloAprobar">Aprobar envío</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Envío <strong id="referenciaAprobar"></strong></p>
                        <div class="alert alert-warning d-none" id="avisoObservaciones">
                            Este envío tiene observaciones sin resolver. No se puede aprobar hasta resolverlas.
                        </div>
                        <div class="form-group mb-0">
                            <label for="comentario">Comentario (opcional)</label>
                            <input type="text" name="comentario" id="comentario" class="form-control" maxlength="500">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-dismiss="modal">Cancelar</button>
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
