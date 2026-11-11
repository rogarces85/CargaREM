@extends('layouts.panel')

@section('titulo', 'Catálogos importados')

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">Catálogos importados</h1>
        <p class="text-muted mb-0">
            Importar un snapshot no cambia lo que el portal usa: el nuevo queda inactivo hasta que se active.
        </p>
    </div>

    @if (session('ok'))
        <div class="alert alert-success mt-3">{{ session('ok') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger mt-3">{{ session('error') }}</div>
    @endif

    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title mb-0">Importar desde ZIP</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.snapshots.importar') }}" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label for="archivo">Snapshot generado por <code>01-TOOLS/rem-export</code> (comprimido)</label>
                    <input type="file" name="archivo" id="archivo" class="form-control-file" accept=".zip" required>
                    @error('archivo')
                        <span class="invalid-feedback d-block">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Importar</button>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title mb-0">Snapshots</h3>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Origen</th>
                        <th>Diccionario</th>
                        <th>Establecimientos</th>
                        <th>Importado</th>
                        <th>Estado</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($snapshots as $snapshot)
                        <tr>
                            <td>{{ $snapshot->id }}</td>
                            <td>{{ $snapshot->origen }}</td>
                            <td>{{ number_format((int) $snapshot->filas_diccionario, 0, ',', '.') }}</td>
                            <td>{{ number_format((int) $snapshot->filas_establecimientos, 0, ',', '.') }}</td>
                            <td>{{ $snapshot->created_at?->format('d-m-Y H:i') }}</td>
                            <td>
                                @if ($snapshot->activo)
                                    <span class="badge badge-success">activo</span>
                                @else
                                    <span class="badge badge-secondary">inactivo</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @unless ($snapshot->activo)
                                    <form method="POST" action="{{ route('admin.snapshots.activar', $snapshot) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Activar</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Todavía no hay snapshots importados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $snapshots->links() }}</div>
@endsection
