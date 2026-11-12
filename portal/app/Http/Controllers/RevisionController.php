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
            'texto'    => ['required', 'string', 'max:2000'],
            'serie_id' => ['nullable', 'integer', 'exists:series,id'],
        ], [
            'texto.required' => 'Escriba qué debe corregir el establecimiento.',
        ]);

        // Si el revisor apunta a una serie, la observación se ata al archivo
        // vigente de esa serie: así se resuelve sola cuando el encargado
        // sube la corrección.
        $archivo = null;

        if (! empty($datos['serie_id'])) {
            $archivo = $envio->archivosVigentes()->where('serie_id', $datos['serie_id'])->first();
        }

        $this->observaciones->registrar($envio, $request->user(), $datos['texto'], $archivo);
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
     * Confirma la carga en el sistema institucional (T-F3-07, MVP manual).
     */
    public function cargar(Request $request, Envio $envio): RedirectResponse
    {
        $this->authorize('cargar', $envio);

        $datos = $request->validate([
            'referencia' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->estados->confirmarCarga($envio, $request->user(), $datos['referencia'] ?? null);
        } catch (EnvioInvalidoException $e) {
            return back()->withErrors(['cargar' => $e->getMessage()]);
        }

        return redirect()
            ->route('revision.index')
            ->with('ok', 'Carga confirmada.');
    }

    /**
     * Reabre un envío aprobado o cargado (T-F3-08, solo administración).
     */
    public function reaperturar(Request $request, Envio $envio): RedirectResponse
    {
        $this->authorize('reabrir', $envio);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ], [
            'motivo.required' => 'Escriba por qué se reabre: queda en el historial.',
        ]);

        try {
            $this->estados->reaperturar($envio, $request->user(), $datos['motivo']);
        } catch (EnvioInvalidoException $e) {
            return back()->withErrors(['reaperturar' => $e->getMessage()]);
        }

        return redirect()
            ->route('revision.index')
            ->with('ok', 'Envío reabierto: vuelve a revisión.');
    }

    /**
     * Anula un envío (T-F3-08, solo administración).
     */
    public function anular(Request $request, Envio $envio): RedirectResponse
    {
        $this->authorize('anular', $envio);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ], [
            'motivo.required' => 'Escriba el motivo de la anulación: queda en el historial.',
        ]);

        try {
            $this->estados->anular($envio, $request->user(), $datos['motivo']);
        } catch (EnvioInvalidoException $e) {
            return back()->withErrors(['anular' => $e->getMessage()]);
        }

        return redirect()
            ->route('revision.index')
            ->with('ok', 'Envío anulado.');
    }
}
