<?php

namespace App\Validacion\Reglas;

use App\Excel\LectorHojas;
use App\Models\PlantillaHoja;
use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;
use Throwable;

/**
 * V5 — hojas del libro (ENV-016, ENV-017).
 *
 * Las hojas y sus rangos vienen de `Thojas`, que se define por año y serie
 * interna, no por mes. Falta una hoja obligatoria es bloqueante; una hoja de
 * más es advertencia, porque el validador de escritorio la ignora.
 */
final class ReglaV5Hojas implements ReglaValidacion
{
    public function __construct(private readonly LectorHojas $lector = new LectorHojas())
    {
    }

    public function etapa(): string
    {
        return 'V5';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        try {
            $presentes = $this->lector->nombres($contexto->rutaAbsoluta);
        } catch (Throwable $e) {
            return []; // V2 ya reportó el libro ilegible
        }

        $serieInterna = $contexto->plantilla?->serie_interna;

        if ($serieInterna === null) {
            return [new Hallazgo(
                'V5', 'ENV-016', Severidad::ADVERTENCIA,
                'El catálogo no define hojas para este período y serie: no se pudo verificar.',
                null, null, null, 'R-V5-01'
            )];
        }

        $esperadas = PlantillaHoja::query()
            ->where('anio', $contexto->anio())
            ->where('serie_interna', $serieInterna)
            ->get();

        if ($esperadas->isEmpty()) {
            return [];
        }

        $hallazgos = [];
        $normalizar = fn (string $n) => mb_strtoupper(trim($n));
        $presentesNormalizadas = array_map($normalizar, $presentes);

        foreach ($esperadas as $esperada) {
            if (! in_array($normalizar((string) $esperada->hoja), $presentesNormalizadas, true)) {
                $hallazgos[] = new Hallazgo(
                    'V5', 'ENV-016', Severidad::BLOQUEANTE,
                    "Falta la hoja obligatoria «{$esperada->hoja}».",
                    $esperada->hoja, null, null, 'R-V5-02'
                );
            }
        }

        $previstas = $esperadas->pluck('hoja')->map($normalizar)->all();

        foreach ($presentes as $hoja) {
            // «NOMBRE» es la hoja de cabecera: está en todo archivo real y el
            // catálogo de hojas no la lista. Avisar de ella sería un falso
            // positivo en cada envío.
            if ($normalizar($hoja) === 'NOMBRE') {
                continue;
            }

            if (! in_array($normalizar($hoja), $previstas, true)) {
                $hallazgos[] = new Hallazgo(
                    'V5', 'ENV-017', Severidad::ADVERTENCIA,
                    "La hoja «{$hoja}» no está prevista en la plantilla.",
                    $hoja, null, null, 'R-V5-03'
                );
            }
        }

        return $hallazgos;
    }
}