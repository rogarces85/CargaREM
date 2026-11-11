@extends('layouts.panel')

@section('titulo', 'Envíos')

@section('contenido')
    <div class="content-header px-0 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0">Envíos</h1>
            <p class="text-muted mb-0">Archivos REM por establecimiento y período.</p>
        </div>
    </div>

    @if (session('ok'))
        <div class="alert alert-success mt-3 mb-0">{{ session('ok') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mt-3 mb-0">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($puedeCrear)
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Nuevo envío</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('envios.store') }}" class="form-row align-items-end">
                    @csrf
                    <div class="form-group col-md-5">
                        <label for="establecimiento_id">Establecimiento</label>
                        <select name="establecimiento_id" id="establecimiento_id" class="form-control" required>
                            @foreach ($establecimientos as $establecimiento)
                                <option value="{{ $establecimiento->id }}">
                                    {{ $establecimiento->codigo }} — {{ $establecimiento->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="periodo">Período</label>
                        <select name="periodo" id="periodo" class="form-control" required>
                            @forelse ($periodos as $periodo)
                                <option value="{{ $periodo->anio }}-{{ $periodo->mes }}">
                                    {{ sprintf('%02d/%d', $periodo->mes, $periodo->anio) }} · {{ $periodo->estado }}
                                </option>
                            @empty
                                <option value="">No hay períodos abiertos</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <button type="submit" class="btn btn-primary btn-block" @disabled($periodos->isEmpty())>
                            Crear envío
                        </button>
                    </div>
                </form>
                <small class="text-muted">
                    El período se elige de los que están abiertos o en revisión. Un período cerrado no acepta cargas.
                </small>
            </div>
        </div>
    @endif

    <div class="card mt-3">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Establecimiento</th>
                        <th>Estado</th>
                        <th class="text-center">Series</th>
                        <th>Actualizado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($envios as $envio)
                        <tr>
                            <td>{{ sprintf('%02d/%d', $envio->mes, $envio->anio) }}</td>
                            <td>
                                <strong>{{ $envio->establecimiento->codigo }}</strong>
                                <span class="text-muted d-block small">{{ $envio->establecimiento->nombre }}</span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $envio->estado === 'aprobado' || $envio->estado === 'cargado' ? 'success' : ($envio->estado === 'con_errores' || $envio->estado === 'anulado' ? 'danger' : 'info') }}">
                                    {{ str_replace('_', ' ', $envio->estado) }}
                                </span>
                            </td>
                            <td class="text-center">{{ $envio->archivosVigentes->count() }}</td>
                            <td>{{ $envio->updated_at?->format('d-m-Y H:i') }}</td>
                            <td class="text-right">
                                <a href="{{ route('envios.show', $envio) }}" class="btn btn-sm btn-outline-primary">
                                    Abrir
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Todavía no hay envíos. Cree uno con el formulario de arriba.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $envios->links() }}</div>
@endsection
