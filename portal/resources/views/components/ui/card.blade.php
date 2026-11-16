@props(['sinRelleno' => false])

{{-- Contenedor base. La superficie, el borde y el radio viven en un solo lugar. --}}
<div {{ $attributes->class(['rounded-xl border border-border bg-card', 'p-4' => ! $sinRelleno]) }}>
    {{ $slot }}
</div>
