@props([
    'icono' => 'inbox',
    'titulo',
    'texto' => null,
])

{{--
    Estado vacío.

    Una tabla sin filas no debe ser una caja en blanco: dice qué falta y qué
    hacer. El borde es discontinuo para distinguirlo de una tarjeta con datos.
--}}
<div {{ $attributes->class(['flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card px-6 py-12 text-center']) }}>
    <span class="flex size-11 items-center justify-center rounded-full bg-muted">
        <x-ui.icon :name="$icono" class="size-5 text-muted-foreground" />
    </span>

    <p class="mt-4 text-sm font-medium">{{ $titulo }}</p>

    @if ($texto)
        <p class="mt-1 max-w-sm text-xs text-muted-foreground">{{ $texto }}</p>
    @endif

    @isset($accion)
        <div class="mt-4">{{ $accion }}</div>
    @endisset
</div>
