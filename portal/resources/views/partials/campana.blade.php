@php
    $avisos = auth()->user()->notifications()->latest()->limit(10)->get();
    $sinLeer = auth()->user()->unreadNotifications()->count();
@endphp

<li class="nav-item dropdown">
    <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button" aria-label="Notificaciones ({{ $sinLeer }} sin leer)">
        <i class="far fa-bell"></i>
        @if ($sinLeer > 0)
            <span class="badge bg-danger navbar-badge">{{ $sinLeer }}</span>
        @endif
    </a>
    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
        <span class="dropdown-item dropdown-header">{{ $sinLeer }} aviso(s) sin leer</span>

        @forelse ($avisos as $aviso)
            <form method="POST" action="{{ route('notificaciones.leer', $aviso->id) }}">
                @csrf
                <button type="submit" class="dropdown-item {{ $aviso->read_at === null ? 'bg-light' : '' }}">
                    <strong>{{ $aviso->data['titulo'] ?? 'Aviso' }}</strong>
                    <span class="d-block small text-muted">{{ $aviso->data['mensaje'] ?? '' }}</span>
                    <span class="d-block small text-muted">
                        {{ $aviso->data['periodo'] ?? '' }}
                        · {{ $aviso->created_at?->diffForHumans() }}
                    </span>
                </button>
            </form>
        @empty
            <span class="dropdown-item text-muted">Sin avisos.</span>
        @endforelse

        @if ($avisos->isNotEmpty())
            <div class="dropdown-divider"></div>
            <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                @csrf
                <button type="submit" class="dropdown-item text-center">Marcar todas como leídas</button>
            </form>
        @endif
    </div>
</li>