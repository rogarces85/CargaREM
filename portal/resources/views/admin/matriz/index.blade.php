@extends('layouts.panel')

@section('titulo', 'Matriz establecimiento × serie')

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">Matriz establecimiento × serie</h1>
        <p class="text-muted mb-0">
            Qué series se le exigen a cada establecimiento. Sin fila, la serie no se exige (R-SER-05).
        </p>
    </div>

    @if (session('ok'))
        <div class="alert alert-success mt-3">{{ session('ok') }}</div>
    @endif

    <div class="card mt-3">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Establecimiento</th>
                        <th class="text-end">Series exigidas</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($establecimientos as $establecimiento)
                        <tr>
                            <td>{{ $establecimiento->codigo }}</td>
                            <td>{{ $establecimiento->nombre }}</td>
                            <td class="text-end">{{ $establecimiento->series_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.matriz.edit', $establecimiento) }}"
                                   class="btn btn-sm btn-outline-secondary">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Sin establecimientos importados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $establecimientos->links() }}</div>
@endsection