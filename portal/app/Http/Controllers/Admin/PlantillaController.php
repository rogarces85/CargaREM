<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plantilla;
use Illuminate\View\View;

/**
 * Consulta de plantillas por período y serie.
 *
 * No se editan a mano: vienen del catálogo del Minsal y se reemplazan
 * importando un snapshot nuevo.
 */
class PlantillaController extends Controller
{
    public function index(): View
    {
        return view('admin.plantillas.index', [
            'plantillas' => Plantilla::with('serie')
                ->orderByDesc('anio')
                ->orderByDesc('mes')
                ->orderBy('serie_id')
                ->paginate(50),
        ]);
    }
}