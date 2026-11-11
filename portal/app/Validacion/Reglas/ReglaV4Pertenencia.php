<?php

namespace App\Validacion\Reglas;

use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;

/**
 * V4 — pertenencia al Servicio de Salud (ENV-015).
 *
 * Se revalida quién subió el archivo: la autorización se comprobó al subir,
 * pero un cambio de asignación posterior dejaría en el sistema un archivo
 * fuera de alcance. Es defensa en profundidad, no desconfianza del controlador.
 */
final class ReglaV4Pertenencia implements ReglaValidacion
{
    public function etapa(): string
    {
        return 'V4';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        $usuario = $contexto->archivo->subidoPor;

        if ($usuario === null) {
            return [new Hallazgo(
                'V4', 'ENV-015', Severidad::BLOQUEANTE,
                'No se puede determinar quién subió el archivo.',
                null, null, null, 'R-V4-01'
            )];
        }

        $establecimiento = $contexto->envio->establecimiento;
        $servicioDelEnvio = $establecimiento->servicio_id;

        if ($usuario->hasRole('admin')) {
            return [];
        }

        $porServicio = $usuario->servicio_id !== null && (int) $usuario->servicio_id === (int) $servicioDelEnvio;

        $porAsignacion = $usuario->establecimientos()
            ->whereKey($establecimiento->getKey())
            ->exists();

        if ($porServicio || $porAsignacion) {
            return [];
        }

        return [new Hallazgo(
            'V4', 'ENV-015', Severidad::BLOQUEANTE,
            'El establecimiento no pertenece al Servicio de Salud del usuario que subió el archivo.',
            null, null, (string) $establecimiento->codigo, 'R-V4-02'
        )];
    }
}