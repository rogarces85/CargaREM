@props(['titulo' => null])

{{--
    Línea de tiempo.

    Se usa para el historial de transiciones de un envío: quién movió el estado,
    cuándo y con qué motivo. Cada elemento dibuja su conector; la hoja de
    estilos oculta el del último, porque un componente no puede saber si es el
    último de la lista.
--}}
<div {{ $attributes }}>
    @if ($titulo)
        <p class="mb-4 text-sm font-semibold">{{ $titulo }}</p>
    @endif

    <ol class="linea-tiempo relative space-y-6">
        {{ $slot }}
    </ol>
</div>
