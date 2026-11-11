<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\Serie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Matriz establecimiento × serie (T-F1-23).
 *
 * Decide qué series se le exigen a cada establecimiento y desde cuándo.
 * Es lo que alimenta la validación de completitud V11 (R-SER-01 a R-SER-07).
 */
class MatrizController extends Controller
{
    public function index(): View
    {
        $establecimientos = Establecimiento::withCount(['series' => function ($q) {
            $q->where('obligatoria', true);
        }])->orderBy('nombre')->paginate(25);

        return view('admin.matriz.index', [
            'establecimientos' => $establecimientos,
        ]);
    }

    public function edit(Establecimiento $establecimiento): View
    {
        $series = Serie::orderBy('orden')->orderBy('codigo')->get();

        $actuales = DB::table('establecimiento_serie')
            ->where('establecimiento_id', $establecimiento->id)
            ->get()
            ->keyBy('serie_id');

        return view('admin.matriz.form', [
            'establecimiento' => $establecimiento,
            'series'          => $series,
            'actuales'        => $actuales,
        ]);
    }

    public function update(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $datos = $request->validate([
            'series'                   => ['array'],
            'series.*.obligatoria'     => ['nullable', 'boolean'],
            'series.*.vigente_desde'   => ['nullable', 'date'],
            'series.*.vigente_hasta'   => ['nullable', 'date', 'after_or_equal:series.*.vigente_desde'],
        ]);

        DB::transaction(function () use ($establecimiento, $datos) {
            $establecimiento->series()->detach();

            foreach ($datos['series'] ?? [] as $serieId => $valores) {
                if (empty($valores['obligatoria'])) {
                    continue;
                }

                $establecimiento->series()->attach($serieId, [
                    'obligatoria'   => true,
                    'vigente_desde' => $valores['vigente_desde'] ?? null,
                    'vigente_hasta' => $valores['vigente_hasta'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('admin.matriz.index')
            ->with('ok', 'Matriz actualizada para '.$establecimiento->nombre.'.');
    }
}
