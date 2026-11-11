<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogoSnapshot;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Listado de snapshots e importación desde ZIP (T-F1-20, T-F1-21).
 *
 * Importar nunca cambia lo que el portal está usando: el snapshot nace
 * inactivo y se activa a mano.
 */
class CatalogoSnapshotController extends Controller
{
    public function index(): View
    {
        return view('admin.snapshots.index', [
            'snapshots' => CatalogoSnapshot::orderByDesc('id')->paginate(15),
            'activo'    => CatalogoSnapshot::activo()->first(),
        ]);
    }

    public function importar(Request $request, CatalogoService $servicio): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ]);

        $ruta = $request->file('archivo')->storeAs(
            'catalogo_tmp',
            'snapshot_'.now()->format('Ymd_His').'.zip',
            'local'
        );

        try {
            $snapshot = $servicio->importarZip(\Illuminate\Support\Facades\Storage::disk('local')->path($ruta), $request->user()?->id);

            return redirect()
                ->route('admin.snapshots.index')
                ->with('ok', "Snapshot {$snapshot->id} importado con ".number_format((int) $snapshot->filas_diccionario, 0, ',', '.').' filas. Queda inactivo.');
        } catch (\App\Exceptions\CatalogoInvalidoException $e) {
            return redirect()
                ->route('admin.snapshots.index')
                ->with('error', "{$e->codigo}: {$e->getMessage()} {$e->sugerencia}");
        }
    }

    public function activar(CatalogoSnapshot $snapshot, CatalogoService $servicio): RedirectResponse
    {
        $servicio->activar($snapshot);

        return redirect()
            ->route('admin.snapshots.index')
            ->with('ok', "Snapshot {$snapshot->id} activado.");
    }
}