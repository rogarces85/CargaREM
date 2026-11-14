@extends('layouts.panel')

@section('titulo', 'Plantillas')

@section('contenido')
    <div class="content-header px-0 d-flex justify-content-between align-items-start">
        <div>
            <h1 class="m-0">Plantillas</h1>
            <p class="text-muted mb-0">
                Versión de formato esperada por año, mes y serie. Las hojas se editan por año y serie interna.
            </p>
        </div>
        <a href="{{ route('admin.plantillas.create') }}" class="btn btn-primary">Nueva plantilla</a>
    </div>

    @if (session('ok'))
        <div class="alert alert-success mt-3">{{ session('ok') }}</div>
    @endif

    <div class="card mt-3">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>Año</th>
                        <th>Mes</th>
                        <th>Serie</th>
                        <th>Serie del Excel</th>
                        <th>Versión</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plantillas as $plantilla)
                        <tr>
                            <td>{{ $plantilla->anio }}</td>
                            <td>{{ $plantilla->mes }}</td>
                            <td>{{ $plantilla->serie?->codigo }}</td>
                            <td>{{ $plantilla->serie_excel }}</td>
                            <td>{{ $plantilla->version }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.plantillas.edit', $plantilla) }}" class="btn btn-sm btn-outline-primary">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Sin plantillas cargadas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $plantillas->links() }}</div>
@endsection
