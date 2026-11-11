@extends('layouts.panel')

@section('titulo', 'Auditoría')

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">Auditoría</h1>
        <p class="text-muted mb-0">
            Registro append-only de accesos y operaciones. No se modifica ni se borra.
        </p>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.auditoria.index') }}" class="form-row align-items-end">
                <div class="form-group col-md-3">
                    <label for="evento">Evento</label>
                    <select name="evento" id="evento" class="form-control">
                        <option value="">Todos</option>
                        @foreach ($eventos as $evento)
                            <option value="{{ $evento }}" @selected(request('evento') === $evento)>{{ $evento }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group col-md-2">
                    <label for="entidad">Entidad</label>
                    <select name="entidad" id="entidad" class="form-control">
                        <option value="">Todas</option>
                        @foreach ($entidades as $entidad)
                            <option value="{{ $entidad }}" @selected(request('entidad') === $entidad)>{{ $entidad }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group col-md-2">
                    <label for="desde">Desde</label>
                    <input type="date" name="desde" id="desde" value="{{ request('desde') }}" class="form-control">
                </div>

                <div class="form-group col-md-2">
                    <label for="hasta">Hasta</label>
                    <input type="date" name="hasta" id="hasta" value="{{ request('hasta') }}" class="form-control">
                </div>

                <div class="form-group col-md-3">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="{{ route('admin.auditoria.index') }}" class="btn btn-link">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Evento</th>
                        <th>Entidad</th>
                        <th>Id</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registros as $registro)
                        <tr>
                            <td>{{ $registro->id }}</td>
                            <td>{{ $registro->created_at?->format('d-m-Y H:i:s') }}</td>
                            <td>{{ $registro->usuario?->email ?? '—' }}</td>
                            <td><code>{{ $registro->evento }}</code></td>
                            <td>{{ $registro->entidad ?? '—' }}</td>
                            <td>{{ $registro->entidad_id ?? '—' }}</td>
                            <td>{{ $registro->ip ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Sin registros para el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $registros->links() }}</div>
@endsection
