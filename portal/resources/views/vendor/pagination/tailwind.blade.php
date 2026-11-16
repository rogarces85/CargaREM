{{--
    Paginación del portal.

    Sobrescribe `pagination::tailwind` de Laravel (por la ruta de vistas
    publicadas) para que use los tokens del sistema en vez de la paleta gris
    por defecto. Muestra el rango leído, no solo flechas: "1–15 de 42" dice
    cuánto falta sin tener que contar páginas.

    Los enlaces conservan la cadena de consulta de la URL actual, así que un
    filtro aplicado sobrevive al cambio de página.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-muted-foreground">
            Mostrando <span class="tabular font-medium text-foreground">{{ $paginator->firstItem() ?? 0 }}</span>–<span class="tabular font-medium text-foreground">{{ $paginator->lastItem() ?? 0 }}</span>
            de <span class="tabular font-medium text-foreground">{{ $paginator->total() }}</span>
        </p>

        <div class="flex items-center gap-1">
            {{-- Anterior --}}
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="flex size-8 items-center justify-center rounded-lg border border-border text-muted-foreground opacity-40">
                    <x-ui.icon name="chevron-left" class="size-4" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior"
                   class="flex size-8 items-center justify-center rounded-lg border border-border text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground">
                    <x-ui.icon name="chevron-left" class="size-4" />
                </a>
            @endif

            {{-- Números --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="flex size-8 items-center justify-center text-xs text-muted-foreground" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                  class="flex size-8 items-center justify-center rounded-lg bg-primary text-xs font-semibold tabular text-primary-foreground">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" aria-label="Página {{ $page }}"
                               class="flex size-8 items-center justify-center rounded-lg border border-border text-xs tabular text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Siguiente --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Página siguiente"
                   class="flex size-8 items-center justify-center rounded-lg border border-border text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground">
                    <x-ui.icon name="chevron-right" class="size-4" />
                </a>
            @else
                <span aria-disabled="true" class="flex size-8 items-center justify-center rounded-lg border border-border text-muted-foreground opacity-40">
                    <x-ui.icon name="chevron-right" class="size-4" />
                </span>
            @endif
        </div>
    </nav>
@endif
