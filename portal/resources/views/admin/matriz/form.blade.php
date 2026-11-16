@extends('layouts.panel')

@section('titulo', 'Matriz de series')

@section('contenido')
    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            :titulo="$establecimiento->nombre"
            subtitulo="Código {{ $establecimiento->codigo }}"
            :migas="['Panel' => route('panel'), 'Matriz de series' => route('admin.matriz.index'), 'Editar' => null]"
        />

        <form method="POST" action="{{ route('admin.matriz.update', $establecimiento) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-ui.table>
                <caption class="sr-only">Series exigidas a {{ $establecimiento->nombre }}</caption>
                <thead>
                    <tr>
                        <th scope="col">Serie</th>
                        <th scope="col" class="text-center">Exigida</th>
                        <th scope="col">Vigente desde</th>
                        <th scope="col">Vigente hasta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($series as $serie)
                        @php $actual = $actuales->get($serie->id); @endphp
                        <tr>
                            <td>
                                <span class="block font-medium">{{ $serie->codigo }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ $serie->nombre }}</span>
                            </td>

                            <td class="text-center">
                                <input type="hidden" name="series[{{ $serie->id }}][obligatoria]" value="0">
                                <input
                                    type="checkbox"
                                    name="series[{{ $serie->id }}][obligatoria]"
                                    value="1"
                                    class="check"
                                    aria-label="Exigir la serie {{ $serie->codigo }}"
                                    @checked(old("series.{$serie->id}.obligatoria", $actual?->obligatoria))
                                >
                            </td>

                            <td>
                                <input
                                    type="date"
                                    name="series[{{ $serie->id }}][vigente_desde]"
                                    value="{{ old("series.{$serie->id}.vigente_desde", $actual?->vigente_desde) }}"
                                    class="input py-1 text-xs"
                                    aria-label="Vigente desde, serie {{ $serie->codigo }}"
                                >
                            </td>

                            <td>
                                <input
                                    type="date"
                                    name="series[{{ $serie->id }}][vigente_hasta]"
                                    value="{{ old("series.{$serie->id}.vigente_hasta", $actual?->vigente_hasta) }}"
                                    class="input py-1 text-xs"
                                    aria-label="Vigente hasta, serie {{ $serie->codigo }}"
                                >
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="check" class="size-4" />
                    Guardar matriz
                </button>
                <a href="{{ route('admin.matriz.index') }}" class="btn btn-ghost">Cancelar</a>
            </div>
        </form>

    </div>
@endsection
