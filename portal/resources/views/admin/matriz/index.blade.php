@extends('layouts.panel')

@section('titulo', 'Matriz establecimiento × serie')

@section('contenido')
    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            titulo="Matriz establecimiento × serie"
            subtitulo="Qué series se le exigen a cada establecimiento. Sin fila, la serie no se exige (R-SER-05)."
            :migas="['Panel' => route('panel'), 'Matriz de series' => null]"
        />

        @if ($establecimientos->isEmpty())
            <x-ui.empty
                icono="table"
                titulo="Sin establecimientos importados"
                texto="Cargue el catálogo de establecimientos para poder asignarles series."
            />
        @else
            <x-ui.table>
                <caption class="sr-only">Series exigidas por establecimiento</caption>
                <thead>
                    <tr>
                        <th scope="col">Código</th>
                        <th scope="col">Establecimiento</th>
                        <th scope="col" class="text-end">Series exigidas</th>
                        <th scope="col" class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($establecimientos as $establecimiento)
                        <tr>
                            <td class="tabular font-medium">{{ $establecimiento->codigo }}</td>
                            <td>{{ $establecimiento->nombre }}</td>
                            <td class="text-end tabular">{{ $establecimiento->series_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.matriz.edit', $establecimiento) }}" class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="pencil" class="size-3.5" />
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <div>{{ $establecimientos->links() }}</div>
        @endif

    </div>
@endsection
