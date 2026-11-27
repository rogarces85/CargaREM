<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feriado;
use App\Models\Periodo;
use App\Services\CalendarioService;
use App\Support\Plazos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Calendario anual de plazos (D-029, RF-046).
 *
 * Muestra, para cada mes del año, la fecha límite calculada —fin del quinto
 * día hábil del mes siguiente— y permite crear o actualizar los períodos con
 * esa fecha. Los feriados del año se listan al lado para poder revisarlos.
 *
 * La generación la hace `App\Support\Plazos`, el mismo servicio que usan los
 * comandos: antes esta lógica estaba copiada aquí y en la consola.
 */
class CalendarioController extends Controller
{
    public function __construct(
        private readonly CalendarioService $calendario,
        private readonly Plazos $plazos,
    ) {
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
            'diferencias'        => $this->plazos->diferencias($anio),
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
        $resultado = $this->plazos->generar($anio);

        return redirect()
            ->route('admin.calendario.index', ['anio' => $anio])
            ->with('ok', "Calendario aplicado: {$resultado['creados']} período(s) creado(s) y {$resultado['actualizados']} fecha(s) actualizada(s).");
    }
}
