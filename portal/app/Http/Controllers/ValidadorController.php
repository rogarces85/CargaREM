<?php

namespace App\Http\Controllers;

use App\Models\ValidadorEjecucion;
use App\Services\ValidadorEstadisticaService;
use App\Validador\ArchivoNoValidable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Módulo Validador (RF-040).
 *
 * Disponible para todos los usuarios con `validador.usar`. El encargado lo usa
 * para revisar su propio archivo antes de subirlo; el Equipo REM, para revisar
 * la calidad de lo que ya llegó. **El flujo de envío no lo conoce.**
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

        $catalogo = $this->validador->catalogo();
        $nomina = $this->validador->nomina();

        return view('validador.index', [
            'ejecuciones'   => $ejecuciones,
            'catalogo'      => $catalogo,
            'nomina'        => $nomina,
            'errorCatalogo' => $catalogo === null
                ? 'El archivo de reglas del Validador no se puede leer.'
                : null,
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
        } catch (ArchivoNoValidable $e) {
            return back()->withErrors([
                'archivo' => $e->getMessage() . ($e->detalles === [] ? '' : ' ' . implode(' ', $e->detalles)),
            ]);
        }

        return redirect()
            ->route('validador.show', $resultado['ejecucion'])
            ->with('ok', $resultado['resultado']->resumen->hallazgos === 0
                ? 'Revisión terminada sin hallazgos.'
                : 'Revisión terminada: ' . $resultado['resultado']->resumen->hallazgos . ' hallazgo(s).');
    }

    public function show(Request $request, ValidadorEjecucion $ejecucion): View
    {
        // Cada usuario ve sus revisiones; el administrador, todas (RF-040.2).
        abort_unless(
            $ejecucion->usuario_id === $request->user()->id || $request->user()->can('validador.estadisticas'),
            403
        );

        return view('validador.show', [
            'ejecucion'  => $ejecucion,
            'resultado'  => $ejecucion->resultado ?? [],
            'hallazgos'  => $ejecucion->listaHallazgos(),
        ]);
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
            'catalogo'   => $this->validador->catalogo(),
        ]);
    }
}
