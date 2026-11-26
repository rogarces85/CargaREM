@extends('layouts.panel')

@section('titulo', 'Uso del Validador')

@section('contenido')
    <div class="mx-auto max-w-5xl space-y-6">

        <x-ui.page-header
            titulo="Uso del Validador"
            :subtitulo="'Revisiones registradas en ' . $anio . '. Total: ' . $total . '.'"
            :migas="['Panel' => route('panel'), 'Validador' => route('validador.index'), 'Registro de uso' => null]"
        >
            <x-slot:acciones>
                <form method="GET" action="{{ route('validador.estadisticas') }}" class="flex items-end gap-2">
                    <input type="number" name="anio" value="{{ $anio }}" min="2000" max="2100"
                           class="input h-10 w-28 tabular" aria-label="Año">
                    <button type="submit" class="btn btn-secondary h-10">Ver</button>
                </form>
            </x-slot:acciones>
        </x-ui.page-header>

        <x-ui.alert tono="info">
            El registro sirve para saber si la herramienta se usa y con qué resultado. No hay cuota ni bloqueo:
            un módulo de ayuda que castiga por usarlo se deja de usar.
        </x-ui.alert>

        <x-ui.card class="p-5">
            <h2 class="text-sm font-semibold">Revisiones por mes</h2>

            @php
                $maximo = max(1, max($porMes));
            @endphp

            <ul class="mt-4 space-y-2">
                @foreach ($porMes as $mes => $cantidad)
                    <li class="flex items-center gap-3">
                        <span class="w-24 shrink-0 text-xs text-muted-foreground">
                            {{ ucfirst(\Carbon\Carbon::create(null, $mes, 1)->translatedFormat('F')) }}
                        </span>

                        <span class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                            <span class="block h-full rounded-full bg-primary"
                                  style="width: {{ round($cantidad * 100 / $maximo, 1) }}%"></span>
                        </span>

                        <span class="w-10 shrink-0 text-end text-xs tabular">{{ $cantidad }}</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        @if ($porUsuario->isEmpty())
            <x-ui.empty
                icono="shield-check"
                titulo="Nadie usó el Validador en {{ $anio }}"
                texto="El registro se llena solo: cada revisión de un archivo deja una fila."
            />
        @else
            <x-ui.table>
                <caption class="sr-only">Uso del Validador por usuario</caption>
                <thead>
                    <tr>
                        <th scope="col">Usuario</th>
                        <th scope="col" class="text-center">Revisiones</th>
                        <th scope="col" class="text-center">Hallazgos</th>
                        <th scope="col" class="text-center">Bloqueantes</th>
                        <th scope="col">Última</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($porUsuario as $fila)
                        <tr>
                            <td>
                                <span class="block font-medium">{{ $fila->usuario?->name ?? 'Usuario eliminado' }}</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ \App\Support\RolUi::etiqueta($fila->usuario?->getRoleNames()->first()) }}
                                </span>
                            </td>
                            <td class="text-center tabular">{{ $fila->ejecuciones }}</td>
                            <td class="text-center tabular">{{ $fila->hallazgos }}</td>
                            <td class="text-center tabular">{{ $fila->bloqueantes }}</td>
                            <td class="whitespace-nowrap text-xs text-muted-foreground">
                                {{ \Carbon\Carbon::parse($fila->ultima)->format('d-m-Y H:i') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif

    </div>
@endsection
