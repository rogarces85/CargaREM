<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Panel') · {{ config('app.name') }}</title>

    <link rel="stylesheet" href="{{ asset('assets/adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/adminlte/css/adminlte.min.css') }}">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

    {{-- Header --}}
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Menú">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>
            </ul>

            @auth
                <ul class="navbar-nav ms-auto">
                    @include('partials.campana')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('perfil.edit') }}" title="Mi cuenta">
                            <i class="fas fa-user"></i>
                            {{ auth()->user()->name }}
                            <span class="badge bg-secondary">{{ auth()->user()->getRoleNames()->first() ?? 'sin rol' }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-link nav-link">Salir</button>
                        </form>
                    </li>
                </ul>
            @endauth
        </div>
    </nav>

    {{-- Sidebar --}}
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="{{ route('panel') }}" class="brand-link">
                <span class="brand-text fw-light">Portal REM</span>
            </a>
        </div>

        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="{{ route('panel') }}" class="nav-link active">
                            <i class="nav-icon fas fa-home"></i>
                            <p>Panel</p>
                        </a>
                    </li>

                    @canany(['envios.ver.propio', 'envios.ver.ss'])
                        <li class="nav-header">OPERACIÓN</li>
                        <li class="nav-item">
                            <a href="{{ route('envios.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-file-upload"></i>
                                <p>Envíos</p>
                            </a>
                        </li>
                    @endcanany

                    @can('envios.ver.ss')
                        <li class="nav-item">
                            <a href="{{ route('revision.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-clipboard-check"></i>
                                <p>Revisión</p>
                            </a>
                        </li>
                    @endcan

                    @canany(['envios.ver.propio', 'envios.ver.ss'])
                        <li class="nav-item">
                            <a href="{{ route('completitud.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-th"></i>
                                <p>Completitud</p>
                            </a>
                        </li>
                    @endcanany

                    @can('catalogos.gestionar')
                        <li class="nav-header">ADMINISTRACIÓN</li>
                        <li class="nav-item">
                            <a href="{{ route('admin.servicios.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-hospital"></i>
                                <p>Servicios</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.establecimientos.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-building"></i>
                                <p>Establecimientos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.series.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-list-ol"></i>
                                <p>Series</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.matriz.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-table"></i>
                                <p>Matriz de series</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.periodos.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-calendar-alt"></i>
                                <p>Períodos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.calendario.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-calendar-check"></i>
                                <p>Calendario de plazos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.feriados.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-calendar-day"></i>
                                <p>Feriados</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.plantillas.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-file-excel"></i>
                                <p>Plantillas</p>
                            </a>
                        </li>
                    @endcan

                    @can('usuarios.gestionar')
                        <li class="nav-header">USUARIOS</li>
                        <li class="nav-item">
                            <a href="{{ route('admin.usuarios.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-users"></i>
                                <p>Usuarios</p>
                            </a>
                        </li>
                    @endcan
                </ul>
            </nav>
        </div>
    </aside>

    {{-- Main --}}
    <main class="app-main">
        <div class="app-content">
            <div class="container-fluid py-3">
                @yield('contenido')
            </div>
        </div>
    </main>

    <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">Portal REM</div>
        <strong>{{ config('app.name') }}</strong>
    </footer>
</div>

<script src="{{ asset('assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/adminlte/js/adminlte.min.js') }}"></script>

@stack('scripts')
</body>
</html>
