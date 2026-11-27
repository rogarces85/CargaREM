<?php

namespace App\Console\Commands;

use App\Services\ValidadorEstadisticaService;
use Illuminate\Console\Command;

/**
 * Limpia los archivos del Validador con más de 90 días (RF-040.1).
 *
 * Borra el Excel, no el registro: el uso del módulo se conserva para el
 * informe del administrador. El archivo es un insumo de paso; la fila es el
 * dato de gestión.
 */
class LimpiarValidador extends Command
{
    protected $signature = 'rem:validador:limpiar';

    protected $description = 'Borra los archivos del Validador con más de la retención configurada.';

    public function handle(ValidadorEstadisticaService $validador): int
    {
        $borrados = $validador->limpiarAntiguos();

        $this->info("Archivos borrados: {$borrados}. Los registros de uso se conservan.");

        return self::SUCCESS;
    }
}
