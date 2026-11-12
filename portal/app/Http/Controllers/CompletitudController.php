<?php

namespace App\Http\Controllers;

use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Services\CompletitudService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Matriz de completitud del Servicio de Salud (T-F4-03).
 *
 * Responde una pregunta concreta: qué establecimientos entregaron lo que se
 * les exige, en qué estado, y qué falta. El encargado ve lo suyo; el revisor
 * y la consulta, todo su Servicio de Salud.
 */
class CompletitudController extends Controller
{
    public function __construct(private readonly CompletitudService $completitud)
    {
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

    /**
     * Exporta la misma matriz (T-F4-02).
     */
    public function exportar(Request $request): StreamedResponse
    {
        $usuario = $request->user();

        abort_unless($usuario->can('reportes.descargar'), 403);

        [$anio, $mes] = $this->periodo($request);

        $matriz = $this->completitud->matriz(
            $anio,
            $mes,
            $usuario,
            $request->filled('establecimiento_id') ? $request->integer('establecimiento_id') : null
        );

        $nombreBase = sprintf('completitud-%d-%02d', $anio, $mes);

        if ($request->query('formato') === 'xlsx') {
            $libro = $this->completitud->aExcel($matriz, $anio, $mes);

            return response()->streamDownload(function () use ($libro) {
                (new Xlsx($libro))->save('php://output');
            }, $nombreBase.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $csv = $this->completitud->aCsv($matriz, $anio, $mes);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $nombreBase.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
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

    /** Establecimientos que el usuario puede filtrar. */
    private function establecimientosDelAlcance(Request $request)
    {
        $usuario = $request->user();

        if ($usuario->hasRole('admin')) {
            return Establecimiento::where('activo', true)->orderBy('nombre')->get();
        }

        if ($usuario->servicio_id !== null) {
            return Establecimiento::where('activo', true)
                ->where('servicio_id', $usuario->servicio_id)
                ->orderBy('nombre')
                ->get();
        }

        return $usuario->establecimientos()->where('activo', true)->orderBy('nombre')->get();
    }
}
