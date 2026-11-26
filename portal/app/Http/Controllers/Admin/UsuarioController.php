<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\User;
use App\Services\Avisar;
use App\Support\RolUi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Gestión de usuarios (MOD-01, RF-013 a RF-016, RF-042).
 *
 * Un usuario nunca se elimina: se desactiva (R-ID-04). El administrador no
 * puede resetear su propia contraseña ni quitarse el rol (R-ID-05), para no
 * perder el último acceso administrativo.
 *
 * La v3 agrega el reparto de establecimientos en el mismo formulario: dar de
 * alta a un revisor sin establecimientos lo deja con una bandeja vacía, y eso
 * pasa por ser un fallo del sistema antes que una configuración pendiente.
 */
class UsuarioController extends Controller
{
    public function __construct(private readonly Avisar $avisar)
    {
    }

    public function index(): View
    {
        return view('admin.index', [
            'titulo'   => 'Usuarios',
            'ruta'     => 'admin.usuarios',
            'columnas' => ['Nombre' => 'name', 'Correo' => 'email', 'Activo' => 'activo'],
            'filas'    => User::with(['roles', 'establecimientos'])->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return $this->formulario(new User(), route('admin.usuarios.store'), 'POST', 'Nuevo usuario');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'email'       => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password'    => ['required', 'string', 'min:12'],
            'rol'         => ['required', Rule::in(Role::pluck('name')->all())],
            'activo'      => ['boolean'],
            'establecimientos'   => ['array'],
            'establecimientos.*' => ['integer', 'exists:establecimientos,id'],
        ]);

        $usuario = DB::transaction(function () use ($datos) {
            $usuario = User::create([
                'name'     => $datos['name'],
                'email'    => $datos['email'],
                'password' => Hash::make($datos['password']),
                'activo'   => (bool) ($datos['activo'] ?? true),
            ]);

            $usuario->syncRoles([$datos['rol']]);
            $usuario->establecimientos()->sync($datos['establecimientos'] ?? []);

            return $usuario;
        });

        // R-ALC-01: un revisor o un encargado sin establecimientos no ve nada.
        // El aviso al administrador convierte un síntoma mudo en una tarea.
        if ($this->repartePorEstablecimiento($usuario) && $usuario->establecimientos()->count() === 0) {
            $this->avisar->sinAsignacion($usuario);
        }

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
            'name'        => ['required', 'string', 'max:150'],
            'email'       => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password'    => ['nullable', 'string', 'min:12'],
            'rol'         => ['required', Rule::in(Role::pluck('name')->all())],
            'activo'      => ['boolean'],
            'establecimientos'   => ['array'],
            'establecimientos.*' => ['integer', 'exists:establecimientos,id'],
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

        if (! empty($datos['password']) && $usuario->id === $request->user()->id) {
            return back()->withErrors(['password' => 'Use su perfil para cambiar su propia contraseña.']);
        }

        DB::transaction(function () use ($usuario, $datos, $request) {
            $usuario->fill([
                'name'   => $datos['name'],
                'email'  => $datos['email'],
                'activo' => (bool) ($datos['activo'] ?? true),
            ]);

            if (! empty($datos['password'])) {
                $usuario->password = Hash::make($datos['password']);
            }

            $usuario->save();
            $usuario->syncRoles([$datos['rol']]);

            // El administrador no se reparte a sí mismo: su alcance es global y
            // sincronizar vacío le borraría cualquier asignación previa sin que
            // la pantalla la muestre.
            if ($usuario->id !== $request->user()->id) {
                $usuario->establecimientos()->sync($datos['establecimientos'] ?? []);
            }
        });

        if ($this->repartePorEstablecimiento($usuario) && $usuario->establecimientos()->count() === 0) {
            $this->avisar->sinAsignacion($usuario);
        }

        return redirect()
            ->route('admin.usuarios.index')
            ->with('ok', 'Usuario actualizado.');
    }

    /** ¿El rol de este usuario se define por establecimientos asignados? */
    private function repartePorEstablecimiento(User $usuario): bool
    {
        return $usuario->hasAnyRole(['revisor', 'encargado']);
    }

    private function formulario(User $usuario, string $accion, string $metodo, string $prefijo): View
    {
        return view('admin.usuarios.form', [
            'titulo'    => $prefijo,
            'usuario'   => $usuario,
            'accion'    => $accion,
            'metodo'    => $metodo,
            'roles'     => RolUi::opciones(Role::orderBy('name')->pluck('name')->all()),
            'rolActual' => $usuario->exists ? $usuario->getRoleNames()->first() : 'encargado',
            'asignados' => $usuario->exists
                ? $usuario->establecimientos()->pluck('establecimientos.id')->all()
                : [],
            // Solo los activos: asignar un establecimiento dado de baja no da
            // acceso a nada y ensucia la lista.
            'establecimientos' => Establecimiento::where('activo', true)
                ->orderByRaw('comuna IS NULL')
                ->orderBy('comuna')
                ->orderBy('nombre')
                ->get()
                ->groupBy(fn (Establecimiento $e) => $e->comuna ?: 'Sin comuna'),
        ]);
    }
}
