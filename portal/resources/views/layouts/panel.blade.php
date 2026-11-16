<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Panel') · {{ config('app.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230d7875'/%3E%3Cpath d='M8 16h4l2-5 3 10 2-5h5' stroke='white' stroke-width='2.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">

    {{-- Resuelve el tema antes del primer paint. Debe ir antes del CSS. --}}
    @include('partials.tema')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground">

<a href="#main-content" class="skip-link">Saltar al contenido</a>

{{-- Capa oscura del panel móvil. Su visibilidad la resuelve app.css según
     `html.sidebar-open`; no lleva utilidad `hidden` porque esa utilidad gana
     sobre la capa de componentes y la dejaría siempre oculta. --}}
<div data-sidebar-overlay class="fixed inset-0 z-40 bg-black/50"></div>

{{-- ====================== Barra lateral ====================== --}}
<aside data-sidebar class="fixed inset-y-0 start-0 z-50 flex flex-col border-e border-sidebar-border bg-sidebar">

    <div class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-sidebar-border px-4">
        <a href="{{ route('panel') }}" data-brand-link class="flex items-center gap-2.5">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary">
                <x-ui.icon name="activity" class="size-4 text-sidebar-primary-foreground" />
            </span>
            <span data-brand-text class="flex flex-col leading-none">
                <span class="text-sm font-bold tracking-tight text-sidebar-foreground">Portal REM</span>
                <span class="text-[10px] font-medium uppercase tracking-widest text-sidebar-foreground/60">Estadística</span>
            </span>
        </a>

        <button
            type="button"
            data-sidebar-close
            class="flex size-8 items-center justify-center rounded-md text-sidebar-foreground/60 transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground lg:hidden"
            aria-label="Cerrar el menú"
        >
            <x-ui.icon name="x" class="size-4" />
        </button>
    </div>

    {{-- Colapso a riel, solo en escritorio. --}}
    <button
        type="button"
        data-sidebar-collapse
        aria-expanded="true"
        aria-label="Colapsar la barra lateral"
        class="absolute -end-3 top-[4.5rem] z-50 hidden size-6 items-center justify-center rounded-full border border-sidebar-border bg-sidebar text-sidebar-foreground/60 shadow-sm transition-colors hover:text-sidebar-foreground lg:flex"
    >
        <x-ui.icon name="chevrons-left" class="size-3.5" />
    </button>

    <nav data-nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4" aria-label="Menú principal">

        <div>
            <p data-nav-group-label class="px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-sidebar-foreground/50">General</p>
            <ul class="space-y-0.5">
                <li>
                    <x-ui.nav-link :href="route('panel')" icono="layout-dashboard" :activo="request()->routeIs('panel')">Panel</x-ui.nav-link>
                </li>
            </ul>
        </div>

        @canany(['envios.ver.propio', 'envios.ver.ss'])
            <div>
                <p data-nav-group-label class="px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-sidebar-foreground/50">Operación</p>
                <ul class="space-y-0.5">
                    <li>
                        <x-ui.nav-link :href="route('envios.index')" icono="file-up" :activo="request()->routeIs('envios.*')">Envíos</x-ui.nav-link>
                    </li>

                    @can('envios.ver.ss')
                        <li>
                            <x-ui.nav-link :href="route('revision.index')" icono="clipboard-check" :activo="request()->routeIs('revision.*')">Revisión</x-ui.nav-link>
                        </li>
                    @endcan

                    <li>
                        <x-ui.nav-link :href="route('completitud.index')" icono="chart-column" :activo="request()->routeIs('completitud.*')">Completitud</x-ui.nav-link>
                    </li>
                </ul>
            </div>
        @endcanany

        @can('catalogos.gestionar')
            <div>
                <p data-nav-group-label class="px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-sidebar-foreground/50">Administración</p>
                <ul class="space-y-0.5">
                    <li>
                        <x-ui.nav-link :href="route('admin.servicios.index')" icono="hospital" :activo="request()->routeIs('admin.servicios.*')">Servicios</x-ui.nav-link>
                    </li>
                    <li>
                        <x-ui.nav-link :href="route('admin.establecimientos.index')" icono="building" :activo="request()->routeIs('admin.establecimientos.*')">Establecimientos</x-ui.nav-link>
                    </li>
                    <li>
                        <x-ui.nav-link :href="route('admin.series.index')" icono="list-ordered" :activo="request()->routeIs('admin.series.*')">Series</x-ui.nav-link>
                    </li>
                    <li>
                        <x-ui.nav-link :href="route('admin.matriz.index')" icono="table" :activo="request()->routeIs('admin.matriz.*')">Matriz de series</x-ui.nav-link>
                    </li>
                    <li>
                        <x-ui.nav-link :href="route('admin.periodos.index')" icono="calendar" :activo="request()->routeIs('admin.periodos.*')">Períodos</x-ui.nav-link>
                    </li>
                    <li>
                        <x-ui.nav-link :href="route('admin.calendario.index')" icono="calendar-check" :activo="request()->routeIs('admin.calendario.*')">Calendario de plazos</x-ui.nav-link>
                    </li>
                    <li>
                        <x-ui.nav-link :href="route('admin.feriados.index')" icono="calendar-days" :activo="request()->routeIs('admin.feriados.*')">Feriados</x-ui.nav-link>
                    </li>
                    <li>
                        <x-ui.nav-link :href="route('admin.plantillas.index')" icono="file-spreadsheet" :activo="request()->routeIs('admin.plantillas.*')">Plantillas</x-ui.nav-link>
                    </li>
                </ul>
            </div>
        @endcan

        @can('usuarios.gestionar')
            <div>
                <p data-nav-group-label class="px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-sidebar-foreground/50">Usuarios</p>
                <ul class="space-y-0.5">
                    <li>
                        <x-ui.nav-link :href="route('admin.usuarios.index')" icono="users" :activo="request()->routeIs('admin.usuarios.*')">Usuarios</x-ui.nav-link>
                    </li>
                </ul>
            </div>
        @endcan
    </nav>
</aside>

{{-- ====================== Lienzo ====================== --}}
<div data-shell class="flex min-h-screen flex-col">

    <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-border bg-background/85 px-4 backdrop-blur-xl sm:px-6">

        <button
            type="button"
            data-sidebar-toggle
            aria-expanded="false"
            aria-label="Abrir el menú"
            class="flex size-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground lg:hidden"
        >
            <x-ui.icon name="menu" class="size-4" />
        </button>

        <div class="ms-auto flex items-center gap-1">

            {{-- Tema: alterna entre claro y oscuro. La preferencia "sistema" se
                 elige en Mi cuenta. --}}
            <button
                type="button"
                data-theme-toggle
                aria-label="Cambiar de tema"
                class="flex size-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
            >
                <x-ui.icon name="sun" class="size-4 dark:hidden" />
                <x-ui.icon name="moon" class="hidden size-4 dark:block" />
            </button>

            @include('partials.campana')

            {{-- Cuenta --}}
            <details class="group relative ms-1">
                <summary
                    class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-1.5 py-1.5 transition-colors hover:bg-accent [&::-webkit-details-marker]:hidden"
                    aria-label="Mi cuenta"
                >
                    <x-ui.avatar :texto="auth()->user()->name" tamano="md" />
                    <span data-user-meta class="hidden min-w-0 sm:block">
                        <span class="block max-w-[10rem] truncate text-sm font-medium leading-tight">{{ auth()->user()->name }}</span>
                        <span class="block text-[11px] leading-tight text-muted-foreground">{{ auth()->user()->getRoleNames()->first() ?? 'sin rol' }}</span>
                    </span>
                    <x-ui.icon name="chevron-down" class="size-3.5 shrink-0 text-muted-foreground" />
                </summary>

                <div class="absolute end-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-border bg-popover p-1 shadow-lg">
                    <div class="border-b border-border px-3 py-2">
                        <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ auth()->user()->email }}</p>
                    </div>

                    <a href="{{ route('perfil.edit') }}"
                       class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-accent">
                        <x-ui.icon name="user" class="size-4 text-muted-foreground" />
                        Mi cuenta
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-accent">
                            <x-ui.icon name="log-out" class="size-4 text-muted-foreground" />
                            Salir
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </header>

    <main id="main-content" tabindex="-1" class="flex-1 px-4 py-6 sm:px-6">
        @yield('contenido')
    </main>

    <footer class="border-t border-border px-4 py-4 text-xs text-muted-foreground sm:px-6">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ config('app.name') }} · Servicio de Salud Osorno</span>
            <span>Portal de envío REM</span>
        </div>
    </footer>
</div>

@include('partials.avisos')

@stack('scripts')

</body>
</html>
