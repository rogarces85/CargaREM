<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Cuenta propia del usuario (D-030).
 *
 * El administrador crea la cuenta, fija el correo y restablece contraseñas
 * ajenas. Cada usuario cambia la suya aquí, con la actual como comprobación.
 * El correo no se edita: lo administra el portal.
 */
class PerfilController extends Controller
{
    public function edit(Request $request): View
    {
        return view('perfil', ['usuario' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'actual'       => ['nullable', 'required_with:password', 'current_password'],
            'password'     => ['nullable', 'string', 'min:12', 'confirmed'],
        ], [
            'actual.current_password' => 'La contraseña actual no coincide.',
            'password.min'            => 'La contraseña nueva debe tener al menos 12 caracteres.',
            'password.confirmed'      => 'La confirmación de la contraseña no coincide.',
        ]);

        $usuario = $request->user();
        $usuario->name = $datos['name'];

        if (! empty($datos['password'])) {
            $usuario->password = Hash::make($datos['password']);
        }

        $usuario->save();

        return redirect()
            ->route('perfil.edit')
            ->with('ok', empty($datos['password']) ? 'Datos actualizados.' : 'Contraseña actualizada.');
    }
}
