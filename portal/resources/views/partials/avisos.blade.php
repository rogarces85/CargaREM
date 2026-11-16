{{--
    Mensajes del servidor que se muestran como aviso efímero.

    Se dejan en el DOM como datos y `ui/toast.js` los convierte en notificación
    al cargar. Así el servidor no necesita JavaScript para emitirlos y el
    mensaje sobrevive a una redirección.

    Se recogen tres orígenes: `session('ok')`, `session('error')` y los errores
    de validación. Los de validación también deben verse junto al campo, esto
    es el resumen.
--}}
@php
    $avisos = [];

    foreach (['ok' => 'success', 'error' => 'error', 'status' => 'success'] as $clave => $tipo) {
        if (session()->has($clave)) {
            $avisos[] = ['tipo' => $tipo, 'mensaje' => session($clave)];
        }
    }

    if (isset($errors) && $errors->any()) {
        foreach ($errors->all() as $mensaje) {
            $avisos[] = ['tipo' => 'error', 'mensaje' => $mensaje];
        }
    }
@endphp

@if ($avisos)
    <div data-flashes hidden>
        @foreach ($avisos as $aviso)
            <span data-type="{{ $aviso['tipo'] }}" data-message="{{ $aviso['mensaje'] }}"></span>
        @endforeach
    </div>
@endif
