<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Campana de notificaciones (MOD-09 recortado).
 *
 * En v2.0 el aviso vive solo en la tabla `notifications`: no hay correo. Cada
 * usuario ve los suyos y los marca como leídos desde la cabecera.
 */
class NotificacionController extends Controller
{
    /**
     * Marca una notificación como leída y abre el envío al que se refiere.
     */
    public function leer(Request $request, string $notificacion): RedirectResponse
    {
        $fila = $request->user()->notifications()->findOrFail($notificacion);

        $fila->markAsRead();

        $envioId = $fila->data['envio_id'] ?? null;

        return $envioId !== null
            ? redirect()->route('envios.show', $envioId)
            : back();
    }

    public function leerTodas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('ok', 'Avisos marcados como leídos.');
    }
}
