<?php

namespace App\Validador;

/**
 * Resultado de evaluar una regla (RF-040).
 *
 * Sustituye al modelo anterior de «hallazgo sí/no». El motor del escritorio
 * distingue cuatro estados y esa distinción importa: una regla **omitida** no
 * es una regla cumplida, y contarla como aprobada infla el porcentaje de
 * calidad sin que nadie haya revisado nada.
 */
final class ResultadoRegla
{
    public const CUMPLE = 'CUMPLE';
    public const HALLAZGO = 'HALLAZGO';
    public const OMITIDA = 'OMITIDA';
    public const ERROR_TECNICO = 'ERROR_TECNICO';

    /** Motivos por los que una regla no se evalúa. */
    public const OMISION_AMBOS_CERO = 'AMBOS_CERO';
    public const OMISION_CONDICION_PREVIA = 'CONDICION_PREVIA_NO_CUMPLE';
    public const OMISION_SECCION_SIN_ACTIVIDAD = 'SECCION_SIN_ACTIVIDAD';
    public const OMISION_HOJA_AUSENTE = 'HOJA_AUSENTE';

    /**
     * @param  list<string>  $celdas
     */
    public function __construct(
        public readonly string $reglaId,
        public readonly string $severidad,
        public readonly string $estado,
        public readonly string $mensaje,
        public readonly string $hoja,
        public readonly string $expresion1,
        public readonly ?float $valor1,
        public readonly string $operador,
        public readonly string $expresion2,
        public readonly ?float $valor2,
        public readonly ?string $hoja2 = null,
        public readonly ?float $diferencia = null,
        public readonly array $celdas = [],
        public readonly ?string $motivoOmision = null,
        public readonly string $evidencia = '',
        public readonly bool $bloqueaVeredicto = true,
    ) {
    }

    public function esHallazgo(): bool
    {
        return $this->estado === self::HALLAZGO;
    }

    public function esError(): bool
    {
        return $this->severidad === ReglaCalidad::ERROR;
    }

    /** @return array<string, mixed> */
    public function aArreglo(): array
    {
        return [
            'regla'      => $this->reglaId,
            'severidad'  => $this->severidad,
            'estado'     => $this->estado,
            'mensaje'    => $this->mensaje,
            'hoja'       => $this->hoja,
            'hoja2'      => $this->hoja2,
            'expresion1' => $this->expresion1,
            'valor1'     => $this->valor1,
            'operador'   => $this->operador,
            'expresion2' => $this->expresion2,
            'valor2'     => $this->valor2,
            'diferencia' => $this->diferencia,
            'celdas'     => $this->celdas,
            'omision'    => $this->motivoOmision,
            'evidencia'  => $this->evidencia,
            'bloquea'    => $this->bloqueaVeredicto,
        ];
    }
}
