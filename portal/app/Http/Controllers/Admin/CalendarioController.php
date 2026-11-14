<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feriado;
use App\Models\Periodo;
use App\Services\CalendarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Calendario anual de plazos (D-029).
 *
 * Muestra, para cada mes del año, la fecha límite calculada —fin del quinto
 * día hábil del mes siguiente— y permite crear o actualizar los períodos con
 * esa fecha. Los feriados del año se listan al lado para poder revisarlos.
 */
class CalendarioController extends Controller
{
    public function __construct(private readonly CalendarioService $calendario)
    {
    }

    public function index(Request $request): View
    {
        $anio = $request->integer('anio') ?: (int) now()->year;
        $anio = max(2010, min(2100, $anio));

        $meses = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $meses[] = [
                'mes'     => $mes,
                'fecha'   => $this->calendario->fechaLimite($anio, $mes),
                'periodo' => Periodo::where('anio', $anio)->where('mes', $mes)->first(),
            ];
        }

        return view('admin.calendario.index', [
            'anio'               => $anio,
            'meses'              => $meses,
            'feriados'           => Feriado::whereYear('fecha', $anio)->orderBy('fecha')->get(),
            'feriadosDelAnio'    => $this->calendario->hayFeriados($anio),
            'feriadosSiguiente'  => $this->calendario->hayFeriados($anio + 1),
        ]);
    }

    /**
     * Crea los períodos que faltan del año y actualiza la fecha de los que no
     * tienen override manual. No toca el estado de los existentes.
     */
    public function aplicar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'min:2010', 'max:2100'],
        ]);

        $anio = (int) $datos['anio'];
        $creados = 0;
        $actualizados = 0;

        DB::transaction(function () use ($anio, &$creados, &$actualizados) {
            for ($mes = 1; $mes <= 12; $mes++) {
                $periodo = Periodo::firstOrNew(['anio' => $anio, 'mes' => $mes]);

                if (! $periodo->exists) {
                    $periodo->estado = Periodo::ABIERTO;
                    $periodo->fecha_limite = $this->calendario->fechaLimite($anio, $mes)->toDateString();
                    $periodo->save();
                    $creados++;

                    continue;
                }

                if ($periodo->fecha_limite_manual) {
                    continue;
                }

                $periodo->fecha_limite = $this->calendario->fechaLimite($anio, $mes)->toDateString();
                $periodo->save();
                $actualizados++;
            }
        });

        return redirect()
            ->route('admin.calendario.index', ['anio' => $anio])
            ->with('ok', "Calendario aplicado: {$creados} período(s) creado(s) y {$actualizados} fecha(s) actualizada(s).");
    }
}
