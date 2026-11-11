@extends('layouts.panel')

@section('titulo', $titulo)

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">{{ $titulo }}</h1>
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

                @foreach ($campos as $campo)
                    <div class="form-group">
                        <label for="{{ $campo['name'] }}">{{ $campo['label'] }}</label>

                        @if (($campo['type'] ?? 'text') === 'select')
                            <select name="{{ $campo['name'] }}"
                                    id="{{ $campo['name'] }}"
                                    class="form-control @error($campo['name']) is-invalid @enderror">
                                @foreach ($campo['options'] as $valor => $texto)
                                    <option value="{{ $valor }}"
                                        @selected((string) old($campo['name'], $registro?->{$campo['name']}) === (string) $valor)>
                                        {{ $texto }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif (($campo['type'] ?? 'text') === 'checkbox')
                            <div class="form-check">
                                <input type="hidden" name="{{ $campo['name'] }}" value="0">
                                <input type="checkbox"
                                       name="{{ $campo['name'] }}"
                                       id="{{ $campo['name'] }}"
                                       value="1"
                                       class="form-check-input"
                                       @checked(old($campo['name'], $registro?->{$campo['name']} ?? true))>
                            </div>
                        @else
                            <input type="{{ $campo['type'] ?? 'text' }}"
                                   name="{{ $campo['name'] }}"
                                   id="{{ $campo['name'] }}"
                                   value="{{ old($campo['name'], $registro?->{$campo['name']}) }}"
                                   class="form-control @error($campo['name']) is-invalid @enderror"
                                   @if (! empty($campo['required'])) required @endif>
                        @endif

                        @error($campo['name'])
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="{{ route($ruta.'.index') }}" class="btn btn-link">Cancelar</a>
            </form>
        </div>
    </div>
@endsection
