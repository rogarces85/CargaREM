<?php

namespace App\Validacion\Reglas;

use App\Validacion\ContextoValidacion;
use App\Validacion\Hallazgo;
use App\Validacion\ReglaValidacion;
use App\Validacion\Severidad;

/**
 * V0 — el archivo (ENV-001 a ENV-004, ENV-027, ENV-028).
 *
 * Se revalida aunque la subida ya lo comprobó: un archivo puede haberse
 * corrompido en disco, y revalidar debe dar el mismo resultado aunque el
 * original ya no esté.
 */
final class ReglaV0Archivo implements ReglaValidacion
{
    public function etapa(): string
    {
        return 'V0';
    }

    public function evaluar(ContextoValidacion $contexto): array
    {
        $ruta = $contexto->rutaAbsoluta;
        $hallazgos = [];

        if (! is_readable($ruta)) {
            return [new Hallazgo(
                'V0', 'ENV-004', Severidad::BLOQUEANTE,
                'El archivo ya no está disponible en el almacén. Vuelva a subirlo.',
                null, null, null, 'R-V0-01'
            )];
        }

        $maxKb = (int) config('rem.archivos.max_kb');
        $kb = (int) ceil(filesize($ruta) / 1024);

        if ($kb > $maxKb) {
            $hallazgos[] = new Hallazgo(
                'V0', 'ENV-001', Severidad::BLOQUEANTE,
                "El archivo pesa {$kb} KB y el máximo permitido es {$maxKb} KB.",
                null, null, (string) $kb, 'R-V0-02'
            );
        }

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $permitidas = (array) config('rem.archivos.extensiones');

        if (! in_array($extension, $permitidas, true)) {
            $hallazgos[] = new Hallazgo(
                'V0', 'ENV-002', Severidad::BLOQUEANTE,
                'Extensión no permitida. Use '.implode(', ', $permitidas).'.',
                null, null, $extension, 'R-V0-03'
            );
        }

        $firma = (string) file_get_contents($ruta, false, null, 0, 8);
        $esZip = str_starts_with($firma, "PK\x03\x04");
        $esOle2 = str_starts_with($firma, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");

        if (! $esZip && ! $esOle2) {
            $hallazgos[] = new Hallazgo(
                'V0', 'ENV-003', Severidad::BLOQUEANTE,
                'El contenido no corresponde a un archivo Excel válido.',
                null, null, null, 'R-V0-04'
            );
        }

        // Un .xlsm trae macros: no se ejecutan nunca, pero se avisa.
        if ($extension === 'xlsm') {
            $hallazgos[] = new Hallazgo(
                'V0', 'ENV-028', Severidad::ADVERTENCIA,
                'El archivo contiene macros; no se ejecutarán.',
                null, null, null, 'R-V0-05'
            );
        }

        return $hallazgos;
    }
}
