<?php

namespace App\Observers;

use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Auditoría automática del CRUD de catálogos y usuarios (MOD-08).
 *
 * Los flujos del envío ya escriben su propia auditoría en los servicios; este
 * observer cubre lo que antes no dejaba rastro: altas, cambios y bajas de
 * usuarios, servicios, establecimientos, series, períodos y plantillas.
 *
 * Solo registra lo que hizo una persona con sesión abierta: la siembra del
 * catálogo no es una acción humana y llenaría la bitácora de ruido.
 *
 * No se registra el contenido de los campos, solo qué atributos cambiaron.
 */
class AuditoriaCatalogoObserver
{
    public function created(Model $modelo): void
    {
        if (! Auth::check()) {
            return;
        }

        AuditoriaService::registrarSobre('catalogo.creado', $modelo, [
            'tabla' => $modelo->getTable(),
        ]);
    }

    public function updated(Model $modelo): void
    {
        if (! Auth::check()) {
            return;
        }

        AuditoriaService::registrarSobre('catalogo.actualizado', $modelo, [
            'tabla'   => $modelo->getTable(),
            'campos'  => array_keys($modelo->getChanges()),
        ]);
    }

    public function deleted(Model $modelo): void
    {
        if (! Auth::check()) {
            return;
        }

        AuditoriaService::registrarSobre('catalogo.eliminado', $modelo, [
            'tabla' => $modelo->getTable(),
        ]);
    }
}
