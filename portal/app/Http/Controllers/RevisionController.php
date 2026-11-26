<?php

namespace App\Http\Controllers;

use App\Dominio\Alcance;
use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Models\Serie;
use App\Services\EstadoEnvioService;
use App\Services\ObservacionService;
use App\Services\SolicitudReenvioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bandeja de revisión del Equipo REM (T-F3-03).
 *
 * El revisor trabaja aquí: ve lo que espera revisión en **sus establecimientos
 * asignados**, observa o aprueba sin entrar envío por envío, y pide una nueva
 * subida cuando encuentra un error. Cada acción pasa por la policy del envío.
 */
class RevisionController extends Controller
{
    public function __construct(
        private readonly ObservacionService $observaciones,
        private readonly EstadoEnvioService $estados,
        private readonly Alcance $alcance,
        private readonly SolicitudReenvioService $reenvios,
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

        if ($request->filled('fuera_de_plazo')) {
            $consulta->where('fuera_de_plazo', true);
        }

        $consulta->orderBy('fecha_envio')->orderBy('id');

        return view('revision.index', [
            'envios' => $consulta->paginate(25)->withQueryString(),
            'establecimientos' => $this->establecimientosDelAlcance($request),
            'sinAsignacion' => $this->alcance->sinAsignacion($usuario),
            'estados' => [
                Envio::EN_REVISION => 'En revisión',
                Envio::OBSERVADO   => 'Observado',
                Envio::VALIDADO    => 'Validado',
                Envio::APROBADO    => 'Aprobado',
                Envio::RECIBIDO    => 'Recibido',
                Envio::CON_ERRORES => 'Con errores',
                ''                 => 'Todos',
            ],
            'filtros' => $request->only(['estado', 'establecimiento_id', 'anio', 'mes', 'dias', 'fuera_de_plazo']),
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
     * Formulario para pedir una nueva subida (RF-044).
     *
     * Se apoya en un período para no ofrecer un desplegable con todos los
     * envíos de todos los años: el revisor trabaja mes a mes.
     */
    public function formularioReenvio(Request $request): View
    {
        $this->authorize('viewAny', Envio::class);

        $anio = $request->filled('anio') ? $request->integer('anio') : (int) now()->year;
        $mes = $request->filled('mes') ? $request->integer('mes') : (int) now()->month;
        $mes = max(1, min(12, $mes));

        return view('revision.reenvio', [
            'anio'   => $anio,
            'mes'    => $mes,
            'envios' => $this->reenvios->enviosDelPeriodo($request->user(), $anio, $mes),
            'series' => Serie::where('activo', true)->orderBy('orden')->get(),
        ]);
    }

    /**
     * Reabre un envío aprobado para que el establecimiento vuelva a subir
     * (RF-044.2).
     */
    public function reabrir(Request $request, Envio $envio): RedirectResponse
    {
        $this->authorize('reabrir', $envio);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'motivo.required' => 'Explique por qué reabre el envío.',
            'motivo.min'      => 'El motivo necesita al menos 10 caracteres.',
        ]);

        try {
            $this->estados->reabrir($envio, $request->user(), $datos['motivo']);
        } catch (EnvioInvalidoException $e) {
            return back()->withErrors(['reabrir' => $e->getMessage()]);
        }

        return redirect()
            ->route('envios.show', $envio)
            ->with('ok', 'Envío reabierto. El establecimiento recibió el aviso.');
    }

    /**
     * Pide al encargado que vuelva a subir la estadística (RF-044).
     *
     * Tres alcances en una sola acción: una serie, todas las series de un
     * envío o todos los establecimientos asignados en un período.
     */
    public function solicitarReenvio(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'alcance'    => ['required', 'in:serie,envio,asignados'],
            'motivo'     => ['required', 'string', 'min:10', 'max:500'],
            'envio_id'   => ['required_if:alcance,serie,envio', 'nullable', 'integer', 'exists:envios,id'],
            'serie_id'   => ['required_if:alcance,serie', 'nullable', 'integer', 'exists:series,id'],
            'anio'       => ['required_if:alcance,asignados', 'nullable', 'integer', 'min:2000', 'max:2100'],
            'mes'        => ['required_if:alcance,asignados', 'nullable', 'integer', 'min:1', 'max:12'],
        ], [
            'motivo.required' => 'Escriba qué debe corregir el establecimiento.',
            'motivo.min'      => 'El motivo necesita al menos 10 caracteres.',
            'alcance.required' => 'Indique el alcance de la solicitud.',
        ]);

        $envio = $datos['envio_id'] ?? null ? Envio::findOrFail($datos['envio_id']) : null;

        if ($envio !== null) {
            // La solicitud es una acción de revisión: se apoya en observar.
            $this->authorize('observar', $envio);
        }

        try {
            $resultado = $this->reenvios->solicitar(
                $request->user(),
                $datos['alcance'],
                $datos['motivo'],
                $envio,
                ! empty($datos['serie_id']) ? Serie::findOrFail($datos['serie_id']) : null,
                isset($datos['anio']) ? (int) $datos['anio'] : null,
                isset($datos['mes']) ? (int) $datos['mes'] : null,
            );
        } catch (EnvioInvalidoException $e) {
            return back()->withErrors(['reenvio' => $e->getMessage()]);
        }

        $mensaje = $resultado['envios'] === 0
            ? 'No hubo envíos que corregir en el período seleccionado.'
            : "Solicitud enviada: {$resultado['envios']} envío(s), {$resultado['observaciones']} observación(es).";

        return redirect()
            ->route('revision.index')
            ->with('ok', $mensaje);
    }

    /**
     * Establecimientos que el revisor puede filtrar: los que tiene asignados.
     */
    private function establecimientosDelAlcance(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        return $this->alcance->establecimientos($request->user());
    }
}
