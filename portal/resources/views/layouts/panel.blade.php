<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Panel') · {{ config('app.name') }}</title>

    <link rel="stylesheet" href="{{ asset('assets/adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/adminlte/css/adminlte.min.css') }}">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        @auth
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <span class="nav-link">
                        <i class="fas fa-user"></i>
                        {{ auth()->user()->name }}
                        <span class="badge badge-secondary">{{ auth()->user()->getRoleNames()->first() ?? 'sin rol' }}</span>
                    </span>
                </li>
                <li class="nav-item">
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-link nav-link">Salir</button>
                    </form>
                </li>
            </ul>
        @endauth
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ route('panel') }}" class="brand-link">
            <span class="brand-text font-weight-light">Portal REM</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column">
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
                            <a href="{{ route('admin.snapshots.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-database"></i>
                                <p>Catálogos</p>
                            </a>
                        </li>
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
                            <a href="{{ route('admin.plantillas.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-file-excel"></i>
                                <p>Plantillas</p>
                            </a>
                        </li>
                    @endcan

                    @can('usuarios.gestionar')
                        <li class="nav-item">
                            <a href="{{ route('admin.usuarios.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-users"></i>
                                <p>Usuarios</p>
                            </a>
                        </li>
                    @endcan

                    @can('auditoria.ver')
                        <li class="nav-header">CONTROL</li>
                        <li class="nav-item">
                            <a href="{{ route('admin.auditoria.index') }}" class="nav-link">
                                <i class="nav-icon fas fa-clipboard-list"></i>
                                <p>Auditoría</p>
                            </a>
                        </li>
                    @endcan
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper p-3">
        @yield('contenido')
    </div>
</div>

<script src="{{ asset('assets/adminlte/plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/adminlte/js/adminlte.min.js') }}"></script>

@stack('scripts')
</body>
</html>