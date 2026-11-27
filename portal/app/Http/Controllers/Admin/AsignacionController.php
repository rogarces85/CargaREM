<?php

namespace App\Http\Controllers\Admin;

use App\Dominio\Alcance;
use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\Avisar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reparto de establecimientos (RF-042, D-042).
 *
 * El administrador decide qué establecimientos revisa cada integrante del
 * Equipo REM. Sin esta pantalla, el cambio de alcance de la v3 dejaría a los
 * revisores sin nada que ver: el alcance es la asignación explícita.
 *
 * Se reutiliza el pivot `usuario_establecimiento`, el mismo que ya usaba el
 * encargado. Un establecimiento puede tener varios revisores (R-ALC-03).
 */
class AsignacionController extends Controller
{
    public function __construct(
        private readonly Alcance $alcance,
        private readonly Avisar $avisar,
    ) {
    }

    public function index(Request $request): View
    {
        $roles = $request->query('rol', 'revisor');

        if (! in_array($roles, ['revisor', 'encargado'], true)) {
            $roles = 'revisor';
        }

        $usuarios = User::role($roles)
            ->with(['establecimientos' => fn ($q) => $q->orderBy('nombre')])
            ->withCount('establecimientos')
            ->orderBy('name')
            ->get();

        return view('admin.asignaciones.index', [
            'usuarios'         => $usuarios,
            'rol'              => $roles,
            'sinAsignacion'    => $usuarios->where('establecimientos_count', 0),
            'totalEstablecimientos' => Establecimiento::where('activo', true)->count(),
        ]);
    }

    public function edit(User $usuario): View
    {
        abort_unless($usuario->hasAnyRole(['revisor', 'encargado']), 404);

        $asignados = $usuario->establecimientos()->pluck('establecimientos.id')->all();

        // Agrupado por comuna: el reparto real se hace por territorio y una
        // lista plana de 86 establecimientos no se lee.
        $establecimientos = Establecimiento::where('activo', true)
            ->orderByRaw('comuna IS NULL')
            ->orderBy('comuna')
            ->orderBy('nombre')
            ->get()
            ->groupBy(fn (Establecimiento $e) => $e->comuna ?: 'Sin comuna');

        return view('admin.asignaciones.form', [
            'usuario'          => $usuario,
            'asignados'        => $asignados,
            'establecimientos' => $establecimientos,
        ]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        abort_unless($usuario->hasAnyRole(['revisor', 'encargado']), 404);

        $datos = $request->validate([
            'establecimientos'   => ['array'],
            'establecimientos.*' => ['integer', 'exists:establecimientos,id'],
        ], [
            'establecimientos.*.exists' => 'Uno de los establecimientos ya no existe en el catálogo.',
        ]);

        $nuevos = array_map('intval', $datos['establecimientos'] ?? []);

        DB::transaction(function () use ($usuario, $nuevos) {
            $usuario->establecimientos()->sync($nuevos);

            AuditoriaService::registrar('usuario.asignaciones', 'User', $usuario->id, [
                'establecimientos' => count($nuevos),
            ]);
        });

        $cantidad = $usuario->establecimientos()->count();

        // R-ALC-01: sin reparto, el usuario no ve nada. Si la asignación queda
        // vacía se avisa al administrador en lugar de dejar el síntoma mudo.
        if ($cantidad === 0) {
            $this->avisar->sinAsignacion($usuario);
        } elseif ($usuario->hasRole('revisor')) {
            $this->avisar->enviar([$usuario], 'asignacion', [
                'usuario' => $usuario->name,
                'establecimiento' => $cantidad . ' establecimiento(s)',
                'periodo' => 'reparto vigente',
                'url' => route('revision.index'),
            ]);
        }

        $mensaje = $cantidad === 0
            ? 'Asignación guardada, pero ' . $usuario->name . ' quedó sin establecimientos: no verá ningún dato.'
            : 'Asignación guardada: ' . $cantidad . ' establecimiento(s).';

        return redirect()
            ->route('admin.asignaciones.index', ['rol' => $usuario->getRoleNames()->first()])
            ->with('ok', $mensaje);
    }
}
