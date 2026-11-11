<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * No hay hueco libre para validar (APP-ENV-008).
 *
 * El servidor de producción es modesto: dos validaciones a la vez como máximo.
 * Una tercera espera; si la espera se agota, se rechaza con un mensaje que
 * dice qué pasó. Es preferible a que el proceso muera por memoria.
 */
class ValidacionOcupadaException extends RuntimeException
{
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
    ) {
        parent::__construct($mensaje);
    }

    public static function sinSlot(int $slots, int $espera): self
    {
        return new self(
            'APP-ENV-008',
            "Hay {$slots} validaciones en curso y ninguna terminó en {$espera} segundos. Vuelva a intentarlo en un momento."
        );
    }
}
