{{--
    Página de error del portal.

    Recibe `codigo`, `titulo` y `mensaje`. No extiende el layout del panel: un
    error puede ocurrir antes de tener sesión, y el layout asume un usuario
    autenticado (la campana consulta notificaciones).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · {{ config('app.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230d7875'/%3E%3Cpath d='M8 16h4l2-5 3 10 2-5h5' stroke='white' stroke-width='2.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">

    @include('partials.tema')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground">

<a href="#contenido" class="skip-link">Saltar al contenido</a>

<main id="contenido" tabindex="-1" class="flex min-h-screen flex-col items-center justify-center px-4 text-center">
    <span class="flex size-12 items-center justify-center rounded-xl bg-muted">
        <x-ui.icon :name="$icono" class="size-6 text-muted-foreground" />
    </span>

    <p class="mt-6 text-6xl font-bold tabular tracking-tight text-muted-foreground/40">{{ $codigo }}</p>

    <h1 class="mt-2 text-xl font-semibold">{{ $titulo }}</h1>
    <p class="mt-2 max-w-md text-sm text-muted-foreground">{{ $mensaje }}</p>

    <a href="{{ url('/') }}" class="btn btn-primary mt-6">
        <x-ui.icon name="house" class="size-4" />
        Volver al panel
    </a>
</main>

</body>
</html>
