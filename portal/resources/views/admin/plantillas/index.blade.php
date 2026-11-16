@extends('layouts.panel')

@section('titulo', 'Plantillas')

@section('contenido')
    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            titulo="Plantillas"
            subtitulo="Versión esperada del Excel por año, mes y serie."
            :migas="['Panel' => route('panel'), 'Plantillas' => null]"
        >
            <x-slot:acciones>
                <a href="{{ route('admin.plantillas.create') }}" class="btn btn-primary">
                    <x-ui.icon name="plus" class="size-4" />
                    Nueva plantilla
                </a>
            </x-slot:acciones>
        </x-ui.page-header>

        @if ($plantillas->isEmpty())
            <x-ui.empty
                icono="file-spreadsheet"
                titulo="Todavía no hay plantillas"
                texto="Una plantilla define qué hojas y qué rango se espera en el Excel de una serie para un período."
            >
                <x-slot:accion>
                    <a href="{{ route('admin.plantillas.create') }}" class="btn btn-primary">
                        <x-ui.icon name="plus" class="size-4" />
                        Nueva plantilla
                    </a>
                </x-slot:accion>
            </x-ui.empty>
        @else
            <x-ui.table>
                <caption class="sr-only">Plantillas por período y serie</caption>
                <thead>
                    <tr>
                        <th scope="col">Período</th>
                        <th scope="col">Serie</th>
                        <th scope="col" class="hidden sm:table-cell">Versión</th>
                        <th scope="col" class="hidden md:table-cell">Serie del Excel</th>
                        <th scope="col" class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($plantillas as $plantilla)
                        <tr>
                            <td class="tabular font-medium">{{ sprintf('%02d/%d', $plantilla->mes, $plantilla->anio) }}</td>
                            <td>
                                <span class="block font-medium">{{ $plantilla->serie?->codigo ?? '—' }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ $plantilla->serie?->nombre }}</span>
                            </td>
                            <td class="hidden text-muted-foreground sm:table-cell">{{ $plantilla->version }}</td>
                            <td class="hidden truncate text-muted-foreground md:table-cell">{{ $plantilla->serie_excel }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.plantillas.edit', $plantilla) }}" class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="pencil" class="size-3.5" />
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <div>{{ $plantillas->links() }}</div>
        @endif

    </div>
@endsection
