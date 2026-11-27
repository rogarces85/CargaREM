@extends('layouts.panel')

@section('titulo', 'Validador')

@section('contenido')
    <div class="mx-auto max-w-5xl space-y-6">

        <x-ui.page-header
            titulo="Validador de calidad"
            subtitulo="Revisa un Excel REM con el catálogo de reglas de la Unidad. No modifica ningún envío."
            :migas="['Panel' => route('panel'), 'Validador' => null]"
        >
            @can('validador.estadisticas')
                <x-slot:acciones>
                    <a href="{{ route('validador.estadisticas') }}" class="btn btn-secondary">
                        <x-ui.icon name="chart-column" class="size-4" />
                        Registro de uso
                    </a>
                </x-slot:acciones>
            @endcan
        </x-ui.page-header>

        @if ($errorCatalogo)
            <x-ui.alert tono="danger">
                <span class="font-medium">{{ $errorCatalogo }}</span>
                Mientras siga así, el Validador no puede ejecutarse.
            </x-ui.alert>
        @else
            <x-ui.alert tono="info">
                Esta revisión es una <strong>ayuda</strong>: no cambia el estado de ningún envío ni reemplaza la
                validación del portal. Lo que encuentre aquí lo corrige el establecimiento en su archivo.
            </x-ui.alert>

            <x-ui.card class="p-5">
                <h2 class="text-sm font-semibold">Revisar un archivo</h2>

                <p class="mt-1 text-xs text-muted-foreground">
                    Catálogo: <strong>{{ $catalogo->version }}</strong>,
                    {{ $catalogo->total() }} regla(s) en {{ count($catalogo->hojas()) }} hoja(s).
                    Nómina: {{ $nomina?->total() ?? 0 }} establecimientos.
                    Máximo {{ config('rem.archivos.max_kb') }} KB.
                </p>

                {{-- El archivo se reconoce por su nombre: [código 6][serie][mes].
                     Decirlo aquí evita el rechazo que más confunde. --}}
                <p class="mt-2 rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                    El nombre del archivo tiene que seguir el formato DEIS:
                    <code class="rounded bg-card px-1 py-0.5">123100A02.xlsx</code>
                    (código de 6 dígitos, serie y mes). La serie P se informa en 06 y 12.
                </p>

                <form method="POST" action="{{ route('validador.store') }}" enctype="multipart/form-data"
                      class="mt-4 grid gap-4 sm:grid-cols-[2fr_auto] sm:items-end">
                    @csrf

                    <x-ui.field nombre="archivo" etiqueta="Archivo Excel" :error="$errors->first('archivo')" requerido>
                        <input type="file" name="archivo" id="archivo" accept=".xls,.xlsx,.xlsm" required
                               class="w-full rounded-lg border border-input bg-card text-sm file:me-3 file:rounded-s-lg file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium">
                    </x-ui.field>

                    <button type="submit" class="btn btn-primary h-10">
                        <x-ui.icon name="shield-check" class="size-4" />
                        Revisar
                    </button>
                </form>
            </x-ui.card>
        @endif

        <div class="space-y-2">
            <h2 class="text-sm font-semibold">Mis revisiones</h2>

            @if ($ejecuciones->isEmpty())
                <x-ui.empty
                    icono="shield-check"
                    titulo="Todavía no ha revisado ningún archivo"
                    texto="Elija un Excel arriba. Cada revisión queda registrada con su fecha y su resultado."
                />
            @else
                <x-ui.table>
                    <caption class="sr-only">Revisiones del Validador hechas por el usuario</caption>
                    <thead>
                        <tr>
                            <th scope="col">Archivo</th>
                            <th scope="col">Fecha</th>
                            <th scope="col" class="text-center">Hallazgos</th>
                            <th scope="col">Resultado</th>
                            <th scope="col" class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ejecuciones as $ejecucion)
                            <tr>
                                <td class="max-w-[18rem] truncate" title="{{ $ejecucion->nombre_original }}">
                                    {{ $ejecucion->nombre_original }}
                                </td>
                                <td class="whitespace-nowrap text-xs text-muted-foreground">
                                    {{ $ejecucion->created_at?->format('d-m-Y H:i') }}
                                </td>
                                <td class="text-center tabular">{{ $ejecucion->hallazgos }}</td>
                                <td>
                                    <span class="flex flex-wrap items-center gap-1">
                                        <x-ui.estado :valor="$ejecucion->estado" />

                                        @if ($ejecucion->bloqueantes > 0)
                                            <x-ui.badge tono="danger">{{ $ejecucion->bloqueantes }} error(es)</x-ui.badge>
                                        @endif

                                        @if ($ejecucion->advertencias > 0)
                                            <x-ui.badge tono="warn">{{ $ejecucion->advertencias }} a revisar</x-ui.badge>
                                        @endif
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('validador.show', $ejecucion) }}" class="btn btn-secondary btn-sm">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>

                <div>{{ $ejecuciones->links() }}</div>
            @endif
        </div>

    </div>
@endsection
