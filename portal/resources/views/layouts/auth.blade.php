<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Acceso') · {{ config('app.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230d7875'/%3E%3Cpath d='M8 16h4l2-5 3 10 2-5h5' stroke='white' stroke-width='2.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">

    @include('partials.tema')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground">

<a href="#acceso" class="skip-link">Saltar al contenido</a>

<div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-10">

    {{-- Conmutador de tema: la pantalla de acceso no tiene cabecera, así que el
         control vive aquí. --}}
    <div class="absolute end-4 top-4">
        <button
            type="button"
            data-theme-toggle
            aria-label="Cambiar de tema"
            class="flex size-9 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
        >
            <x-ui.icon name="sun" class="size-4 dark:hidden" />
            <x-ui.icon name="moon" class="hidden size-4 dark:block" />
        </button>
    </div>

    <main id="acceso" tabindex="-1" class="w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-primary">
                <x-ui.icon name="activity" class="size-6 text-primary-foreground" />
            </span>
            <h1 class="mt-4 text-xl font-bold tracking-tight">Portal REM</h1>
            <p class="mt-1 text-sm text-muted-foreground">Servicio de Salud Osorno</p>
        </div>

        @yield('contenido')

        <p class="mt-8 text-center text-xs text-muted-foreground">
            {{ config('app.name') }}
        </p>
    </main>
</div>

@include('partials.avisos')

@stack('scripts')

</body>
</html>
