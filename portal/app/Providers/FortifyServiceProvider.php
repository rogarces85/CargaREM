<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Registra las vistas de autenticación y la comprobación de credenciales.
     */
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));

        /*
         * R-ID-04: un usuario desactivado no entra, aunque su contraseña sea
         * correcta. Fortify por defecto solo comprueba el hash, así que la
         * comprobación de `activo` va aquí y no en la vista.
         *
         * Se devuelve null en vez de un error distinto para no revelar si el
         * correo existe.
         */
        Fortify::authenticateUsing(function (Request $request) {
            $usuario = User::where('email', $request->email)->first();

            if ($usuario
                && $usuario->puedeEntrar()
                && Hash::check($request->password, $usuario->password)) {
                return $usuario;
            }

            return null;
        });
    }

    public function register(): void
    {
        //
    }
}
