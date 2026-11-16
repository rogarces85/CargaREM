@props([
    'titulo',
    'subtitulo' => null,
    'migas' => null,
])

{{--
    Cabecera de página: migas, título y acciones.

    `migas` es un arreglo `['Etiqueta' => url|null]`; el último elemento de la
    página actual va sin url y se marca con `aria-current="page"`.
--}}
<div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($migas)
            <nav aria-label="Ubicación" class="mb-1 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                @foreach ($migas as $etiqueta => $url)
                    @if ($url)
                        <a href="{{ $url }}" class="transition-colors hover:text-foreground">{{ $etiqueta }}</a>
                        <x-ui.icon name="chevron-right" class="size-3.5 shrink-0" />
                    @else
                        <span class="text-foreground" aria-current="page">{{ $etiqueta }}</span>
                    @endif
                @endforeach
            </nav>
        @endif

        <h1 class="text-2xl font-bold tracking-tight">{{ $titulo }}</h1>

        @if ($subtitulo)
            <p class="mt-1 text-sm text-muted-foreground">{{ $subtitulo }}</p>
        @endif
    </div>

    @isset($acciones)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $acciones }}
        </div>
    @endisset
</div>
