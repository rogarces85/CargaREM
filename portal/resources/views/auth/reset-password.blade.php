@extends('layouts.auth')

@section('titulo', 'Nueva contraseña')

@section('contenido')
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Defina su nueva contraseña</p>

            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div class="input-group mb-3">
                    <input type="email"
                           name="email"
                           class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $request->email) }}"
                           placeholder="Correo"
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                    </div>
                    @error('email')
                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <div class="input-group mb-3">
                    <input type="password"
                           name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="Nueva contraseña"
                           autocomplete="new-password"
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                    @error('password')
                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <div class="input-group mb-3">
                    <input type="password"
                           name="password_confirmation"
                           class="form-control"
                           placeholder="Repita la contraseña"
                           autocomplete="new-password"
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Cambiar contraseña</button>
            </form>
        </div>
    </div>
@endsection
