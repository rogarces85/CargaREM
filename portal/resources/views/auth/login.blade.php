@extends('layouts.auth')

@section('titulo', 'Ingresar')

@section('contenido')
    <x-ui.card class="p-6">
        <p class="mb-5 text-sm text-muted-foreground">Ingrese con su cuenta del portal</p>

        <form method="POST" action="{{ route('login') }}" novalidate class="space-y-4">
            @csrf

            <x-ui.field nombre="email" etiqueta="Correo" :error="$errors->first('email')" requerido>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    class="input"
                    placeholder="nombre@servicio.cl"
                    autocomplete="username"
                    required
                    autofocus
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                >
            </x-ui.field>

            <x-ui.field nombre="password" etiqueta="Contraseña" :error="$errors->first('password')" requerido>
                <input
                    type="password"
                    name="password"
                    id="password"
                    class="input"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                >
            </x-ui.field>

            <label for="remember" class="flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" name="remember" id="remember" class="check">
                Recordarme
            </label>

            <button type="submit" class="btn btn-primary w-full">
                <x-ui.icon name="log-out" class="size-4 rotate-180" />
                Ingresar
            </button>
        </form>
    </x-ui.card>

    <p class="mt-6 text-center text-xs text-muted-foreground">
        Las cuentas las crea el administrador del portal.
        Si olvidó su contraseña, pídale que la restablezca.
    </p>
@endsection
