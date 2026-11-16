@extends('layouts.panel')

@section('titulo', 'Mi cuenta')

@section('contenido')
    <div class="content-header px-0">
        <h1 class="m-0">Mi cuenta</h1>
        <p class="text-muted mb-0">
            El correo lo administra el portal: si necesita cambiarlo, pídalo al administrador.
        </p>
    </div>

    @if (session('ok'))
        <div class="alert alert-success mt-3">{{ session('ok') }}</div>
    @endif

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
            <form method="POST" action="{{ route('perfil.update') }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nombre</label>
                        <input type="text" name="name" id="name" class="form-control"
                               value="{{ old('name', $usuario->name) }}" maxlength="150" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Correo</label>
                        <input type="email" id="email" class="form-control" value="{{ $usuario->email }}" disabled>
                        <small class="form-text text-muted">Lo fija el administrador.</small>
                    </div>
                </div>

                <hr>

                <p class="text-muted">
                    Para cambiar la contraseña, escriba la actual y la nueva (mínimo 12 caracteres).
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="actual" class="form-label">Contraseña actual</label>
                        <input type="password" name="actual" id="actual" class="form-control" autocomplete="current-password">
                    </div>
                    <div class="col-md-4">
                        <label for="password" class="form-label">Contraseña nueva</label>
                        <input type="password" name="password" id="password" class="form-control" autocomplete="new-password">
                    </div>
                    <div class="col-md-4">
                        <label for="password_confirmation" class="form-label">Repetir la nueva</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Guardar</button>
            </form>
        </div>
    </div>
@endsection