<?php

namespace App\Http\Controllers;

use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de los archivos subidos (T-F2-08).
 *
 * El archivo vive fuera de `public/`; solo sale por aquí, con la policy por
 * delante y con `nosniff` para que el navegador no intente interpretarlo
 * (principio 19).
 */
class ArchivoController extends Controller
{
    public function descargar(Envio $envio, EnvioArchivo $archivo): StreamedResponse
    {
        // El archivo tiene que ser de este envío: sin esta comprobación, una
        // URL con dos ids válidos serviría el archivo de otro establecimiento.
        abort_unless($archivo->envio_id === $envio->id, 404);

        $this->authorize('download', $archivo);

        $disco = Storage::disk($archivo->disco);

        abort_unless($disco->exists($archivo->ruta), 404, 'El archivo ya no está en el almacén.');

        AuditoriaService::registrarSobre('archivo.descargado', $archivo, [
            'envio' => $envio->id,
            'serie' => $archivo->serie_id,
        ]);

        return $disco->download($archivo->ruta, $archivo->nombre_original, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
