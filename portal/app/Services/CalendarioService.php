<?php

namespace App\Services;

use App\Models\Feriado;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Calendario de días hábiles del portal (D-029).
 *
 * Regla del plazo: el envío de un período se recibe hasta el final del
 * **quinto día hábil del mes siguiente**. Son días no hábiles los sábados, los
 * domingos y los feriados registrados en la tabla `feriados`.
 *
 * Si un año no tiene feriados cargados, el cómputo sigue con fines de semana:
 * el plazo queda más largo por 1 o 2 días. `hayFeriados()` permite avisarlo en
 * la pantalla de calendario y en el comando `rem:calendario`.
 */
class CalendarioService
{
    public const DIAS_HABILES_PLAZO = 5;

    /** Días que se recorren como máximo buscando el enésimo día hábil. */
    private const LIMITE_BUSQUEDA = 62;

    public function esHabil(CarbonInterface $fecha): bool
    {
        if ($fecha->isWeekend()) {
            return false;
        }

        return ! Feriado::whereDate('fecha', $fecha->toDateString())->exists();
    }

    /**
     * Enésimo día hábil de un mes.
     *
     * Si el mes no alcanza (caso imposible con un calendario real), continúa
     * en el mes siguiente en vez de fallar.
     */
    public function enesimoDiaHabil(int $anio, int $mes, int $n = self::DIAS_HABILES_PLAZO): Carbon
    {
        if ($n < 1) {
            throw new \InvalidArgumentException('El día hábil debe ser mayor que cero.');
        }

        $fecha = Carbon::create($anio, $mes, 1)->startOfDay();
        $contados = 0;

        for ($i = 0; $i < self::LIMITE_BUSQUEDA; $i++) {
            if ($this->esHabil($fecha)) {
                $contados++;

                if ($contados === $n) {
                    return $fecha;
                }
            }

            $fecha->addDay();
        }

        throw new \RuntimeException("No se encontraron {$n} días hábiles desde {$mes}/{$anio}.");
    }

    /**
     * Fecha límite de un período: fin del quinto día hábil del mes siguiente,
     * en la zona horaria de la aplicación (America/Santiago).
     */
    public function fechaLimite(int $anio, int $mes): Carbon
    {
        $siguiente = Carbon::create($anio, $mes, 1)->addMonthNoOverflow();

        return $this->enesimoDiaHabil($siguiente->year, $siguiente->month)->endOfDay();
    }

    /** ¿Hay feriados cargados para ese año? */
    public function hayFeriados(int $anio): bool
    {
        return Feriado::whereYear('fecha', $anio)->exists();
    }

    /**
     * Feriados que caen en un rango, para mostrarlos junto al calendario.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Feriado>
     */
    public function feriadosEntre(string $desde, string $hasta): \Illuminate\Database\Eloquent\Collection
    {
        return Feriado::whereBetween('fecha', [$desde, $hasta])->orderBy('fecha')->get();
    }
}
