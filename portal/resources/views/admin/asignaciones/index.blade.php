@extends('layouts.panel')

@section('titulo', 'Asignaciones')

@section('contenido')
    @php
        $esRevisor = $rol === 'revisor';
        $nombreRol = $esRevisor ? 'Equipo REM' : 'Encargados';
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            titulo="Asignación de establecimientos"
            subtitulo="Qué establecimientos revisa o carga cada usuario."
            :migas="['Panel' => route('panel'), 'Usuarios' => route('admin.usuarios.index'), 'Asignaciones' => null]"
        />

        {{-- Cambio de pestaña entre los dos roles que se reparten por establecimiento. --}}
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.asignaciones.index', ['rol' => 'revisor']) }}"
               class="btn {{ $esRevisor ? 'btn-primary' : 'btn-secondary' }} btn-sm">
                <x-ui.icon name="clipboard-check" class="size-3.5" />
                Equipo REM
            </a>
            <a href="{{ route('admin.asignaciones.index', ['rol' => 'encargado']) }}"
               class="btn {{ ! $esRevisor ? 'btn-primary' : 'btn-secondary' }} btn-sm">
                <x-ui.icon name="file-up" class="size-3.5" />
                Encargados
            </a>
        </div>

        @if ($sinAsignacion->isNotEmpty())
            <x-ui.alert tono="warn">
                <span class="font-medium">{{ $sinAsignacion->count() }} usuario(s) sin establecimientos.</span>
                Mientras no tengan asignación no ven ningún dato: la bandeja y el panel aparecen vacíos.
                Asígneles al menos uno para que puedan trabajar.
            </x-ui.alert>
        @endif

        @if ($usuarios->isEmpty())
            <x-ui.empty
                icono="users"
                titulo="No hay usuarios con ese rol"
                texto="Cree uno desde Usuarios y vuelva aquí para asignarle establecimientos."
            />
        @else
            <x-ui.table>
                <caption class="sr-only">Usuarios del rol {{ $nombreRol }} y sus establecimientos asignados</caption>
                <thead>
                    <tr>
                        <th scope="col">Usuario</th>
                        <th scope="col">Correo</th>
                        <th scope="col" class="text-center">Establecimientos</th>
                        <th scope="col">Asignados</th>
                        <th scope="col" class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($usuarios as $usuario)
                        <tr>
                            <td>
                                <span class="flex items-center gap-2.5">
                                    <x-ui.avatar :texto="$usuario->name" tamano="sm" />
                                    <span class="min-w-0">
                                        <span class="block font-medium">{{ $usuario->name }}</span>
                                        <span class="block text-xs text-muted-foreground">
                                            {{ $usuario->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </span>
                                </span>
                            </td>

                            <td class="text-muted-foreground">{{ $usuario->email }}</td>

                            <td class="text-center tabular">
                                @if ($usuario->establecimientos_count === 0)
                                    <x-ui.badge tono="warn">Sin asignar</x-ui.badge>
                                @else
                                    {{ $usuario->establecimientos_count }} de {{ $totalEstablecimientos }}
                                @endif
                            </td>

                            <td class="max-w-[24rem]">
                                @if ($usuario->establecimientos->isEmpty())
                                    <span class="text-xs text-muted-foreground">—</span>
                                @else
                                    <span class="flex flex-wrap gap-1">
                                        @foreach ($usuario->establecimientos->take(6) as $establecimiento)
                                            <x-ui.badge tono="idle" :punto="false">{{ $establecimiento->codigo }}</x-ui.badge>
                                        @endforeach

                                        @if ($usuario->establecimientos->count() > 6)
                                            <span class="text-xs text-muted-foreground">
                                                +{{ $usuario->establecimientos->count() - 6 }}
                                            </span>
                                        @endif
                                    </span>
                                @endif
                            </td>

                            <td class="text-end">
                                <a href="{{ route('admin.asignaciones.edit', $usuario) }}" class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="pencil" class="size-3.5" />
                                    Asignar
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif

    </div>
@endsection
