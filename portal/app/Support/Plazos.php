<?php

namespace App\Support;

use App\Models\Periodo;
use App\Services\CalendarioService;
use Illuminate\Support\Facades\DB;

/**
 * Generación del calendario de plazos (RF-046, D-046).
 *
 * El plazo de cada período es el fin del quinto día hábil del mes siguiente
 * (D-029). Lo que la v3 agrega es que **los períodos se crean solos**: al pedir
 * un período que no existe, el alta del envío lo provisiona; y este servicio
 * completa un año entero desde el calendario del administrador, desde la consola
 * o desde una tarea programada.
 *
 * **Nada de esto cambia el estado de un período.** Crear no es cerrar: un
 * período nuevo nace abierto y el cierre sigue siendo una decisión humana
 * (R-CIE-04).
 *
 * La clase es el único lugar que sabe recorrer un año. Antes esa lógica estaba
 * copiada en el controlador del calendario y en el comando, y las dos copias ya
 * habían divergido en el mensaje que devolvían.
 */
final class Plazos
{
    public function __construct(private readonly CalendarioService $calendario)
    {
    }

    /**
     * Asegura los doce períodos del año.
     *
     * - Crea los que faltan, en estado `abierto`, con la fecha calculada.
     * - Actualiza la fecha de los que no tienen override manual.
     * - **No toca** el estado de los existentes ni una fecha fijada a mano.
     *
     * @return array{creados: int, actualizados: int}
     */
    public function generar(int $anio): array
    {
        $creados = 0;
        $actualizados = 0;

        DB::transaction(function () use ($anio, &$creados, &$actualizados) {
            for ($mes = 1; $mes <= 12; $mes++) {
                $periodo = Periodo::firstOrNew(['anio' => $anio, 'mes' => $mes]);
                $nuevo = ! $periodo->exists;

                if ($nuevo) {
                    $periodo->estado = Periodo::ABIERTO;
                }

                if (! $nuevo && $periodo->fecha_limite_manual) {
                    continue;
                }

                $calculada = $this->calendario->fechaLimite($anio, $mes)->toDateString();

                // Si la fecha ya es la correcta no se escribe: así el comando
                // dice la verdad («0 actualizados») y no ensucia `updated_at`
                // cada vez que corre la tarea programada.
                if ($periodo->fecha_limite?->toDateString() === $calculada) {
                    continue;
                }

                $periodo->fecha_limite = $calculada;
                $periodo->save();

                $nuevo ? $creados++ : $actualizados++;
            }
        });

        return ['creados' => $creados, 'actualizados' => $actualizados];
    }

    /**
     * Diferencias entre lo guardado y lo calculado.
     *
     * Solo mira los períodos **que existen**: un período que falta es asunto de
     * `generar()`, no de la verificación. Mezclarlos haría que el comando de
     * verificación fallara en enero por los once meses que todavía no se
     * crearon, que es ruido y no un error de calendario.
     *
     * @return list<array{periodo: string, guardada: ?string, calculada: string, manual: bool}>
     */
    public function diferencias(int $anio): array
    {
        $periodos = Periodo::where('anio', $anio)->get()->keyBy('mes');
        $diferencias = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $periodo = $periodos->get($mes);

            if ($periodo === null) {
                continue;
            }

            // Una fecha manual es una decisión, no un desfase: se informa y no
            // cuenta como diferencia.
            if ($periodo->fecha_limite_manual === true) {
                continue;
            }

            $calculada = $this->calendario->fechaLimite($anio, $mes)->toDateString();
            $guardada = $periodo->fecha_limite?->toDateString();

            if ($guardada === null || $guardada !== $calculada) {
                $diferencias[] = [
                    'periodo'  => sprintf('%02d/%d', $mes, $anio),
                    'guardada' => $guardada,
                    'calculada' => $calculada,
                    'manual'   => false,
                ];
            }
        }

        return $diferencias;
    }
}
