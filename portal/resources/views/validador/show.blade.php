@extends('layouts.panel')

@section('titulo', 'Revisión del Validador')

@section('contenido')
    <div class="mx-auto max-w-5xl space-y-6">

        <x-ui.page-header
            :titulo="$ejecucion->nombre_original"
            :subtitulo="'Revisado el ' . $ejecucion->created_at?->format('d-m-Y H:i')
                . ' · reglas v' . $ejecucion->reglas_version
                . ' · ' . $ejecucion->duracion_ms . ' ms'"
            :migas="['Panel' => route('panel'), 'Validador' => route('validador.index'), 'Revisión' => null]"
        >
            <x-slot:acciones>
                <a href="{{ route('validador.index') }}" class="btn btn-ghost">
                    <x-ui.icon name="arrow-right" class="size-4 rotate-180" />
                    Volver
                </a>
            </x-slot:acciones>
        </x-ui.page-header>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat icono="list" rotulo="Hallazgos" :valor="$ejecucion->hallazgos" tono="info" />
            <x-ui.stat icono="ban" rotulo="Bloqueantes" :valor="$ejecucion->bloqueantes"
                       :tono="$ejecucion->bloqueantes > 0 ? 'danger' : 'ok'" />
            <x-ui.stat icono="triangle-exclamation-point" rotulo="Advertencias" :valor="$ejecucion->advertencias" tono="warn" />
        </div>

        @if ($ejecucion->resultado['error'] ?? null)
            <x-ui.alert tono="danger">
                <span class="font-medium">La revisión no se pudo completar.</span>
                {{ $ejecucion->resultado['error'] }}
            </x-ui.alert>
        @endif

        @if ($ejecucion->hallazgos === 0 && ! ($ejecucion->resultado['error'] ?? null))
            <x-ui.alert tono="ok">
                Ninguna regla encontró problemas en este archivo. Recuerde que esto no reemplaza la validación
                del portal ni la revisión del Equipo REM.
            </x-ui.alert>
        @elseif (count($ejecucion->listaHallazgos()) > 0)
            <x-ui.card class="p-0">
                <div class="overflow-x-auto">
                    <table class="tabla">
                        <caption class="sr-only">Hallazgos del Validador</caption>
                        <thead>
                            <tr>
                                <th scope="col">Regla</th>
                                <th scope="col">Severidad</th>
                                <th scope="col">Hoja</th>
                                <th scope="col">Celda</th>
                                <th scope="col">Mensaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ejecucion->listaHallazgos() as $hallazgo)
                                <tr>
                                    <td><code class="rounded bg-muted px-1 py-0.5 text-[11px]">{{ $hallazgo['regla'] }}</code></td>
                                    <td><x-ui.estado :valor="$hallazgo['severidad']" /></td>
                                    <td class="text-muted-foreground">{{ $hallazgo['hoja'] ?? '—' }}</td>
                                    <td class="text-muted-foreground">{{ $hallazgo['celda'] ?? '—' }}</td>
                                    <td>{{ $hallazgo['mensaje'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        @endif

        <x-ui.alert tono="info">
            Este resultado es una ayuda a la calidad de la estadística: <strong>no cambió el estado de ningún
            envío</strong> ni se guardó en el expediente del período.
        </x-ui.alert>

    </div>
@endsection
