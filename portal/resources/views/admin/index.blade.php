@extends('layouts.panel')

@section('titulo', $titulo)

@section('contenido')
    <div class="content-header px-0 d-flex justify-content-between align-items-center">
        <h1 class="m-0">{{ $titulo }}</h1>
        <a href="{{ route($ruta.'.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo
        </a>
    </div>

    @if (session('ok'))
        <div class="alert alert-success mt-3">{{ session('ok') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger mt-3">{{ session('error') }}</div>
    @endif

    <div class="card mt-3">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap mb-0">
                <thead>
                    <tr>
                        @foreach ($columnas as $etiqueta => $campo)
                            <th>{{ $etiqueta }}</th>
                        @endforeach
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $fila)
                        <tr>
                            @foreach ($columnas as $campo)
                                <td>{{ data_get($fila, $campo) }}</td>
                            @endforeach
                            <td class="text-right">
                                <a href="{{ route($ruta.'.edit', $fila) }}" class="btn btn-sm btn-outline-secondary">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columnas) + 1 }}" class="text-center text-muted py-4">
                                No hay registros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $filas->links() }}</div>
@endsection
