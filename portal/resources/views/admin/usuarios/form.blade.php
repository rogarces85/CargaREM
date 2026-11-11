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

                <div class="form-group">
                    <label for="name">Nombre</label>
                    <input type="text" name="name" id="name" class="form-control"
                           value="{{ old('name', $usuario->name) }}" required>
                </div>

                <div class="form-group">
                    <label for="email">Correo</label>
                    <input type="email" name="email" id="email" class="form-control"
                           value="{{ old('email', $usuario->email) }}" required>
                </div>

                <div class="form-group">
                    <label for="password">
                        Contraseña
                        @if ($usuario->exists)
                            <span class="text-muted small">(dejar vacío para no cambiarla)</span>
                        @endif
                    </label>
                    <input type="password" name="password" id="password" class="form-control"
                           autocomplete="new-password" @if (! $usuario->exists) required @endif>
                    <small class="form-text text-muted">Mínimo 12 caracteres.</small>
                </div>

                <div class="form-group">
                    <label for="rol">Rol</label>
                    <select name="rol" id="rol" class="form-control" required>
                        @foreach ($roles as $rol)
                            <option value="{{ $rol }}" @selected(old('rol', $rolActual) === $rol)>{{ $rol }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-check mb-3">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" id="activo" value="1" class="form-check-input"
                           @checked(old('activo', $usuario->activo ?? true))>
                    <label for="activo" class="form-check-label">Activo</label>
                </div>

                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="{{ route('admin.usuarios.index') }}" class="btn btn-link">Cancelar</a>
            </form>
        </div>
    </div>
@endsection
