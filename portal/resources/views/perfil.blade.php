@extends('layouts.panel')

@section('titulo', 'Mi cuenta')

@section('contenido')
    <div class="mx-auto max-w-3xl space-y-6">

        <x-ui.page-header
            titulo="Mi cuenta"
            subtitulo="Su nombre y su contraseña. El correo lo administra el portal."
            :migas="['Panel' => route('panel'), 'Mi cuenta' => null]"
        />

        <form method="POST" action="{{ route('perfil.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Datos --}}
            <x-ui.card class="p-5">
                <h2 class="text-sm font-semibold">Datos</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-ui.field nombre="name" etiqueta="Nombre" :error="$errors->first('name')" requerido>
                        <input type="text" name="name" id="name" value="{{ old('name', $usuario->name) }}"
                               maxlength="150" required class="input" autocomplete="name"
                               @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    </x-ui.field>

                    <x-ui.field nombre="email" etiqueta="Correo" ayuda="Lo fija el administrador.">
                        <input type="email" id="email" value="{{ $usuario->email }}" disabled class="input" autocomplete="username">
                    </x-ui.field>
                </div>
            </x-ui.card>

            {{-- Contraseña --}}
            <x-ui.card class="p-5">
                <h2 class="text-sm font-semibold">Contraseña</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Para cambiarla, escriba la actual y la nueva (mínimo 12 caracteres). Si no quiere cambiarla, deje los tres campos vacíos.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <x-ui.field nombre="actual" etiqueta="Contraseña actual" :error="$errors->first('actual')">
                        <input type="password" name="actual" id="actual" autocomplete="current-password" class="input"
                               @error('actual') aria-invalid="true" aria-describedby="actual-error" @enderror>
                    </x-ui.field>

                    <x-ui.field nombre="password" etiqueta="Contraseña nueva" :error="$errors->first('password')">
                        <input type="password" name="password" id="password" autocomplete="new-password" class="input"
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    </x-ui.field>

                    <x-ui.field nombre="password_confirmation" etiqueta="Repetir la nueva" :error="$errors->first('password_confirmation')">
                        <input type="password" name="password_confirmation" id="password_confirmation"
                               autocomplete="new-password" class="input"
                               @error('password_confirmation') aria-invalid="true" aria-describedby="password_confirmation-error" @enderror>
                    </x-ui.field>
                </div>
            </x-ui.card>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="check" class="size-4" />
                    Guardar
                </button>
                <a href="{{ route('panel') }}" class="btn btn-ghost">Cancelar</a>
            </div>
        </form>

    </div>
@endsection
