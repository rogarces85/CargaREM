<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /*
     * En Laravel 12 el controlador base llega vacío: `authorize()` no existe
     * salvo que se agregue el trait. Los controladores de catálogos resolvían
     * los permisos con middleware, pero el flujo de envío necesita policies
     * por modelo (¿puede este usuario ver ESTE envío?).
     */
    use AuthorizesRequests;
}
