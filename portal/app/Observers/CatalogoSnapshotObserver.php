<?php

namespace App\Observers;

use App\Models\CatalogoSnapshot;
use App\Services\AuditoriaService;

/**
 * Auditoría automática de snapshots de catálogo (T-F1-27).
 *
 * Importar o activar un catálogo cambia cómo se valida cada archivo: es de las
 * operaciones más sensibles del portal y queda registrada sin que el
 * controlador tenga que acordarse.
 */
class CatalogoSnapshotObserver
{
    public function created(CatalogoSnapshot $snapshot): void
    {
        AuditoriaService::registrarSobre('catalogo.importado', $snapshot, [
            'origen'      => $snapshot->origen,
            'diccionario' => $snapshot->filas_diccionario,
        ]);
    }

    public function updated(CatalogoSnapshot $snapshot): void
    {
        if (! $snapshot->wasChanged('activo')) {
            return;
        }

        AuditoriaService::registrarSobre(
            $snapshot->activo ? 'catalogo.activado' : 'catalogo.desactivado',
            $snapshot,
            ['origen' => $snapshot->origen]
        );
    }

    public function deleted(CatalogoSnapshot $snapshot): void
    {
        AuditoriaService::registrarSobre('catalogo.eliminado', $snapshot);
    }
}
