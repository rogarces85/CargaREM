<?php

namespace App\Http\Controllers;

use App\Exceptions\ArchivoRechazadoException;
use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Services\EnvioService;
use App\Services\EstadoEnvioService;
use App\Services\ReporteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantallas del flujo de envío (T-F2-10).
 *
 * Todo pasa por la policy: el controlador no decide permisos, solo pregunta.
 */
class EnvioController extends Controller
{
    public function __construct(
        private readonly EnvioService $envios,
        private readonly EstadoEnvioService $estados,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Envio::class);

        $usuario = $request->user();

        $envios = Envio::query()
            ->forUser($usuario)
            ->with(['establecimiento', 'archivosVigentes.serie'])
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->paginate(20);

        return view('envios.index', [
            'envios'       => $envios,
            'establecimientos' => $usuario->hasRole('admin')
                ? Establecimiento::orderBy('nombre')->get()
                : $usuario->establecimientos()->orderBy('nombre')->get(),
            'periodos'     => Periodo::where('estado', '!=', Periodo::CERRADO)
                ->orderByDesc('anio')->orderByDesc('mes')->get(),
            'puedeCrear'   => $usuario->hasRole('admin') || $usuario->establecimientos()->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'periodo'            => ['required', 'regex:/^\d{4}-(?:[1-9]|1[0-2])$/'],
        ], [
            'periodo.regex' => 'Elija un período válido.',
        ]);

        [$anio, $mes] = array_map('intval', explode('-', $datos['periodo']));

        $establecimiento = Establecimiento::findOrFail($datos['establecimiento_id']);

        $this->authorize('create', [Envio::class, $establecimiento]);

        try {
            $envio = $this->envios->crear($establecimiento, $anio, $mes, $request->user());
        } catch (EnvioInvalidoException $e) {
            return back()->withInput()->withErrors(['envio' => $e->getMessage()]);
        }

        return redirect()
            ->route('envios.show', $envio)
            ->with('ok', 'Envío creado. Suba el archivo de cada serie.');
    }

    public function show(Envio $envio): View
    {
        $this->authorize('view', $envio);

        $envio->load([
            'establecimiento', 'archivos.serie', 'archivos.validaciones',
            'observaciones.autor', 'transiciones.actor', 'validaciones',
        ]);

        $exigidas = $envio->establecimiento->series()
            ->wherePivot('obligatoria', true)
            ->orderBy('orden')
            ->get();

        $vigentes = $envio->archivosVigentes->keyBy('serie_id');

        return view('envios.show', [
            'envio'     => $envio,
            'exigidas'  => $exigidas,
            'vigentes'  => $vigentes,
            'series'    => Serie::where('activo', true)->orderBy('orden')->get(),
            'puedeSubir' => request()->user()->can('update', $envio),
        ]);
    }

    /**
     * Sube o reemplaza el archivo de una serie (OP-02).
     */
    public function subir(Request $request, Envio $envio): RedirectResponse
    {
        $this->authorize('update', $envio);

        $datos = $request->validate([
            'serie_id' => ['required', 'integer', 'exists:series,id'],
            'archivo'  => ['required', 'file', 'max:'.config('rem.archivos.max_kb')],
        ], [
            'archivo.max' => 'El archivo supera el máximo permitido de '.config('rem.archivos.max_kb').' KB.',
        ]);

        $serie = Serie::findOrFail($datos['serie_id']);

        try {
            $registro = $this->envios->subirVersion($envio, $serie, $request->file('archivo'), $request->user());
        } catch (ArchivoRechazadoException|EnvioInvalidoException $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        }

        $this->envios->revalidar($envio);
        $this->estados->recalcular($envio->fresh());

        return redirect()
            ->route('envios.show', $envio)
            ->with('ok', "Archivo de la serie {$serie->codigo} subido y validado.");
    }

    /**
     * Cambia el estado del envío (enviar, observar, aprobar, cargar, reabrir, anular).
     */
    public function transicion(Request $request, Envio $envio): RedirectResponse
    {
        $datos = $request->validate([
            'accion' => ['required', 'string', 'in:enviar,observar,aprobar,cargar,reabrir,anular'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $destino = match ($datos['accion']) {
            'enviar'   => Envio::EN_REVISION,
            'observar' => Envio::OBSERVADO,
            'aprobar'  => Envio::APROBADO,
            'cargar'   => Envio::CARGADO,
            'reabrir'  => Envio::EN_REVISION,
            'anular'   => Envio::ANULADO,
        };

        $habilidad = match ($datos['accion']) {
            'enviar', 'reabrir' => $datos['accion'],
            'observar'          => 'observar',
            'aprobar'           => 'aprobar',
            'cargar'            => 'cargar',
            'anular'            => 'anular',
        };

        $this->authorize($habilidad, $envio);

        try {
            $this->estados->transicionar($envio, $destino, $request->user(), $datos['motivo'] ?? null);
        } catch (EnvioInvalidoException $e) {
            return back()->withErrors(['transicion' => $e->getMessage()]);
        }

        return redirect()
            ->route('envios.show', $envio)
            ->with('ok', 'Envío actualizado a «'.$envio->fresh()->estado.'».');
    }

    public function reporte(Envio $envio, ReporteService $reportes)
    {
        $this->authorize('view', $envio);

        if (! request()->user()->can('reportes.descargar')) {
            abort(403);
        }

        return $reportes->descargar($envio);
    }
}
