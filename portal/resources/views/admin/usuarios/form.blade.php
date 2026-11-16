@extends('layouts.panel')

@section('titulo', $titulo)

@section('contenido')
    <div class="mx-auto max-w-3xl space-y-6">

        <x-ui.page-header
            :titulo="$titulo"
            :migas="['Panel' => route('panel'), 'Usuarios' => route('admin.usuarios.index'), 'Editar' => null]"
        />

        <form method="POST" action="{{ $accion }}" class="space-y-6">
            @csrf
            @if ($metodo !== 'POST')
                @method($metodo)
            @endif

            <x-ui.card class="p-5">
                <div class="space-y-4">
                    <x-ui.field nombre="name" etiqueta="Nombre" :error="$errors->first('name')" requerido>
                        <input type="text" name="name" id="name" value="{{ old('name', $usuario->name) }}" required
                               class="input"
                               @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    </x-ui.field>

                    <x-ui.field nombre="email" etiqueta="Correo" :error="$errors->first('email')" requerido>
                        <input type="email" name="email" id="email" value="{{ old('email', $usuario->email) }}" required
                               class="input"
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    </x-ui.field>

                    <x-ui.field
                        nombre="password"
                        :etiqueta="$usuario->exists ? 'Contraseña (dejar vacío para no cambiarla)' : 'Contraseña'"
                        :error="$errors->first('password')"
                        ayuda="Mínimo 12 caracteres."
                        :requerido="! $usuario->exists"
                    >
                        <input type="password" name="password" id="password" autocomplete="new-password"
                               class="input" @if (! $usuario->exists) required @endif
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    </x-ui.field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field nombre="rol" etiqueta="Rol" :error="$errors->first('rol')" requerido>
                            <select name="rol" id="rol" class="select" required>
                                @foreach ($roles as $rol)
                                    <option value="{{ $rol }}" @selected(old('rol', $rolActual) === $rol)>{{ $rol }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <div class="flex items-end pb-2">
                            <label for="activo" class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="hidden" name="activo" value="0">
                                <input type="checkbox" name="activo" id="activo" value="1" class="check"
                                       @checked(old('activo', $usuario->activo ?? true))>
                                Activo
                            </label>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="check" class="size-4" />
                    Guardar
                </button>
                <a href="{{ route('admin.usuarios.index') }}" class="btn btn-ghost">Cancelar</a>
            </div>
        </form>

    </div>
@endsection
