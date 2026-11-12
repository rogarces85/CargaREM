<?php

namespace App\Services;

use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Observacion;
use App\Models\User;

/**
 * Observaciones del revisor (T-F3-01, OP-08).
 *
 * Una observación puede ser del envío completo o de un archivo concreto. En
 * el primer caso frena la aprobación; en el segundo, el encargado sabe qué
 * serie corregir.
 */
class ObservacionService
{
    public function registrar(
        Envio $envio,
        User $autor,
        string $texto,
        ?EnvioArchivo $archivo = null,
    ): Observacion {
        $texto = trim($texto);

        if ($texto === '') {
            throw EnvioInvalidoException::observacionVacia();
        }

        if (mb_strlen($texto) > 2000) {
            throw EnvioInvalidoException::observacionLarga();
        }

        if ($archivo !== null && $archivo->envio_id !== $envio->id) {
            throw EnvioInvalidoException::observacionDeOtroEnvio();
        }

        $observacion = $envio->observaciones()->create([
            'envio_archivo_id' => $archivo?->id,
            'autor_id'         => $autor->id,
            'texto'            => $texto,
            'resuelta'         => false,
        ]);

        AuditoriaService::registrarSobre('envio.observado', $envio, [
            'observacion' => $observacion->id,
            'serie'       => $archivo?->serie_id,
        ]);

        return $observacion->load('autor');
    }

    /**
     * Marca como resueltas las observaciones de una serie (T-F3-02).
     *
     * Se llama al subir una versión nueva: la observación hablaba del archivo
     * anterior, y ese archivo ya no es el vigente.
     *
     * @return int cuántas se resolvieron
     */
    public function resolverPorSerie(Envio $envio, int $serieId): int
    {
        $ids = $envio->archivos()->where('serie_id', $serieId)->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $cantidad = $envio->observaciones()
            ->where('resuelta', false)
            ->whereIn('envio_archivo_id', $ids)
            ->update([
                'resuelta'    => true,
                'resuelta_en' => now(),
            ]);

        if ($cantidad > 0) {
            AuditoriaService::registrarSobre('envio.observaciones.resueltas', $envio, [
                'serie'     => $serieId,
                'cantidad'  => $cantidad,
            ]);
        }

        return $cantidad;
    }

    /** Observaciones abiertas del envío, con o sin archivo. */
    public function abiertas(Envio $envio): int
    {
        return $envio->observaciones()->where('resuelta', false)->count();
    }
}
