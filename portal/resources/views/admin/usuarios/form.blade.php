@extends('layouts.panel')

@section('titulo', $titulo)

@section('contenido')
    @php
        $reparte = in_array($rolActual, ['revisor', 'encargado'], true);
    @endphp

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
                        <x-ui.field nombre="rol" etiqueta="Rol" :error="$errors->first('rol')" requerido
                                    ayuda="«Equipo REM» es el rol que revisa la estadística.">
                            <select name="rol" id="rol" class="select" required>
                                @foreach ($roles as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected(old('rol', $rolActual) === $valor)>{{ $etiqueta }}</option>
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

            {{-- Reparto por establecimiento (RF-042). Solo tiene sentido para los
                 roles cuyo alcance son los establecimientos asignados. --}}
            @unless ($usuario->exists && $usuario->hasRole('admin'))
                <x-ui.card class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold">Establecimientos asignados</h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Solo para Encargado y Equipo REM. Sin asignación no ven ningún dato.
                                Agrupados por comuna.
                            </p>
                        </div>

                        <span class="text-xs text-muted-foreground">
                            <span data-contador-asignados class="tabular font-medium">{{ count($asignados) }}</span>
                            seleccionado(s)
                        </span>
                    </div>

                    @error('establecimientos')
                        <p class="campo-error mt-3">
                            <x-ui.icon name="circle-exclamation-point" class="mt-px size-3.5 shrink-0" />
                            <span>{{ $message }}</span>
                        </p>
                    @enderror

                    @if ($establecimientos->isEmpty())
                        <p class="mt-4 text-sm text-muted-foreground">
                            No hay establecimientos activos en el catálogo. Impórtelo antes de repartir.
                        </p>
                    @else
                        {{-- `max-h` con desplazamiento: 86 establecimientos no caben
                             sueltos y el formulario dejaría el botón de guardar a
                             dos pantallas de distancia. La altura se fija en `style`
                             porque Tailwind no resuelve un `max-height` compuesto. --}}
                        <div class="mt-4 max-h-80 space-y-4 overflow-y-auto pe-1" style="max-height: 20rem">
                            @foreach ($establecimientos as $comuna => $lista)
                                <fieldset>
                                    <legend class="mb-1.5 w-full border-b border-border pb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        {{ $comuna }} · {{ $lista->count() }}
                                    </legend>

                                    <div class="grid gap-1 sm:grid-cols-2">
                                        @foreach ($lista as $establecimiento)
                                            <label for="est-{{ $establecimiento->id }}"
                                                   class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-1.5 text-sm transition-colors hover:bg-accent/60">
                                                <input type="checkbox"
                                                       name="establecimientos[]"
                                                       id="est-{{ $establecimiento->id }}"
                                                       value="{{ $establecimiento->id }}"
                                                       class="check mt-0.5"
                                                       data-asignacion
                                                       @checked(in_array($establecimiento->id, old('establecimientos', $asignados), false))>

                                                <span class="min-w-0">
                                                    <span class="block font-medium leading-tight">{{ $establecimiento->codigo }}</span>
                                                    <span class="block truncate text-xs text-muted-foreground">{{ $establecimiento->nombre }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            @endunless

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

@push('scripts')
    <script>
        // El contador es informativo: la casilla es la fuente de verdad y el
        // formulario envía lo que esté marcado.
        (function () {
            const casillas = Array.from(document.querySelectorAll('[data-asignacion]'));
            const contador = document.querySelector('[data-contador-asignados]');

            if (!contador) {
                return;
            }

            const actualizar = () => {
                contador.textContent = casillas.filter((c) => c.checked).length;
            };

            casillas.forEach((c) => c.addEventListener('change', actualizar));
            actualizar();
        })();
    </script>
@endpush
