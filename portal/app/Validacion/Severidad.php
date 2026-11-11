<?php

namespace App\Validacion;

/**
 * Las tres severidades del catálogo (Constitución, principio 6).
 *
 * Bloqueante detiene la cadena; advertencia no; informativa solo reporta.
 */
final class Severidad
{
    public const BLOQUEANTE = 'bloqueante';
    public const ADVERTENCIA = 'advertencia';
    public const INFO = 'info';
}