@extends('layouts.panel')

@section('titulo', $titulo)

@section('contenido')
    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            :titulo="$titulo"
            :migas="['Panel' => route('panel'), $titulo => null]"
        >
            <x-slot:acciones>
                <a href="{{ route($ruta . '.create') }}" class="btn btn-primary">
                    <x-ui.icon name="plus" class="size-4" />
                    Nuevo
                </a>
            </x-slot:acciones>
        </x-ui.page-header>

        @if ($filas->isEmpty())
            <x-ui.empty
                icono="inbox"
                titulo="Todavía no hay registros"
                :texto="'Cree el primero con el botón «Nuevo».'"
            >
                <x-slot:accion>
                    <a href="{{ route($ruta . '.create') }}" class="btn btn-primary">
                        <x-ui.icon name="plus" class="size-4" />
                        Nuevo
                    </a>
                </x-slot:accion>
            </x-ui.empty>
        @else
            <x-ui.table>
                <caption class="sr-only">{{ $titulo }}</caption>
                <thead>
                    <tr>
                        @foreach ($columnas as $etiqueta => $campo)
                            <th scope="col">{{ $etiqueta }}</th>
                        @endforeach
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        <tr>
                            @foreach ($columnas as $campo)
                                @php $valor = data_get($fila, $campo); @endphp
                                <td @class(['tabular' => is_numeric($valor)])>
                                    @if ($valor instanceof \DateTimeInterface)
                                        {{ $valor->format('d-m-Y') }}
                                    @elseif (is_bool($valor))
                                        <x-ui.badge :tono="$valor ? 'ok' : 'idle'">{{ $valor ? 'Sí' : 'No' }}</x-ui.badge>
                                    @elseif ($valor === null || $valor === '')
                                        <span class="text-muted-foreground">—</span>
                                    @else
                                        {{ $valor }}
                                    @endif
                                </td>
                            @endforeach

                            <td class="text-end">
                                <a href="{{ route($ruta . '.edit', $fila) }}" class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="pencil" class="size-3.5" />
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <div>{{ $filas->links() }}</div>
        @endif

    </div>
@endsection
