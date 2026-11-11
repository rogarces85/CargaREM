@extends('layouts.panel')

@section('titulo', 'Matriz de series')

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">{{ $establecimiento->nombre }}</h1>
        <p class="text-muted mb-0">Código {{ $establecimiento->codigo }}</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mt-3">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.matriz.update', $establecimiento) }}">
        @csrf
        @method('PUT')

        <div class="card mt-3">
            <div class="card-body table-responsive p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Serie</th>
                            <th class="text-center">Exigida</th>
                            <th>Vigente desde</th>
                            <th>Vigente hasta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($series as $serie)
                            @php
                                $actual = $actuales->get($serie->id);
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $serie->codigo }}</strong>
                                    <span class="text-muted small d-block">{{ $serie->nombre }}</span>
                                </td>
                                <td class="text-center">
                                    <input type="hidden" name="series[{{ $serie->id }}][obligatoria]" value="0">
                                    <input type="checkbox"
                                           name="series[{{ $serie->id }}][obligatoria]"
                                           value="1"
                                           @checked(old("series.{$serie->id}.obligatoria", $actual?->obligatoria))>
                                </td>
                                <td>
                                    <input type="date"
                                           name="series[{{ $serie->id }}][vigente_desde]"
                                           value="{{ old("series.{$serie->id}.vigente_desde", $actual?->vigente_desde) }}"
                                           class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="date"
                                           name="series[{{ $serie->id }}][vigente_hasta]"
                                           value="{{ old("series.{$serie->id}.vigente_hasta", $actual?->vigente_hasta) }}"
                                           class="form-control form-control-sm">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Guardar matriz</button>
        <a href="{{ route('admin.matriz.index') }}" class="btn btn-link">Cancelar</a>
    </form>
@endsection
