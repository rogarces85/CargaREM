<?php

namespace App\Http\Controllers;

use App\Models\ValidadorEjecucion;
use App\Services\ValidadorEstadisticaService;
use App\Validador\CatalogoCalidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Módulo Validador (RF-040).
 *
 * Disponible para todos los usuarios con `validador.usar`. El encargado lo usa
 * para revisar su propio archivo antes de subirlo; el Equipo REM, para revisar
 * la calidad de lo que ya llegó. El flujo de envío no lo conoce.
 */
class ValidadorController extends Controller
{
    public function __construct(private readonly ValidadorEstadisticaService $validador)
    {
    }

    public function index(Request $request): View
    {
        $ejecuciones = ValidadorEjecucion::query()
            ->where('usuario_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        $catalogo = null;

        try {
            $catalogo = CatalogoCalidad::desdeArchivo();
        } catch (Throwable $e) {
            // Si el archivo de reglas falta o está mal, la pantalla lo dice en
            // vez de reventar: el usuario necesita saber que la herramienta no
            // está operativa, no un error 500.
            $errorCatalogo = $e->getMessage();
        }

        return view('validador.index', [
            'ejecuciones'    => $ejecuciones,
            'catalogo'       => $catalogo,
            'errorCatalogo'  => $errorCatalogo ?? null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('validador.usar'), 403);

        $request->validate([
            'archivo' => ['required', 'file', 'max:'.config('rem.archivos.max_kb'), 'mimes:xls,xlsx,xlsm'],
        ], [
            'archivo.required' => 'Elija el archivo Excel que quiere revisar.',
            'archivo.max'      => 'El archivo supera el máximo permitido (' . config('rem.archivos.max_kb') . ' KB).',
            'archivo.mimes'    => 'Solo se aceptan archivos .xls, .xlsx o .xlsm.',
        ]);

        try {
            $resultado = $this->validador->ejecutar($request->user(), $request->file('archivo'));
        } catch (Throwable $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        }

        $ejecucion = $resultado['ejecucion'];

        return redirect()
            ->route('validador.show', $ejecucion)
            ->with('ok', $ejecucion->hallazgos === 0
                ? 'Revisión terminada sin hallazgos.'
                : "Revisión terminada: {$ejecucion->hallazgos} hallazgo(s).");
    }

    public function show(Request $request, ValidadorEjecucion $ejecucion): View
    {
        // Cada usuario ve sus revisiones; el administrador, todas (RF-040.2).
        abort_unless(
            $ejecucion->usuario_id === $request->user()->id || $request->user()->can('validador.estadisticas'),
            403
        );

        return view('validador.show', ['ejecucion' => $ejecucion]);
    }

    /** Registro de uso agregado, solo para el administrador. */
    public function estadisticas(Request $request): View
    {
        abort_unless($request->user()->can('validador.estadisticas'), 403);

        $anio = $request->integer('anio') ?: (int) now()->year;

        return view('validador.estadisticas', [
            'anio'       => $anio,
            'porUsuario' => $this->validador->usoPorUsuario($anio),
            'porMes'     => $this->validador->porMes($anio),
            'total'      => ValidadorEjecucion::whereYear('created_at', $anio)->count(),
        ]);
    }
}
