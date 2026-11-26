<?php

namespace App\Validador;

/**
 * Un hallazgo del Validador (RF-040).
 *
 * Deliberadamente no es `App\Validacion\Hallazgo`: aquel pertenece al flujo de
 * envío, se persiste en `validaciones` y cambia el estado de un envío. Este se
 * guarda como JSON en su propia ejecución y no toca nada del proceso.
 */
final class HallazgoCalidad
{
    public function __construct(
        public readonly string $regla,
        public readonly string $severidad,
        public readonly string $mensaje,
        public readonly ?string $hoja = null,
        public readonly ?string $celda = null,
    ) {
    }

    public function esBloqueante(): bool
    {
        return $this->severidad === 'bloqueante';
    }

    /** @return array<string, mixed> */
    public function aArreglo(): array
    {
        return [
            'regla'     => $this->regla,
            'severidad' => $this->severidad,
            'mensaje'   => $this->mensaje,
            'hoja'      => $this->hoja,
            'celda'     => $this->celda,
        ];
    }
}
