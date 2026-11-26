<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Campana de notificaciones (MOD-09, RF-051).
 *
 * El aviso vive solo en la tabla `notifications`: no hay correo. La campana de
 * la cabecera muestra los últimos diez; esta página es el historial completo,
 * con filtro por tipo y «marcar todas como leídas».
 */
class NotificacionController extends Controller
{
    /** Tipos que se pueden filtrar. */
    public const TIPOS = [
        'subida'    => 'Subidas',
        'revision'  => 'Revisión',
        'apertura'  => 'Aperturas de plazo',
        'asignacion' => 'Asignaciones',
        'validador' => 'Validador',
    ];

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $tipo = (string) $request->query('tipo', '');

        $consulta = $usuario->notifications()->latest();

        if ($tipo !== '' && array_key_exists($tipo, self::TIPOS)) {
            $consulta->where('data->tipo', $tipo);
        }

        return view('notificaciones.index', [
            'avisos' => $consulta->paginate(20)->withQueryString(),
            'tipo'   => $tipo,
            'tipos'  => self::TIPOS,
            'sinLeer' => $usuario->unreadNotifications()->count(),
        ]);
    }

    /**
     * Marca una notificación como leída y abre el envío al que se refiere.
     */
    public function leer(Request $request, string $notificacion): RedirectResponse
    {
        $fila = $request->user()->notifications()->findOrFail($notificacion);

        $fila->markAsRead();

        // Los avisos de gestión llevan su propia URL; los de envío, el
        // `envio_id`. Se prefieren los primeros y se cae al envío.
        $url = $fila->data['url'] ?? null;

        if (is_string($url) && $url !== '') {
            return redirect()->to($url);
        }

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
