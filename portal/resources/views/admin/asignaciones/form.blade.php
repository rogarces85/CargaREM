@extends('layouts.panel')

@section('titulo', 'Asignar establecimientos')

@section('contenido')
    @php
        $esRevisor = $usuario->hasRole('revisor');
        $nombreRol = $esRevisor ? 'Equipo REM' : 'Encargado de establecimiento';
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">

        <x-ui.page-header
            :titulo="'Asignar a ' . $usuario->name"
            :subtitulo="$nombreRol . ' · ' . $usuario->email"
            :migas="[
                'Panel' => route('panel'),
                'Usuarios' => route('admin.usuarios.index'),
                'Asignaciones' => route('admin.asignaciones.index', ['rol' => $esRevisor ? 'revisor' : 'encargado']),
                $usuario->name => null,
            ]"
        />

        <x-ui.alert tono="info">
            @if ($esRevisor)
                Cada integrante del Equipo REM revisa <strong>solo</strong> los establecimientos que tenga asignados.
                Sin asignación, su bandeja y su panel aparecen vacíos.
            @else
                El encargado carga y corrige <strong>solo</strong> los establecimientos que tenga asignados.
            @endif
            Un establecimiento puede estar asignado a más de una persona.
        </x-ui.alert>

        <form method="POST" action="{{ route('admin.asignaciones.update', $usuario) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-ui.card class="p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold">Establecimientos</h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Agrupados por comuna. <span id="contadorAsignados" class="tabular font-medium">{{ count($asignados) }}</span>
                            seleccionado(s).
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" data-asignar-todos class="btn btn-secondary btn-sm">
                            <x-ui.icon name="check" class="size-3.5" />
                            Todos
                        </button>
                        <button type="button" data-asignar-ninguno class="btn btn-ghost btn-sm">
                            <x-ui.icon name="x" class="size-3.5" />
                            Ninguno
                        </button>
                    </div>
                </div>

                @error('establecimientos')
                    <p class="campo-error mt-3">
                        <x-ui.icon name="circle-exclamation-point" class="mt-px size-3.5 shrink-0" />
                        <span>{{ $message }}</span>
                    </p>
                @enderror

                <div class="mt-4 space-y-5">
                    @foreach ($establecimientos as $comuna => $lista)
                        <fieldset>
                            <legend class="mb-2 flex w-full items-center justify-between gap-3 border-b border-border pb-1.5">
                                <span class="text-sm font-medium">{{ $comuna }}</span>
                                <span class="text-xs text-muted-foreground">{{ $lista->count() }} establecimiento(s)</span>
                            </legend>

                            <div class="grid gap-1.5 sm:grid-cols-2">
                                @foreach ($lista as $establecimiento)
                                    <label for="est-{{ $establecimiento->id }}"
                                           class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-1.5 text-sm transition-colors hover:bg-accent/60">
                                        <input type="checkbox"
                                               name="establecimientos[]"
                                               id="est-{{ $establecimiento->id }}"
                                               value="{{ $establecimiento->id }}"
                                               class="check mt-0.5"
                                               data-asignacion
                                               @checked(in_array($establecimiento->id, $asignados, true))>

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
            </x-ui.card>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="check" class="size-4" />
                    Guardar asignación
                </button>
                <a href="{{ route('admin.asignaciones.index', ['rol' => $esRevisor ? 'revisor' : 'encargado']) }}"
                   class="btn btn-ghost">Cancelar</a>
            </div>
        </form>

    </div>
@endsection

@push('scripts')
    <script>
        // Selección en bloque y contador. La casilla de cada establecimiento es
        // la fuente de verdad; estos botones solo la marcan.
        (function () {
            const casillas = Array.from(document.querySelectorAll('[data-asignacion]'));
            const contador = document.getElementById('contadorAsignados');

            function actualizar() {
                if (contador) {
                    contador.textContent = casillas.filter((c) => c.checked).length;
                }
            }

            casillas.forEach((c) => c.addEventListener('change', actualizar));

            document.querySelector('[data-asignar-todos]')?.addEventListener('click', () => {
                casillas.forEach((c) => { c.checked = true; });
                actualizar();
            });

            document.querySelector('[data-asignar-ninguno]')?.addEventListener('click', () => {
                casillas.forEach((c) => { c.checked = false; });
                actualizar();
            });

            actualizar();
        })();
    </script>
@endpush
