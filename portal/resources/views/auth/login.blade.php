@extends('layouts.auth')

@section('titulo', 'Ingresar')

@section('contenido')
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Ingrese con su cuenta del portal</p>

            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                <div class="input-group mb-3">
                    <input type="email"
                           name="email"
                           id="email"
                           class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email') }}"
                           placeholder="Correo"
                           autocomplete="username"
                           required
                           autofocus>
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
                           id="password"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="Contraseña"
                           autocomplete="current-password"
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                    @error('password')
                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-7">
                        <div class="icheck-primary">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Recordarme</label>
                        </div>
                    </div>
                    <div class="col-5">
                        <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
                    </div>
                </div>
            </form>

            @if (Route::has('password.request'))
                <p class="mt-3 mb-0">
                    <a href="{{ route('password.request') }}">¿Olvidó su contraseña?</a>
                </p>
            @endif

            <p class="mt-2 mb-0 text-muted small">
                Las cuentas las crea el administrador del portal.
            </p>
        </div>
    </div>
@endsection
