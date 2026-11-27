<?php

namespace App\Http\Controllers;

use App\Dominio\Alcance;
use App\Dominio\MatrizSeries;
use App\Exceptions\ArchivoRechazadoException;
use App\Exceptions\EnvioInvalidoException;
use App\Exceptions\ValidacionOcupadaException;
use App\Http\Requests\CrearEnvioRequest;
use App\Http\Requests\SubirArchivoRequest;
use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\User;
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
        private readonly MatrizSeries $matriz,
        private readonly Alcance $alcance,
    ) {
    }

    /**
     * Lista de envíos del alcance del usuario (RF-047).
     *
     * Tres decisiones que se ven en el código y no en la vista:
     *
     * - **El alcance va en la consulta** (`forUser`), no en un filtro de la
     *   pantalla. Un revisor nunca recibe filas de establecimientos ajenos.
     * - **Los años salen de los datos**, no de una lista fija: el portal nace
     *   con un año y va a acumular varios.
     * - **El orden es explícito.** «Por período» es el valor por defecto porque
     *   es como trabaja la Unidad; los otros dos órdenes existen para revisar
     *   por territorio o por estado.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Envio::class);

        $usuario = $request->user();

        $filtros = $request->only(['anio', 'mes', 'comuna', 'establecimiento_id', 'estado', 'plazo', 'orden', 'q']);

        $consulta = Envio::query()
            ->forUser($usuario)
            ->with(['establecimiento', 'archivosVigentes.serie'])
            ->join('establecimientos', 'establecimientos.id', '=', 'envios.establecimiento_id')
            ->select('envios.*');

        if (! empty($filtros['anio'])) {
            $consulta->where('envios.anio', (int) $filtros['anio']);
        }

        if (! empty($filtros['mes'])) {
            $consulta->where('envios.mes', (int) $filtros['mes']);
        }

        if (! empty($filtros['comuna'])) {
            $consulta->where('establecimientos.comuna', $filtros['comuna']);
        }

        if (! empty($filtros['establecimiento_id'])) {
            $consulta->where('envios.establecimiento_id', (int) $filtros['establecimiento_id']);
        }

        if (! empty($filtros['estado'])) {
            $consulta->where('envios.estado', $filtros['estado']);
        }

        if (($filtros['plazo'] ?? '') === 'fuera') {
            $consulta->where('envios.fuera_de_plazo', true);
        } elseif (($filtros['plazo'] ?? '') === 'en_plazo') {
            $consulta->where('envios.fuera_de_plazo', false);
        } elseif (($filtros['plazo'] ?? '') === 'apertura') {
            $consulta->whereNotNull('envios.apertura_id');
        }

        if (! empty($filtros['q'])) {
            $texto = trim((string) $filtros['q']);

            $consulta->where(function ($q) use ($texto) {
                $q->where('establecimientos.codigo', 'like', "%{$texto}%")
                    ->orWhere('establecimientos.nombre', 'like', "%{$texto}%");
            });
        }

        $this->ordenar($consulta, (string) ($filtros['orden'] ?? 'periodo'));

        return view('envios.index', [
            'envios'       => $consulta->paginate(25)->withQueryString(),
            'filtros'      => $filtros,
            'establecimientos' => $this->alcance->establecimientos($usuario),
            'periodos'     => Periodo::where('estado', '!=', Periodo::CERRADO)
                ->orderByDesc('anio')->orderByDesc('mes')->get(),
            'puedeCrear'   => $usuario->hasRole('admin') || $usuario->establecimientos()->exists(),
            'anios'        => $this->aniosDisponibles($usuario),
            'comunas'      => $this->comunasDisponibles($usuario),
            'sinAsignacion' => $this->alcance->sinAsignacion($usuario),
        ]);
    }

    /**
     * Ordena según el criterio pedido. Nunca deja el orden al azar de MySQL:
     * sin `orderBy` explícito, la paginación puede repetir o saltar filas.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Envio>  $consulta
     */
    private function ordenar($consulta, string $orden): void
    {
        match ($orden) {
            'comuna' => $consulta
                ->orderBy('establecimientos.comuna')
                ->orderBy('establecimientos.nombre')
                ->orderByDesc('envios.anio')
                ->orderByDesc('envios.mes'),

            'establecimiento' => $consulta
                ->orderBy('establecimientos.nombre')
                ->orderByDesc('envios.anio')
                ->orderByDesc('envios.mes'),

            'estado' => $consulta
                ->orderBy('envios.estado')
                ->orderByDesc('envios.anio')
                ->orderByDesc('envios.mes'),

            default => $consulta
                ->orderByDesc('envios.anio')
                ->orderByDesc('envios.mes')
                ->orderBy('establecimientos.comuna')
                ->orderBy('establecimientos.nombre'),
        };

        $consulta->orderBy('envios.id');
    }

    /**
     * Años con envíos en el alcance, más el actual.
     *
     * @return list<int>
     */
    private function aniosDisponibles(User $usuario): array
    {
        $anios = Envio::query()
            ->forUser($usuario)
            ->distinct()
            ->orderByDesc('anio')
            ->pluck('anio')
            ->map(fn ($anio) => (int) $anio)
            ->all();

        $actual = (int) now()->year;

        if (! in_array($actual, $anios, true)) {
            array_unshift($anios, $actual);
        }

        return $anios;
    }

    /**
     * Comunas del alcance, para el filtro.
     *
     * @return list<string>
     */
    private function comunasDisponibles(User $usuario): array
    {
        $consulta = Establecimiento::query()
            ->where('activo', true)
            ->whereNotNull('comuna')
            ->where('comuna', '!=', '');

        // El desplegable es del alcance, como el resto de la pantalla: listar
        // todas las comunas del Servicio entrega al revisor el territorio que
        // no le asignaron (R-ALC-01, principio 33).
        return $this->alcance
            ->aplicar($consulta, $usuario)
            ->distinct()
            ->orderBy('comuna')
            ->pluck('comuna')
            ->all();
    }

    public function store(CrearEnvioRequest $request): RedirectResponse
    {
        [$anio, $mes] = $request->periodo();

        $establecimiento = Establecimiento::findOrFail($request->integer('establecimiento_id'));

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

        // Series exigidas del período. La regla vive en MatrizSeries y se
        // evalúa con el cierre del período del envío, igual que V11 y que la
        // aprobación: antes esta pantalla listaba series vencidas.
        $exigidas = $this->matriz->exigidas(
            $envio->establecimiento,
            $this->matriz->cierreDelPeriodo((int) $envio->anio, (int) $envio->mes)
        );

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
    public function subir(SubirArchivoRequest $request, Envio $envio): RedirectResponse
    {
        $serie = Serie::findOrFail($request->integer('serie_id'));

        try {
            $registro = $this->envios->subirVersion($envio, $serie, $request->file('archivo'), $request->user());
        } catch (ArchivoRechazadoException|EnvioInvalidoException $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        } catch (ValidacionOcupadaException $e) {
            // El archivo quedó guardado; solo falta validarlo. Se avisa sin
            // perder la subida, que es lo que costó tiempo al usuario.
            return back()->withErrors(['archivo' => $e->getMessage().' El archivo quedó guardado: revalide desde el detalle.']);
        }

        $this->envios->revalidar($envio);
        $this->estados->recalcular($envio->fresh());

        $registro->refresh();

        // El archivo se guarda siempre (la validación corre después), pero si
        // la cadena dejó bloqueantes el mensaje no puede decir «validado»: el
        // usuario tiene que saber que ese archivo no sirve y por qué.
        $bloqueantes = $registro->validaciones()->where('severidad', 'bloqueante')->count();

        if ($bloqueantes > 0) {
            return redirect()
                ->route('envios.show', $envio)
                ->withErrors(['archivo' => "Archivo de la serie {$serie->codigo} guardado, pero la validación encontró {$bloqueantes} bloqueante(s). Revise los hallazgos antes de enviar a revisión."]);
        }

        return redirect()
            ->route('envios.show', $envio)
            ->with('ok', "Archivo de la serie {$serie->codigo} subido y validado.");
    }

    /**
     * Cambia el estado del envío (enviar, observar, aprobar).
     */
    public function transicion(Request $request, Envio $envio): RedirectResponse
    {
        $datos = $request->validate([
            'accion'     => ['required', 'string', 'in:enviar,observar,aprobar'],
            'motivo'     => ['nullable', 'string', 'max:500'],
        ]);

        $destino = match ($datos['accion']) {
            'enviar'   => Envio::EN_REVISION,
            'observar' => Envio::OBSERVADO,
            'aprobar'  => Envio::APROBADO,
        };

        $habilidad = match ($datos['accion']) {
            'enviar'   => 'enviar',
            'observar' => 'observar',
            'aprobar'  => 'aprobar',
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
