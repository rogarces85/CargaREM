<?php

namespace App\Policies;

use App\Dominio\Alcance;
use App\Models\Apertura;
use App\Models\User;

/**
 * Autorización de aperturas de plazo (RF-043, D-044).
 *
 * Dos capas que no se sustituyen: el permiso dice *qué acción* se puede hacer
 * y el alcance dice *sobre qué establecimiento*. Un encargado con
 * `aperturas.solicitar` no pide una apertura para un establecimiento ajeno.
 */
class AperturaPolicy
{
    public function __construct(private readonly Alcance $alcance)
    {
    }

    public function viewAny(User $usuario): bool
    {
        return $usuario->can('aperturas.solicitar') || $usuario->can('aperturas.resolver');
    }

    public function view(User $usuario, Apertura $apertura): bool
    {
        if ($usuario->hasRole('admin')) {
            return true;
        }

        if (! $usuario->can('aperturas.solicitar')) {
            return false;
        }

        // La apertura de un establecimiento se ve desde ese establecimiento; la
        // que vale para todos, desde cualquiera del alcance.
        return $apertura->esParaTodos()
            ? $usuario->establecimientos()->exists()
            : $this->alcance->incluye($usuario, (int) $apertura->establecimiento_id);
    }

    /** Pedir una apertura: solo el administrador o el encargado del establecimiento. */
    public function solicitar(User $usuario): bool
    {
        return $usuario->can('aperturas.solicitar');
    }

    /** Resolver, abrir por cuenta propia o anular: solo el administrador (R-APL-01). */
    public function resolver(User $usuario, ?Apertura $apertura = null): bool
    {
        if (! $usuario->can('aperturas.resolver')) {
            return false;
        }

        return $apertura === null || $apertura->estado === Apertura::PENDIENTE;
    }

    public function anular(User $usuario, Apertura $apertura): bool
    {
        return $usuario->can('aperturas.resolver')
            && in_array($apertura->estado, [Apertura::APROBADA, Apertura::PENDIENTE], true);
    }
}
