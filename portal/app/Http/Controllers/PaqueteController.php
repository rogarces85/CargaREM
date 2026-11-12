<?php

namespace App\Http\Controllers;

use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Services\AuditoriaService;
use App\Services\PaqueteService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga del paquete de un envío aprobado (T-F5-03, OP-11).
 *
 * El ZIP se arma al vuelo, se entrega y se borra: no se guarda una copia por
 * descarga, que con el tiempo serían cientos de paquetes obsoletos en disco.
 */
class PaqueteController extends Controller
{
    public function descargar(Envio $envio, PaqueteService $paquetes): StreamedResponse
    {
        $this->authorize('paquete', $envio);

        try {
            $ruta = $paquetes->generar($envio);
        } catch (EnvioInvalidoException $e) {
            abort(409, $e->getMessage());
        }

        $nombre = sprintf(
            'paquete-%s-%02d-%d.zip',
            $envio->establecimiento->codigo,
            $envio->mes,
            $envio->anio
        );

        AuditoriaService::registrarSobre('envio.paquete', $envio, [
            'archivos' => $envio->archivosVigentes()->count(),
        ]);

        return response()->streamDownload(function () use ($ruta) {
            readfile($ruta);
            @unlink($ruta);
        }, $nombre, [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
