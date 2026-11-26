<?php

namespace App\Http\Controllers;

use App\Services\PanelService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel de inicio (RF-048).
 *
 * Era un closure en las rutas que devolvía una vista estática. Ahora resuelve
 * los indicadores del rol con `PanelService`, que aplica el alcance por
 * establecimiento igual que la bandeja.
 */
class PanelController extends Controller
{
    public function __construct(private readonly PanelService $panel)
    {
    }

    public function index(Request $request): View
    {
        return view('panel', [
            'panel' => $this->panel->para($request->user()),
        ]);
    }
}
