<?php

/*
 * Mensajes de autenticación en español (Constitución, principio 11).
 *
 * Sin este archivo, cualquier error de acceso se muestra al usuario como la
 * clave cruda: "auth.throttle", "auth.failed". Lo detectó el test de bloqueo
 * por intentos.
 */
return [

    'failed'   => 'Los datos ingresados no coinciden con nuestros registros.',
    'password' => 'La contraseña no es correcta.',
    'throttle' => 'Demasiados intentos de acceso. Vuelva a intentarlo en :seconds segundos.',

];
