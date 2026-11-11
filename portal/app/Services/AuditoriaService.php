<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Registro de auditoría (T-F1-26).
 *
 * Se escribe dentro de la transacción en curso: si la operación falla, la
 * bitácora no miente diciendo que ocurrió. Es lo contrario de un log de texto.
 */
class AuditoriaService
{
    /**
     * Deja una fila en la bitácora.
     *
     * @param  array<string, mixed>  $datos  identificadores y hechos; nunca contenido de celdas
     */
    public static function registrar(
        string $evento,
        ?string $entidad = null,
        string|int|null $entidadId = null,
        array $datos = [],
    ): Auditoria {
        $peticion = request();

        return Auditoria::create([
            'usuario_id' => Auth::id(),
            'evento'     => $evento,
            'entidad'    => $entidad,
            'entidad_id' => $entidadId !== null ? (string) $entidadId : null,
            'datos'      => $datos === [] ? null : $datos,
            'ip'         => $peticion->ip(),
            'user_agent' => mb_substr((string) $peticion->userAgent(), 0, 255),
        ]);
    }

    /**
     * Alias para quien prefiera leerlo como acción de la entidad.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function registrarSobre(string $evento, object $modelo, array $datos = []): Auditoria
    {
        return self::registrar(
            $evento,
            class_basename($modelo),
            $modelo->getKey(),
            $datos
        );
    }
}
