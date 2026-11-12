<?php

namespace App\Http\Controllers;

use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Models\Establecimiento;
use App\Services\EstadoEnvioService;
use App\Services\ObservacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bandeja de revisión del Servicio de Salud (T-F3-03).
 *
 * El revisor trabaja aquí: ve lo que espera revisión, observa o aprueba sin
 * entrar envío por envío. Cada acción pasa por la policy del envío.
 */
class RevisionController extends Controller
{
    public function __construct(
        private readonly ObservacionService $observaciones,
        private readonly EstadoEnvioService $estados,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Envio::class);

        $usuario = $request->user();

        $consulta = Envio::query()
            ->forUser($usuario)
            ->with(['establecimiento', 'archivosVigentes.serie', 'observaciones'])
            ->withCount(['observaciones as abiertas' => fn ($q) => $q->where('resuelta', false)]);

        // Por defecto, lo que espera revisión: es la bandeja de trabajo.
        $estado = (string) $request->query('estado', Envio::EN_REVISION);

        if ($estado !== '') {
            $consulta->where('estado', $estado);
        }

        if ($request->filled('establecimiento_id')) {
            $consulta->where('establecimiento_id', $request->integer('establecimiento_id'));
        }

        if ($request->filled('anio')) {
            $consulta->where('anio', $request->integer('anio'));
        }

        if ($request->filled('mes')) {
            $consulta->where('mes', $request->integer('mes'));
        }

        // Antigüedad: días desde que el envío llegó.
        if ($request->filled('dias')) {
            $consulta->where('fecha_envio', '<=', now()->subDays($request->integer('dias')));
        }

        $consulta->orderBy('fecha_envio')->orderBy('id');

        return view('revision.index', [
            'envios' => $consulta->paginate(25)->withQueryString(),
            'establecimientos' => Establecimiento::orderBy('nombre')->get(),
            'estados' => [
                Envio::EN_REVISION => 'En revisión',
                Envio::OBSERVADO   => 'Observado',
                Envio::VALIDADO    => 'Validado',
                Envio::APROBADO    => 'Aprobado',
                Envio::RECIBIDO    => 'Recibido',
                Envio::CON_ERRORES => 'Con errores',
                Envio::CARGADO     => 'Cargado',
                ''                 => 'Todos',
            ],
            'filtros' => $request->only(['estado', 'establecimiento_id', 'anio', 'mes', 'dias']),
        ]);
    }

    /**
     * Observa el envío: deja la observación y lo devuelve al establecimiento.
     */
    public function observar(Request $request, Envio $envio): RedirectResponse
    {
        $this->authorize('observar', $envio);

        $datos = $request->validate([
            'texto' => ['required', 'string', 'max:2000'],
        ], [
            'texto.required' => 'Escriba qué debe corregir el establecimiento.',
        ]);

        $this->observaciones->registrar($envio, $request->user(), $datos['texto']);
        $this->estados->transicionar($envio, Envio::OBSERVADO, $request->user(), $datos['texto']);

        return redirect()
            ->route('revision.index')
            ->with('ok', 'Envío observado. El establecimiento recibirá la observación.');
    }

    /**
     * Aprueba el envío.
     *
     * No se aprueba con observaciones abiertas: si el revisor dejó algo sin
     * resolver, aprobarlo sería contradictorio.
     */
    public function aprobar(Request $request, Envio $envio): RedirectResponse
    {
        $this->authorize('aprobar', $envio);

        // Las guardas viven en el servicio: la pantalla no decide si un envío
        // está en condiciones, solo muestra el motivo cuando no lo está.
        try {
            $this->estados->aprobar(
                $envio,
                $request->user(),
                $request->input('comentario') ?: null
            );
        } catch (EnvioInvalidoException $e) {
            return back()->withErrors(['aprobar' => $e->getMessage()]);
        }

        return redirect()
            ->route('revision.index')
            ->with('ok', 'Envío aprobado.');
    }

    /**
     * Marca el envío como cargado en el sistema institucional (MVP manual).
     */
    public function cargar(Envio $envio): RedirectResponse
    {
        $this->authorize('cargar', $envio);

        $this->estados->transicionar($envio, Envio::CARGADO, request()->user(), 'Carga confirmada.');

        return redirect()
            ->route('revision.index')
            ->with('ok', 'Envío marcado como cargado.');
    }
}
