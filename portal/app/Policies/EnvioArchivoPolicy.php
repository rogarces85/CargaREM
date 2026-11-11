<?php

namespace App\Policies;

use App\Models\EnvioArchivo;
use App\Models\User;

/**
 * Autorización de archivos (T-F1-15).
 *
 * La descarga se verifica contra el envío: si no se ve el envío, no se
 * descarga el archivo (RN-ENV-9, principio 19).
 */
class EnvioArchivoPolicy
{
    public function view(User $usuario, EnvioArchivo $archivo): bool
    {
        if (! $usuario->can('archivos.descargar') && ! $usuario->can('envios.ver.ss') && ! $usuario->can('envios.ver.propio')) {
            return false;
        }

        return EnvioArchivo::query()->forUser($usuario)->whereKey($archivo->getKey())->exists();
    }

    public function download(User $usuario, EnvioArchivo $archivo): bool
    {
        return $usuario->can('archivos.descargar') && $this->view($usuario, $archivo);
    }

    public function delete(User $usuario, EnvioArchivo $archivo): bool
    {
        // Un archivo se reemplaza con una versión nueva; no se borra.
        return false;
    }
}
