<?php

namespace App\Validacion\Reglas;

use App\Excel\LectorCabecera;
use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;
use Throwable;

/**
 * V3 — versión de la plantilla (ENV-014).
 *
 * El catálogo dice qué versión de formato corresponde a cada período y serie.
 * Si el archivo declara otra, se valida con reglas que no son las suyas.
 *
 * Si el catálogo no tiene plantilla para ese período, no se bloquea: se avisa.
 * Bloquear por una carencia del catálogo dejaría el portal inservible el mes
 * en que el Minsal publica tarde. Se bloquea cuando se sabe que está mal; se
 * advierte cuando no se puede saber.
 */
final class ReglaV3Version implements ReglaValidacion
{
    public function __construct(private readonly LectorCabecera $lector = new LectorCabecera())
    {
    }

    public function etapa(): string
    {
        return 'V3';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        try {
            $cabecera = $this->lector->leer($contexto->rutaAbsoluta, $contexto->plantilla);
        } catch (Throwable $e) {
            return []; // V2 ya reportó el libro ilegible
        }

        $declarada = $this->normalizar($cabecera->version);

        if ($contexto->plantilla === null) {
            return [new Hallazgo(
                'V3', 'ENV-014', Severidad::ADVERTENCIA,
                'El catálogo no tiene plantilla para este período y serie: no se pudo verificar la versión.',
                $cabecera->hoja, null, $declarada, 'R-V3-01'
            )];
        }

        $esperada = $this->normalizar($contexto->plantilla->version);

        if ($declarada === null || $declarada !== $esperada) {
            return [new Hallazgo(
                'V3', 'ENV-014', Severidad::BLOQUEANTE,
                "La versión de plantilla del archivo ({$declarada}) no corresponde al período (esperada: {$esperada}).",
                $cabecera->hoja, null, $declarada, 'R-V3-02'
            )];
        }

        return [];
    }

    /** "Versión 1.2: SERIE BS1" y "Version 1.2" normalizan a "1.2". */
    private function normalizar(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        if (preg_match('/(\d+(?:\.\d+)+)/', $valor, $m)) {
            return $m[1];
        }

        return trim($valor);
    }
}