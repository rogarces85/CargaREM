<?php

namespace App\Console\Commands;

use App\Services\AperturaService;
use Illuminate\Console\Command;

/**
 * Cierra las aperturas vencidas (RF-043.4, R-APL-05).
 *
 * **No es necesario para que el sistema funcione.** Una apertura vencida ya no
 * habilita nada porque `vigente_hasta` se comprueba en cada carga. El comando
 * solo deja el estado explícito para que la bandeja del administrador no
 * muestre como «aprobada» algo que ya no abre nada.
 */
class CerrarAperturas extends Command
{
    protected $signature = 'rem:aperturas:cerrar';

    protected $description = 'Marca como anuladas las aperturas de plazo cuya vigencia terminó.';

    public function handle(AperturaService $aperturas): int
    {
        $cerradas = $aperturas->cerrarVencidas();

        $this->info("Aperturas cerradas: {$cerradas}.");

        return self::SUCCESS;
    }
}
