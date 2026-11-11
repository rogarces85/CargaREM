<?php

namespace App\Validacion;

/**
 * Un hallazgo de validación: una fila de la tabla `validaciones`.
 *
 * Guarda el código `ENV-###`, la etapa y la severidad. El mensaje es el que
 * verá el usuario final, en español y con acción sugerida.
 */
final class Hallazgo
{
    public function __construct(
        public readonly string $etapa,
        public readonly string $codigo,
        public readonly string $severidad,
        public readonly string $mensaje,
        public readonly ?string $hoja = null,
        public readonly ?string $celda = null,
        public readonly ?string $valor = null,
        public readonly ?string $regla = null,
    ) {
    }

    public function esBloqueante(): bool
    {
        return $this->severidad === Severidad::BLOQUEANTE;
    }

    /** @return array<string, mixed> */
    public function aFila(int $archivoId): array
    {
        return [
            'envio_archivo_id' => $archivoId,
            'etapa'            => $this->etapa,
            'codigo'           => $this->codigo,
            'severidad'        => $this->severidad,
            'regla'            => $this->regla,
            'mensaje'          => mb_substr($this->mensaje, 0, 500),
            'hoja'             => $this->hoja,
            'celda'            => $this->celda,
            'valor'            => $this->valor !== null ? mb_substr($this->valor, 0, 255) : null,
        ];
    }
}