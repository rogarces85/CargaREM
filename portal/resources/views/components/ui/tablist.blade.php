@props([
    'items' => [],
    'activo' => null,
    'etiqueta' => 'Secciones',
])

{{--
    Fila de pestañas.

    Recibe `items` como `['id' => 'Etiqueta']` y marca el activo. Los paneles
    se escriben en la vista con `data-panel="id"` y `hidden` cuando no van. El
    módulo `ui/tabs.js` se encarga del teclado: flechas, Home y End, con una
    sola pestaña en el orden de tabulación.
--}}
@php
    $activo = $activo ?? array_key_first($items);
@endphp

<div role="tablist" aria-label="{{ $etiqueta }}" {{ $attributes->class(['flex gap-1 overflow-x-auto border-b border-border']) }}>
    @foreach ($items as $id => $rotulo)
        <button
            type="button"
            role="tab"
            data-tab="{{ $id }}"
            id="tab-{{ $id }}"
            aria-controls="panel-{{ $id }}"
            aria-selected="{{ $id === $activo ? 'true' : 'false' }}"
            tabindex="{{ $id === $activo ? '0' : '-1' }}"
            class="-mb-px whitespace-nowrap border-b-2 border-transparent px-3 py-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground aria-selected:border-primary aria-selected:text-foreground"
        >
            {{ $rotulo }}
        </button>
    @endforeach
</div>
