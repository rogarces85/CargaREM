<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Gestión de usuarios (MOD-01, RF-013 a RF-016).
 *
 * Un usuario nunca se elimina: se desactiva (R-ID-04). El administrador no
 * puede resetear su propia contraseña ni quitarse el rol (R-ID-05), para no
 * perder el último acceso administrativo.
 */
class UsuarioController extends Controller
{
    public function index(): View
    {
        return view('admin.index', [
            'titulo'   => 'Usuarios',
            'ruta'     => 'admin.usuarios',
            'columnas' => ['Nombre' => 'name', 'Correo' => 'email', 'Activo' => 'activo'],
            'filas'    => User::with('roles')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return $this->formulario(new User(), route('admin.usuarios.store'), 'POST', 'Nuevo usuario');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12'],
            'rol'      => ['required', Rule::in(Role::pluck('name')->all())],
            'activo'   => ['boolean'],
        ]);

        $usuario = User::create([
            'name'     => $datos['name'],
            'email'    => $datos['email'],
            'password' => Hash::make($datos['password']),
            'activo'   => (bool) ($datos['activo'] ?? true),
        ]);

        $usuario->syncRoles([$datos['rol']]);

        return redirect()
            ->route('admin.usuarios.index')
            ->with('ok', 'Usuario creado.');
    }

    public function edit(User $usuario): View
    {
        return $this->formulario($usuario, route('admin.usuarios.update', $usuario), 'PUT', 'Editar usuario');
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'string', 'min:12'],
            'rol'      => ['required', Rule::in(Role::pluck('name')->all())],
            'activo'   => ['boolean'],
        ]);

        // R-ID-05: el administrador no puede desactivarse ni cambiarse el rol
        // a sí mismo. Evita perder el último acceso administrativo.
        if ($usuario->id === $request->user()->id) {
            if (! (bool) ($datos['activo'] ?? true)) {
                return back()->withErrors(['activo' => 'No puede desactivar su propia cuenta.']);
            }

            if ($datos['rol'] !== 'admin') {
                return back()->withErrors(['rol' => 'No puede quitarse el rol de administrador.']);
            }
        }

        $usuario->fill([
            'name'   => $datos['name'],
            'email'  => $datos['email'],
            'activo' => (bool) ($datos['activo'] ?? true),
        ]);

        if (! empty($datos['password'])) {
            if ($usuario->id === $request->user()->id) {
                return back()->withErrors(['password' => 'Use su perfil para cambiar su propia contraseña.']);
            }

            $usuario->password = Hash::make($datos['password']);
        }

        $usuario->save();
        $usuario->syncRoles([$datos['rol']]);

        return redirect()
            ->route('admin.usuarios.index')
            ->with('ok', 'Usuario actualizado.');
    }

    private function formulario(User $usuario, string $accion, string $metodo, string $prefijo): View
    {
        return view('admin.usuarios.form', [
            'titulo'   => $prefijo,
            'usuario'  => $usuario,
            'accion'   => $accion,
            'metodo'   => $metodo,
            'roles'    => Role::orderBy('name')->pluck('name', 'name')->all(),
            'rolActual' => $usuario->exists ? $usuario->getRoleNames()->first() : 'encargado',
        ]);
    }
}
