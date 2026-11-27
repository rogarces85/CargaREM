<?php

namespace App\Validador;

/**
 * Agregación de resultados para el tablero y el reporte (RF-040).
 *
 * La distinción entre CUMPLE, HALLAZGO y OMITIDA es la que hace honesto el
 * porcentaje: una regla omitida no es una regla cumplida, y contar las
 * omisiones como aprobadas infla el resultado sin que nadie haya revisado nada.
 * Ese fue el fallo del primer recuento del escritorio.
 */
final class ResumenValidacion
{
    /**
     * @param  list<ResultadoRegla>  $resultados
     */
    public function __construct(
        public readonly int $evaluadas = 0,
        public readonly int $cumplen = 0,
        public readonly int $hallazgos = 0,
        public readonly int $omitidas = 0,
        public readonly int $erroresTecnicos = 0,
        public readonly int $fueraDeAlcance = 0,
        public readonly int $hojasAusentes = 0,
        public readonly int $errores = 0,
        public readonly int $revisar = 0,
        public readonly int $erroresBloqueantes = 0,
        public readonly float $porcentajeAprobacion = 100.0,
        public readonly string $veredicto = 'APROBADO',
    ) {
    }

    /** @param  list<ResultadoRegla>  $resultados */
    public static function desde(array $resultados, int $fueraDeAlcance = 0, int $hojasAusentes = 0): self
    {
        $cumplen = 0;
        $hallazgos = 0;
        $omitidas = 0;
        $erroresTecnicos = 0;
        $errores = 0;
        $revisar = 0;
        $bloqueantes = 0;

        foreach ($resultados as $r) {
            switch ($r->estado) {
                case ResultadoRegla::CUMPLE:
                    $cumplen++;
                    break;

                case ResultadoRegla::HALLAZGO:
                    $hallazgos++;

                    if ($r->severidad === ReglaCalidad::ERROR) {
                        $errores++;

                        if ($r->bloqueaVeredicto) {
                            $bloqueantes++;
                        }
                    } elseif ($r->severidad === ReglaCalidad::REVISAR) {
                        $revisar++;
                    }

                    break;

                case ResultadoRegla::OMITIDA:
                    $omitidas++;
                    break;

                case ResultadoRegla::ERROR_TECNICO:
                    $erroresTecnicos++;
                    break;
            }
        }

        $evaluadas = $cumplen + $hallazgos;

        $veredicto = match (true) {
            $bloqueantes > 0 => 'RECHAZADO',
            $hallazgos > 0   => 'CON_OBSERVACIONES',
            default          => 'APROBADO',
        };

        return new self(
            evaluadas: $evaluadas,
            cumplen: $cumplen,
            hallazgos: $hallazgos,
            omitidas: $omitidas,
            erroresTecnicos: $erroresTecnicos,
            fueraDeAlcance: $fueraDeAlcance,
            hojasAusentes: $hojasAusentes,
            errores: $errores,
            revisar: $revisar,
            erroresBloqueantes: $bloqueantes,
            porcentajeAprobacion: $evaluadas === 0
                ? 100.0
                : round(($cumplen / $evaluadas) * 100, 1),
            veredicto: $veredicto,
        );
    }

    /** @return array<string, mixed> */
    public function aArreglo(): array
    {
        return [
            'evaluadas'       => $this->evaluadas,
            'cumplen'         => $this->cumplen,
            'hallazgos'       => $this->hallazgos,
            'omitidas'        => $this->omitidas,
            'erroresTecnicos' => $this->erroresTecnicos,
            'fueraDeAlcance'  => $this->fueraDeAlcance,
            'hojasAusentes'   => $this->hojasAusentes,
            'errores'         => $this->errores,
            'revisar'         => $this->revisar,
            'bloqueantes'     => $this->erroresBloqueantes,
            'porcentaje'      => $this->porcentajeAprobacion,
            'veredicto'       => $this->veredicto,
        ];
    }
}
