<?php

namespace App\Console\Commands;

use App\Support\Plazos;
use Illuminate\Console\Command;

/**
 * Genera los períodos de un año (RF-046).
 *
 * Idempotente: se puede correr todas las veces que haga falta. Es el comando
 * que se programa para diciembre (el año siguiente) y para cada mes, y también
 * el que se ejecuta a mano después de cargar un feriado que corre una fecha.
 */
class GenerarPeriodos extends Command
{
    protected $signature = 'rem:periodos:generar
                            {anio? : Año a generar (por defecto el actual)}
                            {--siguiente : Genera además el año siguiente}';

    protected $description = 'Crea los períodos del año con su fecha límite calculada, sin tocar los estados.';

    public function handle(Plazos $plazos): int
    {
        $anio = (int) ($this->argument('anio') ?: now()->year);

        $anios = $this->option('siguiente') ? [$anio, $anio + 1] : [$anio];

        foreach ($anios as $objetivo) {
            $resultado = $plazos->generar($objetivo);

            $this->info(sprintf(
                '%d: %d período(s) creado(s), %d fecha(s) actualizada(s).',
                $objetivo,
                $resultado['creados'],
                $resultado['actualizados']
            ));
        }

        return self::SUCCESS;
    }
}
