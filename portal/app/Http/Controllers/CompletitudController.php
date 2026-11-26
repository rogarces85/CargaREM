<?php

namespace App\Http\Controllers;

use App\Dominio\Alcance;
use App\Models\Periodo;
use App\Services\CompletitudService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Matriz de completitud del Servicio de Salud (T-F4-03).
 *
 * Responde una pregunta concreta: qué establecimientos entregaron lo que se
 * les exige, en qué estado, y qué falta. El encargado y el Equipo REM ven lo
 * suyo asignado; el admin ve todo el Servicio. En v3.0 no hay exportación: la
 * pantalla es la única salida.
 */
class CompletitudController extends Controller
{
    public function __construct(
        private readonly CompletitudService $completitud,
        private readonly Alcance $alcance,
    ) {
    }

    public function index(Request $request): View
    {
        $usuario = $request->user();

        abort_unless($usuario->can('envios.ver.propio') || $usuario->can('envios.ver.ss'), 403);

        [$anio, $mes] = $this->periodo($request);

        $matriz = $this->completitud->matriz(
            $anio,
            $mes,
            $usuario,
            $request->filled('establecimiento_id') ? $request->integer('establecimiento_id') : null
        );

        return view('completitud.index', [
            'matriz'           => $matriz,
            'anio'             => $anio,
            'mes'              => $mes,
            'establecimientos' => $this->establecimientosDelAlcance($request),
            'periodos'         => Periodo::orderByDesc('anio')->orderByDesc('mes')->limit(24)->get(),
            'filtros'          => $request->only(['anio', 'mes', 'establecimiento_id']),
        ]);
    }

    /** @return array{0: int, 1: int} */
    private function periodo(Request $request): array
    {
        $anio = $request->filled('anio') ? $request->integer('anio') : (int) now()->year;
        $mes = $request->filled('mes') ? $request->integer('mes') : (int) now()->month;

        // El período puede no existir en el catálogo; la matriz igual se arma
        // y muestra todo en gris, que es información útil.
        $mes = max(1, min(12, $mes));

        return [$anio, $mes];
    }

    /** Establecimientos que el usuario puede filtrar (R-ALC-01). */
    private function establecimientosDelAlcance(Request $request)
    {
        return $this->alcance->establecimientos($request->user());
    }
}
