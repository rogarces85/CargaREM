@extends('layouts.panel')

@section('titulo', 'Plantillas')

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">Plantillas</h1>
        <p class="text-muted mb-0">
            Versión de formato esperada por año, mes y serie. Vienen del catálogo del Minsal y se
            reemplazan importando un snapshot; no se editan a mano.
        </p>
    </div>

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
                        <th>Snapshot</th>
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
                            <td>{{ $plantilla->snapshot_id }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Sin plantillas importadas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $plantillas->links() }}</div>
@endsection
