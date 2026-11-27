@extends('layouts.panel')

@section('titulo', 'Aperturas de plazo')

@section('contenido')
    @php
        $estados = \App\Models\Apertura::ESTADOS;
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            titulo="Aperturas de fuera de plazo"
            :subtitulo="$esAdmin
                ? 'Solicitudes del equipo y ventanas que usted abre. Solo el administrador las resuelve.'
                : 'Pida abrir un período cerrado. La decisión es del administrador.'"
            :migas="['Panel' => route('panel'), 'Aperturas' => null]"
        />

        @error('apertura')
            <x-ui.alert tono="danger">{{ $message }}</x-ui.alert>
        @enderror

        {{-- Alta: el administrador abre por su cuenta; el encargado pide. --}}
        <x-ui.card class="p-5">
            <h2 class="text-sm font-semibold">{{ $esAdmin ? 'Abrir un plazo' : 'Pedir una apertura' }}</h2>
            <p class="mt-1 text-xs text-muted-foreground">
                @if ($esAdmin)
                    La ventana habilita a ese establecimiento (o a todos) a cargar el período mientras esté vigente.
                @else
                    Solo se puede cargar fuera de plazo con una apertura aprobada. El motivo es obligatorio.
                @endif
            </p>

            <form method="POST"
                  action="{{ $esAdmin ? route('aperturas.otorgar') : route('aperturas.store') }}"
                  class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                @csrf

                <x-ui.field nombre="establecimiento_id" etiqueta="Establecimiento">
                    <select name="establecimiento_id" id="establecimiento_id" class="select">
                        @if ($esAdmin)
                            <option value="">Todos los establecimientos</option>
                        @endif

                        @foreach ($establecimientos as $establecimiento)
                            <option value="{{ $establecimiento->id }}" @selected(old('establecimiento_id') == $establecimiento->id)>
                                {{ $establecimiento->codigo }} — {{ $establecimiento->nombre }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field nombre="anio" etiqueta="Año" :error="$errors->first('anio')" requerido>
                    <input type="number" name="anio" id="anio" min="2000" max="2100"
                           value="{{ old('anio', now()->year) }}" class="input tabular" required>
                </x-ui.field>

                <x-ui.field nombre="mes" etiqueta="Mes" :error="$errors->first('mes')" requerido>
                    <select name="mes" id="mes" class="select" required>
                        @foreach (range(1, 12) as $numero)
                            <option value="{{ $numero }}" @selected(old('mes', now()->month) == $numero)>
                                {{ sprintf('%02d', $numero) }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                @if ($esAdmin)
                    <x-ui.field nombre="vigente_hasta" etiqueta="Vigente hasta"
                                :error="$errors->first('vigente_hasta')"
                                ayuda="Vacío = hasta que la anule.">
                        <input type="date" name="vigente_hasta" id="vigente_hasta"
                               value="{{ old('vigente_hasta') }}" class="input">
                    </x-ui.field>
                @endif

                <x-ui.field nombre="motivo" etiqueta="Motivo" :error="$errors->first('motivo')" requerido
                            ayuda="Mínimo 15 caracteres. Queda en la auditoría." class="lg:col-span-2">
                    <textarea name="motivo" id="motivo" rows="2" maxlength="500" required class="textarea">{{ old('motivo') }}</textarea>
                </x-ui.field>

                <button type="submit" class="btn btn-primary h-10">
                    <x-ui.icon :name="$esAdmin ? 'calendar-plus' : 'send'" class="size-4" />
                    {{ $esAdmin ? 'Abrir plazo' : 'Enviar solicitud' }}
                </button>
            </form>
        </x-ui.card>

        {{-- Filtro --}}
        <form method="GET" action="{{ route('aperturas.index') }}" class="flex flex-wrap items-end gap-3">
            <x-ui.field nombre="estado" etiqueta="Estado" class="w-56">
                <select name="estado" id="estado" class="select">
                    <option value="">Todas</option>
                    @foreach ($estados as $valor => $texto)
                        <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <button type="submit" class="btn btn-secondary h-10">
                <x-ui.icon name="search" class="size-4" />
                Filtrar
            </button>
        </form>

        @if ($aperturas->isEmpty())
            <x-ui.empty
                icono="calendar"
                titulo="No hay aperturas con ese filtro"
                texto="Las solicitudes aparecen aquí en cuanto se envían."
            />
        @else
            <x-ui.table>
                <caption class="sr-only">Aperturas de plazo solicitadas y otorgadas</caption>
                <thead>
                    <tr>
                        <th scope="col">Período</th>
                        <th scope="col">Establecimiento</th>
                        <th scope="col">Motivo</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Vigencia</th>
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($aperturas as $apertura)
                        <tr>
                            <td class="tabular font-medium">{{ $apertura->periodoLegible() }}</td>

                            <td>
                                @if ($apertura->esParaTodos())
                                    <x-ui.badge tono="info">Todos</x-ui.badge>
                                @else
                                    <span class="block font-medium">{{ $apertura->establecimiento?->codigo }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ $apertura->establecimiento?->nombre }}</span>
                                @endif
                            </td>

                            <td class="max-w-[24rem]">
                                <span class="block text-sm">{{ $apertura->motivo }}</span>
                                <span class="mt-0.5 block text-xs text-muted-foreground">
                                    Pidió {{ $apertura->solicitante?->name ?? '—' }}
                                    · {{ $apertura->created_at?->format('d-m-Y H:i') }}
                                </span>
                            </td>

                            <td>
                                <span class="flex flex-wrap items-center gap-1">
                                    <x-ui.estado :valor="$apertura->estado" />
                                    @if ($apertura->origen === \App\Models\Apertura::ORIGEN_ADMIN)
                                        <x-ui.badge tono="idle" :punto="false">Abierta por el administrador</x-ui.badge>
                                    @endif
                                </span>

                                @if ($apertura->motivo_resolucion)
                                    <span class="mt-1 block text-xs text-muted-foreground">
                                        Resolución: {{ $apertura->motivo_resolucion }}
                                    </span>
                                @endif
                            </td>

                            <td class="text-xs text-muted-foreground">
                                {{ $apertura->vigente_hasta?->format('d-m-Y') ?? 'Sin fecha de término' }}
                            </td>

                            <td class="text-end">
                                @if ($esAdmin && $apertura->estado === \App\Models\Apertura::PENDIENTE)
                                    <details class="group relative inline-block text-start">
                                        <summary class="btn btn-secondary btn-sm cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                                            Resolver
                                        </summary>

                                        <div class="absolute end-0 z-40 mt-2 w-80 space-y-3 rounded-xl border border-border bg-popover p-4 shadow-lg">
                                            <form method="POST" action="{{ route('aperturas.resolver', $apertura) }}" class="space-y-2">
                                                @csrf
                                                <input type="hidden" name="decision" value="aprobar">

                                                <x-ui.field nombre="motivo-aprobar-{{ $apertura->id }}" etiqueta="Motivo de la aprobación">
                                                    <textarea name="motivo" id="motivo-aprobar-{{ $apertura->id }}" rows="2" maxlength="500" required class="textarea"></textarea>
                                                </x-ui.field>

                                                <x-ui.field nombre="vigente-{{ $apertura->id }}" etiqueta="Vigente hasta">
                                                    <input type="date" name="vigente_hasta" id="vigente-{{ $apertura->id }}" class="input">
                                                </x-ui.field>

                                                <button type="submit" class="btn btn-primary btn-sm w-full">
                                                    <x-ui.icon name="circle-check" class="size-3.5" />
                                                    Aprobar
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('aperturas.resolver', $apertura) }}" class="space-y-2 border-t border-border pt-3">
                                                @csrf
                                                <input type="hidden" name="decision" value="rechazar">

                                                <x-ui.field nombre="motivo-rechazar-{{ $apertura->id }}" etiqueta="Motivo del rechazo">
                                                    <textarea name="motivo" id="motivo-rechazar-{{ $apertura->id }}" rows="2" maxlength="500" required class="textarea"></textarea>
                                                </x-ui.field>

                                                <button type="submit" class="btn btn-ghost btn-sm w-full">
                                                    <x-ui.icon name="x" class="size-3.5" />
                                                    Rechazar
                                                </button>
                                            </form>
                                        </div>
                                    </details>
                                @endif

                                @if ($esAdmin && $apertura->estado === \App\Models\Apertura::APROBADA)
                                    <form method="POST" action="{{ route('aperturas.anular', $apertura) }}" class="mt-1 inline-flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="motivo" required minlength="10" maxlength="500"
                                               placeholder="Motivo de la anulación" class="input h-8 w-40 text-xs">
                                        <button type="submit" class="btn btn-ghost btn-sm">Anular</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <div>{{ $aperturas->links() }}</div>
        @endif

    </div>
@endsection
