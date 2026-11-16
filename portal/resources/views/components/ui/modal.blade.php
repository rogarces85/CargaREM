@props([
    'id',
    'titulo',
    'ancho' => 'max-w-lg',
])

{{--
    Diálogo modal sobre <dialog> nativo.

    Se usa el elemento nativo y no un div con trucos: el navegador ya aporta el
    foco atrapado, el cierre con Escape, la capa superior y la devolución del
    foco al elemento que lo abrió. `ui/modal.js` solo agrega la apertura por
    `data-modal-open` y el cierre al hacer clic en el fondo.

    `showModal()` es obligatorio: con `show()` el diálogo no queda en la capa
    superior ni bloquea el resto del documento.
--}}
<dialog
    id="{{ $id }}"
    data-modal
    aria-labelledby="{{ $id }}-titulo"
    {{ $attributes->class(['m-auto w-[calc(100vw-2rem)] rounded-xl border border-border bg-card p-0 text-foreground shadow-xl backdrop:bg-black/50', $ancho]) }}
>
    <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
        <h2 id="{{ $id }}-titulo" class="text-base font-semibold">{{ $titulo }}</h2>

        <button
            type="button"
            data-modal-close
            class="-m-1 flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
            aria-label="Cerrar"
        >
            <x-ui.icon name="x" class="size-4" />
        </button>
    </div>

    <div class="px-5 py-4">
        {{ $slot }}
    </div>

    @isset($pie)
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-4">
            {{ $pie }}
        </div>
    @endisset
</dialog>
