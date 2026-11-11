@extends('layouts.auth')

@section('titulo', 'Segundo factor')

@section('contenido')
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Verificación en dos pasos</p>

            <form method="POST" action="{{ route('two-factor.login') }}" novalidate>
                @csrf

                <div class="input-group mb-3">
                    <input type="text"
                           name="code"
                           class="form-control @error('code') is-invalid @enderror"
                           placeholder="Código de 6 dígitos"
                           inputmode="numeric"
                           autocomplete="one-time-code"
                           autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-key"></span></div>
                    </div>
                    @error('code')
                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block">Verificar</button>
            </form>

            <form method="POST" action="{{ route('two-factor.login') }}" class="mt-3">
                @csrf

                <div class="input-group mb-3">
                    <input type="text"
                           name="recovery_code"
                           class="form-control @error('recovery_code') is-invalid @enderror"
                           placeholder="O un código de recuperación">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-life-ring"></span></div>
                    </div>
                    @error('recovery_code')
                        <span class="invalid-feedback" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-outline-secondary btn-block">Usar código de recuperación</button>
            </form>
        </div>
    </div>
@endsection
