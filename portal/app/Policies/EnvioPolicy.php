<?php

namespace App\Policies;

use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\User;

/**
 * Autorización de envíos (T-F1-15, MOD-05).
 *
 * Dos capas que no se sustituyen: el permiso del rol dice *qué acción* puede
 * hacer; el alcance dice *sobre qué establecimiento*. Un revisor con
 * `envios.aprobar` no puede aprobar un envío fuera de su Servicio de Salud.
 */
class EnvioPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('envios.ver.propio') || $usuario->can('envios.ver.ss');
    }

    public function view(User $usuario, Envio $envio): bool
    {
        if (! $this->viewAny($usuario)) {
            return false;
        }

        return Envio::query()->forUser($usuario)->whereKey($envio->getKey())->exists();
    }

    /**
     * Crear un envío para un establecimiento.
     *
     * Se pasa el establecimiento porque el permiso solo no alcanza: un
     * encargado puede crear, pero solo en los suyos.
     */
    public function create(User $usuario, Establecimiento $establecimiento): bool
    {
        if (! $usuario->can('envios.crear')) {
            return false;
        }

        if ($usuario->hasRole('admin')) {
            return true;
        }

        return $usuario->establecimientos()->whereKey($establecimiento->getKey())->exists();
    }

    public function update(User $usuario, Envio $envio): bool
    {
        return $usuario->can('envios.crear')
            && $envio->estaAbierto()
            && $this->view($usuario, $envio);
    }

    /**
     * Enviar a revisión.
     *
     * Los estados son los mismos desde los que `EstadoEnvioService::PERMITIDAS`
     * admite la transición. Cuando la policy ofrecía `con_errores`, la vista
     * pintaba un botón que el servicio rechazaba siempre: un envío con
     * bloqueantes no se manda, se corrige.
     *
     * Tampoco se manda un envío vacío: sin archivos no hay nada que revisar.
     */
    public function enviar(User $usuario, Envio $envio): bool
    {
        return $usuario->can('envios.enviar')
            && in_array($envio->estado, [Envio::RECIBIDO, Envio::VALIDADO], true)
            && $envio->archivosVigentes()->exists()
            && $this->view($usuario, $envio);
    }

    public function observar(User $usuario, Envio $envio): bool
    {
        return $usuario->can('envios.observar')
            && $envio->estado === Envio::EN_REVISION
            && $this->view($usuario, $envio);
    }

    /**
     * RN-ENV-7 (segregación): el revisor no aprueba un envío del
     * establecimiento donde él mismo es encargado. Evita que la misma persona
     * cargue y apruebe.
     */
    public function aprobar(User $usuario, Envio $envio): bool
    {
        if (! $usuario->can('envios.aprobar') || $envio->estado !== Envio::EN_REVISION) {
            return false;
        }

        if (! $this->view($usuario, $envio)) {
            return false;
        }

        if ($usuario->hasRole('encargado')
            && $usuario->establecimientos()->whereKey($envio->establecimiento_id)->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Reabrir un envío aprobado (RF-044.2, D-043).
     *
     * Solo se reabre lo aprobado y solo con el permiso `envios.reabrir`, que en
     * la v3 tienen el Equipo REM y el administrador. El envío tiene que estar
     * en el alcance de quien reabre: un revisor no reabre lo que no revisa.
     */
    public function reabrir(User $usuario, Envio $envio): bool
    {
        return $usuario->can('envios.reabrir')
            && $envio->estado === Envio::APROBADO
            && $this->view($usuario, $envio);
    }

    public function delete(User $usuario, Envio $envio): bool
    {
        // Un envío no se borra: queda auditado.
        return false;
    }
}
