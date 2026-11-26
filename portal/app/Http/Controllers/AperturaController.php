<?php

namespace App\Http\Controllers;

use App\Dominio\Alcance;
use App\Exceptions\EnvioInvalidoException;
use App\Models\Apertura;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Services\AperturaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Aperturas de fuera de plazo (RF-043).
 *
 * Una sola pantalla con dos caras: el encargado pide y consulta sus
 * solicitudes; el administrador resuelve, abre por su cuenta y anula. La
 * decisión de quién ve qué la toma la policy, no la vista.
 */
class AperturaController extends Controller
{
    public function __construct(
        private readonly AperturaService $aperturas,
        private readonly Alcance $alcance,
    ) {
    }

    public function index(Request $request): View
    {
        $usuario = $request->user();

        abort_unless($usuario->can('aperturas.solicitar') || $usuario->can('aperturas.resolver'), 403);

        $esAdmin = $usuario->hasRole('admin');

        // El administrador ve la bandeja completa; el encargado, las de sus
        // establecimientos (R-ALC-01).
        $consulta = Apertura::query()
            ->with(['establecimiento', 'solicitante', 'resolvedor'])
            ->orderByRaw("FIELD(estado, 'pendiente', 'aprobada', 'rechazada', 'anulada')")
            ->orderByDesc('created_at');

        if (! $esAdmin) {
            $ids = $this->alcance->ids($usuario);

            $consulta->where(function ($q) use ($ids) {
                $q->whereIn('establecimiento_id', $ids)->orWhereNull('establecimiento_id');
            });
        }

        if ($request->filled('estado')) {
            $consulta->where('estado', $request->query('estado'));
        }

        return view('aperturas.index', [
            'aperturas'        => $consulta->paginate(25)->withQueryString(),
            'esAdmin'          => $esAdmin,
            'establecimientos' => $this->alcance->establecimientos($usuario),
            'periodos'         => Periodo::orderByDesc('anio')->orderByDesc('mes')->limit(24)->get(),
            'filtros'          => $request->only(['estado']),
        ]);
    }

    /** El encargado pide una apertura; el administrador puede usar la misma vía. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('aperturas.solicitar'), 403);

        $datos = $request->validate([
            'establecimiento_id' => ['nullable', 'integer', 'exists:establecimientos,id'],
            'anio'               => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes'                => ['required', 'integer', 'min:1', 'max:12'],
            'motivo'             => ['required', 'string', 'min:15', 'max:500'],
        ], [
            'motivo.required' => 'Escriba por qué necesita cargar fuera de plazo.',
            'motivo.min'      => 'El motivo necesita al menos 15 caracteres: el administrador decide con esa información.',
        ]);

        $establecimiento = ! empty($datos['establecimiento_id'])
            ? Establecimiento::findOrFail($datos['establecimiento_id'])
            : null;

        // Alcance en la consulta, no en la vista: nadie pide por un
        // establecimiento que no opera.
        if ($establecimiento !== null && ! $this->alcance->incluye($request->user(), $establecimiento)) {
            abort(403);
        }

        if ($establecimiento === null && ! $request->user()->hasRole('admin')) {
            abort(403);
        }

        try {
            $resultado = $this->aperturas->solicitar(
                $request->user(),
                $establecimiento,
                (int) $datos['anio'],
                (int) $datos['mes'],
                $datos['motivo'],
            );
        } catch (EnvioInvalidoException $e) {
            return back()->withInput()->withErrors(['apertura' => $e->getMessage()]);
        }

        return redirect()
            ->route('aperturas.index')
            ->with('ok', $resultado['creada']
                ? 'Solicitud enviada. El administrador la revisará y recibirá el aviso.'
                : 'Ya había una solicitud pendiente para ese período: se muestra abajo.');
    }

    /** El administrador abre una ventana por su cuenta, con motivo. */
    public function otorgar(Request $request): RedirectResponse
    {
        $this->authorize('resolver', Apertura::class);

        $datos = $request->validate([
            'establecimiento_id' => ['nullable', 'integer', 'exists:establecimientos,id'],
            'anio'               => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes'                => ['required', 'integer', 'min:1', 'max:12'],
            'motivo'             => ['required', 'string', 'min:15', 'max:500'],
            'vigente_hasta'      => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'motivo.min'              => 'El motivo de la apertura necesita al menos 15 caracteres.',
            'vigente_hasta.after_or_equal' => 'La fecha de término no puede ser anterior a hoy.',
        ]);

        try {
            $this->aperturas->otorgar(
                $request->user(),
                ! empty($datos['establecimiento_id']) ? Establecimiento::findOrFail($datos['establecimiento_id']) : null,
                (int) $datos['anio'],
                (int) $datos['mes'],
                $datos['motivo'],
                $datos['vigente_hasta'] ?? null,
            );
        } catch (EnvioInvalidoException $e) {
            return back()->withInput()->withErrors(['apertura' => $e->getMessage()]);
        }

        return redirect()
            ->route('aperturas.index')
            ->with('ok', 'Apertura otorgada. El establecimiento recibió el aviso.');
    }

    public function resolver(Request $request, Apertura $apertura): RedirectResponse
    {
        $this->authorize('resolver', $apertura);

        $datos = $request->validate([
            'decision'      => ['required', 'in:aprobar,rechazar'],
            'motivo'        => ['required', 'string', 'min:10', 'max:500'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        try {
            $this->aperturas->resolver(
                $apertura,
                $request->user(),
                $datos['decision'] === 'aprobar',
                $datos['motivo'],
                $datos['vigente_hasta'] ?? null,
            );
        } catch (EnvioInvalidoException $e) {
            return back()->withErrors(['apertura' => $e->getMessage()]);
        }

        return redirect()
            ->route('aperturas.index')
            ->with('ok', $datos['decision'] === 'aprobar' ? 'Solicitud aprobada.' : 'Solicitud rechazada.');
    }

    public function anular(Request $request, Apertura $apertura): RedirectResponse
    {
        $this->authorize('anular', $apertura);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        try {
            $this->aperturas->anular($apertura, $request->user(), $datos['motivo']);
        } catch (EnvioInvalidoException $e) {
            return back()->withErrors(['apertura' => $e->getMessage()]);
        }

        return redirect()
            ->route('aperturas.index')
            ->with('ok', 'Apertura anulada. El bloqueo vuelve a aplicar.');
    }
}
