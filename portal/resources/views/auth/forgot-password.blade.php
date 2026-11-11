@extends('layouts.auth')

@section('titulo', 'Recuperar contraseña')

@section('contenido')
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Recuperar contraseña</p>

            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf

                <div class="input-group mb-3">
                    <input type="email"
                           name="email"
                           class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email') }}"
                           placeholder="Correo"
                           required
                           autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                    </div>
                    @error('email')
                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block">Enviar enlace</button>
            </form>

            <p class="mt-3 mb-0">
                <a href="{{ route('login') }}">Volver al acceso</a>
            </p>
        </div>
    </div>
@endsection
