@extends('layouts.panel')

@section('titulo', 'Notificaciones')

@section('contenido')
    <div class="mx-auto max-w-4xl space-y-6">

        <x-ui.page-header
            titulo="Notificaciones"
            :subtitulo="$sinLeer . ' sin leer de su historial completo.'"
            :migas="['Panel' => route('panel'), 'Notificaciones' => null]"
        >
            <x-slot:acciones>
                @if ($sinLeer > 0)
                    <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary">
                            <x-ui.icon name="check" class="size-4" />
                            Marcar todas como leídas
                        </button>
                    </form>
                @endif
            </x-slot:acciones>
        </x-ui.page-header>

        <form method="GET" action="{{ route('notificaciones.index') }}" class="flex flex-wrap items-end gap-3">
            <x-ui.field nombre="tipo" etiqueta="Tipo" class="w-60">
                <select name="tipo" id="tipo" class="select">
                    <option value="">Todos</option>
                    @foreach ($tipos as $valor => $texto)
                        <option value="{{ $valor }}" @selected($tipo === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <button type="submit" class="btn btn-secondary h-10">
                <x-ui.icon name="search" class="size-4" />
                Filtrar
            </button>

            @if ($tipo !== '')
                <a href="{{ route('notificaciones.index') }}" class="btn btn-ghost h-10">Limpiar</a>
            @endif
        </form>

        @if ($avisos->isEmpty())
            <x-ui.empty
                icono="bell"
                titulo="No hay avisos con ese filtro"
                texto="Los avisos aparecen cuando alguien sube un archivo, se resuelve una apertura o cambia su reparto."
            />
        @else
            <x-ui.card class="divide-y divide-border">
                @foreach ($avisos as $aviso)
                    @php
                        $leido = $aviso->read_at !== null;
                        $tono = $aviso->data['tono'] ?? 'info';
                    @endphp

                    <form method="POST" action="{{ route('notificaciones.leer', $aviso->id) }}">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-start gap-3 p-4 text-left transition-colors hover:bg-accent/50 {{ $leido ? '' : 'bg-primary/[0.04]' }}">
                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg
                                @class([
                                    'bg-status-ok/10 text-status-ok-fg' => $tono === 'ok',
                                    'bg-status-warn/10 text-status-warn-fg' => $tono === 'warn',
                                    'bg-status-danger/10 text-status-danger-fg' => $tono === 'danger',
                                    'bg-status-info/10 text-status-info-fg' => ! in_array($tono, ['ok', 'warn', 'danger'], true),
                                ])">
                                <x-ui.icon name="bell" class="size-4" />
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium">{{ $aviso->data['titulo'] ?? 'Aviso' }}</span>

                                    @if (($aviso->data['alcance'] ?? null) === 'completo')
                                        <x-ui.badge tono="ok" :punto="false">Envío completo</x-ui.badge>
                                    @elseif (($aviso->data['alcance'] ?? null) === 'serie')
                                        <x-ui.badge tono="info" :punto="false">Serie suelta</x-ui.badge>
                                    @endif

                                    @unless ($leido)
                                        <span class="size-1.5 rounded-full bg-primary" aria-label="Sin leer"></span>
                                    @endunless
                                </span>

                                <span class="mt-0.5 block text-sm text-muted-foreground">
                                    {{ $aviso->data['mensaje'] ?? '' }}
                                </span>

                                <span class="mt-1 block text-[11px] text-muted-foreground">
                                    {{ $aviso->data['periodo'] ?? '' }}
                                    @if (! empty($aviso->data['establecimiento']))
                                        · {{ $aviso->data['establecimiento'] }}
                                    @endif
                                    · {{ $aviso->created_at?->diffForHumans() }}
                                </span>
                            </span>
                        </button>
                    </form>
                @endforeach
            </x-ui.card>

            <div>{{ $avisos->links() }}</div>
        @endif

    </div>
@endsection
