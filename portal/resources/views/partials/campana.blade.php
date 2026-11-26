@php
    $avisos = auth()->user()->notifications()->latest()->limit(10)->get();
    $sinLeer = auth()->user()->unreadNotifications()->count();
@endphp

{{--
    Campana de avisos.

    Se resuelve con <details>: abre y cierra sin JavaScript, y el navegador ya
    anuncia el estado expandido a quien usa lector de pantalla. El costo es que
    no se cierra al hacer clic fuera; con diez avisos y un uso de paso, no vale
    la pena agregar JavaScript para eso.
--}}
<div class="relative">
    <details class="group">
        <summary
            class="relative flex size-9 cursor-pointer list-none items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground [&::-webkit-details-marker]:hidden"
            aria-label="Notificaciones ({{ $sinLeer }} sin leer)"
        >
            <x-ui.icon name="bell" class="size-4" />

            @if ($sinLeer > 0)
                <span class="absolute end-1 top-1 flex size-4 items-center justify-center rounded-full bg-status-danger text-[9px] font-bold text-destructive-foreground tabular">
                    {{ $sinLeer > 9 ? '9+' : $sinLeer }}
                </span>
            @endif
        </summary>

        <div class="absolute end-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-border bg-popover shadow-lg">
            <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
                <p class="text-xs font-medium text-muted-foreground">
                    {{ $sinLeer }} aviso(s) sin leer
                </p>

                <a href="{{ route('notificaciones.index') }}" class="text-xs font-medium text-primary hover:underline">
                    Ver todos
                </a>
            </div>

            <div class="max-h-80 overflow-y-auto">
                @forelse ($avisos as $aviso)
                    <form method="POST" action="{{ route('notificaciones.leer', $aviso->id) }}">
                        @csrf
                        <button
                            type="submit"
                            class="block w-full px-4 py-2.5 text-left transition-colors hover:bg-accent/60 {{ $aviso->read_at === null ? 'bg-primary/[0.05]' : '' }}"
                        >
                            <span class="flex items-start gap-2">
                                @if ($aviso->read_at === null)
                                    <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                                @else
                                    <span class="mt-1.5 size-1.5 shrink-0" aria-hidden="true"></span>
                                @endif

                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">{{ $aviso->data['titulo'] ?? 'Aviso' }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ $aviso->data['mensaje'] ?? '' }}</span>
                                    <span class="block text-[11px] text-muted-foreground">
                                        {{ $aviso->data['periodo'] ?? '' }}
                                        · {{ $aviso->created_at?->diffForHumans() }}
                                    </span>
                                </span>
                            </span>
                        </button>
                    </form>
                @empty
                    <p class="px-4 py-6 text-center text-xs text-muted-foreground">Sin avisos.</p>
                @endforelse
            </div>

            @if ($avisos->isNotEmpty())
                <form method="POST" action="{{ route('notificaciones.leer-todas') }}" class="border-t border-border">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2.5 text-center text-xs font-medium transition-colors hover:bg-accent">
                        Marcar todas como leídas
                    </button>
                </form>
            @endif
        </div>
    </details>
</div>
