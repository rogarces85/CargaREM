@props(['valor', 'punto' => true])

{{--
    Insignia de un estado del dominio.

    Traduce el valor crudo (`en_revision`, `con_errores`) a etiqueta y tono con
    `App\Support\EstadoUi`. Las vistas nunca escriben el color: piden el estado.
--}}
@php
    $estado = \App\Support\EstadoUi::de($valor);
@endphp

<x-ui.badge :tono="$estado['tono']" :punto="$punto" {{ $attributes }}>
    {{ $estado['etiqueta'] }}
</x-ui.badge>
