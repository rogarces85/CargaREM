<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consulta de la bitácora de auditoría (T-F1-28).
 *
 * Solo lectura: no hay rutas de edición ni borrado, y el modelo las rechaza
 * igual (principio 21).
 */
class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $consulta = Auditoria::query()->with('usuario')->orderByDesc('id');

        if ($request->filled('evento')) {
            $consulta->where('evento', $request->string('evento'));
        }

        if ($request->filled('entidad')) {
            $consulta->where('entidad', $request->string('entidad'));
        }

        if ($request->filled('usuario_id')) {
            $consulta->where('usuario_id', $request->integer('usuario_id'));
        }

        if ($request->filled('desde')) {
            $consulta->whereDate('created_at', '>=', $request->date('desde'));
        }

        if ($request->filled('hasta')) {
            $consulta->whereDate('created_at', '<=', $request->date('hasta'));
        }

        return view('admin.auditoria.index', [
            'registros' => $consulta->paginate(50)->withQueryString(),
            'eventos'   => Auditoria::query()->distinct()->orderBy('evento')->pluck('evento'),
            'entidades' => Auditoria::query()->whereNotNull('entidad')->distinct()->orderBy('entidad')->pluck('entidad'),
        ]);
    }
}
