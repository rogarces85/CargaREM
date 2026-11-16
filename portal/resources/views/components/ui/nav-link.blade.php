@props([
    'href',
    'icono',
    'activo' => false,
])

{{--
    Enlace de navegación.

    El estado activo se marca con `aria-current="page"` además del color: quien
    navega con lector de pantalla necesita saber dónde está, y el color solo no
    lo dice (constitución, principio 30).
--}}
<a
    href="{{ $href }}"
    @if ($activo) aria-current="page" @endif
    {{ $attributes->class([
        'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
        'bg-sidebar-accent text-sidebar-accent-foreground' => $activo,
        'text-sidebar-foreground/70 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground' => ! $activo,
    ]) }}
>
    <x-ui.icon
        :name="$icono"
        class="size-[18px] shrink-0 {{ $activo ? 'text-sidebar-accent-foreground' : 'text-sidebar-foreground/50 group-hover:text-sidebar-foreground' }}"
    />
    <span>{{ $slot }}</span>
</a>
