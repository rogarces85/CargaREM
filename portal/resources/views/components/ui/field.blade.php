@props([
    'nombre',
    'etiqueta',
    'error' => null,
    'ayuda' => null,
    'requerido' => false,
])

{{--
    Campo de formulario: etiqueta, control, mensaje.

    El control entra por el slot y debe llevar `id="{{ $nombre }}"` y, cuando
    hay error, `aria-invalid="true" aria-describedby="{{ $nombre }}-error"`.
    El `id` de la etiqueta apunta al control por `for`, así que un lector de
    pantalla anuncia el nombre del campo y no solo el valor (principio 23).
--}}
<div {{ $attributes->class(['w-full']) }}>
    <label for="{{ $nombre }}" class="campo">
        {{ $etiqueta }}
        @if ($requerido)
            <span class="text-status-danger-fg" aria-hidden="true">*</span>
        @endif
    </label>

    {{ $slot }}

    @if ($error)
        <p id="{{ $nombre }}-error" class="campo-error">
            <x-ui.icon name="circle-exclamation-point" class="mt-px size-3.5 shrink-0" />
            <span>{{ $error }}</span>
        </p>
    @elseif ($ayuda)
        <p class="campo-ayuda">{{ $ayuda }}</p>
    @endif
</div>
