@extends('layouts.panel')

@section('titulo', $titulo)

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">{{ $titulo }}</h1>
        <p class="text-muted mb-0">
            La plantilla define la versión esperada del Excel para el año, mes y serie.
            Las hojas se guardan por año y serie interna: una por línea, con el formato <code>HOJA|RANGO</code>.
        </p>
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

    <div class="card mt-3">
        <div class="card-body">
            <form method="POST" action="{{ $accion }}">
                @csrf
                @if ($metodo !== 'POST')
                    @method($metodo)
                @endif

                <div class="row g-3 mb-3">
                    <div class="col-md-2">
                        <label for="anio" class="form-label">Año</label>
                        <input type="number" name="anio" id="anio" min="2000" max="2100"
                               value="{{ old('anio', $plantilla->anio) }}" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label for="mes" class="form-label">Mes</label>
                        <input type="number" name="mes" id="mes" min="1" max="12"
                               value="{{ old('mes', $plantilla->mes) }}" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="serie_id" class="form-label">Serie</label>
                        <select name="serie_id" id="serie_id" class="form-select" required>
                            @foreach ($series as $id => $codigo)
                                <option value="{{ $id }}" @selected((string) old('serie_id', $plantilla->serie_id) === (string) $id)>
                                    {{ $codigo }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="version" class="form-label">Versión</label>
                        <input type="text" name="version" id="version" maxlength="20"
                               value="{{ old('version', $plantilla->version) }}" class="form-control" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="serie_excel" class="form-label">Serie del Excel</label>
                        <input type="text" name="serie_excel" id="serie_excel" maxlength="60"
                               value="{{ old('serie_excel', $plantilla->serie_excel) }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="serie_interna" class="form-label">Serie interna</label>
                        <input type="text" name="serie_interna" id="serie_interna" maxlength="60"
                               value="{{ old('serie_interna', $plantilla->serie_interna) }}" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="hojas" class="form-label">Hojas obligatorias</label>
                    <textarea name="hojas" id="hojas" rows="6" class="form-control">{{ $hojas }}</textarea>
                    <small class="form-text text-muted">
                        Una hoja por línea, por ejemplo <code>A01|C6:V40</code>. Sin rango, se deja vacío.
                    </small>
                </div>

                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="{{ route('admin.plantillas.index') }}" class="btn btn-link">Cancelar</a>
            </form>
        </div>
    </div>
@endsection