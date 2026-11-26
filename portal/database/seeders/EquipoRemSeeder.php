<?php

namespace Database\Seeders;

use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Equipo REM: los cuatro revisores de la Unidad (RF-050, D-041).
 *
 * Solo para entornos que no son producción. En producción el administrador
 * crea las cuentas y reparte los establecimientos: este seeder no puede
 * inventar una contraseña que alguien vaya a usar de verdad.
 *
 * Se siembra sin establecimientos a propósito. El reparto es una decisión de
 * la Unidad —qué comuna revisa cada uno— y ponerlo aquí sería inventarla. El
 * administrador lo hace en **Asignaciones**, y mientras tanto el portal avisa
 * de que faltan.
 *
 * Idempotente: correrlo dos veces no duplica ni pisa la contraseña de quien ya
 * la cambió.
 */
class EquipoRemSeeder extends Seeder
{
    /** Misma clave que los usuarios de prueba de L1. Solo local. */
    public const CLAVE = 'Portal2026!';

    /**
     * Los cuatro integrantes, con el nombre tal como llegó.
     *
     * El correo es provisional con el dominio del Servicio: el administrador lo
     * corrige en la pantalla de usuarios cuando tenga el real.
     *
     * @var list<array{nombre: string, email: string}>
     */
    public const INTEGRANTES = [
        ['nombre' => 'Roxana Mancilla', 'email' => 'roxana.mancilla@ssosorno.cl'],
        ['nombre' => 'Marcelo Horstmeir', 'email' => 'marcelo.horstmeir@ssosorno.cl'],
        ['nombre' => 'Victoria Martínez', 'email' => 'victoria.martinez@ssosorno.cl'],
        ['nombre' => 'Rodrigo Garcés', 'email' => 'rodrigo.garces@ssosorno.cl'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('EquipoRemSeeder no corre en producción: cree las cuentas desde Usuarios.');
            $this->command?->line('Integrantes a crear: ' . collect(self::INTEGRANTES)->pluck('nombre')->implode(', '));

            return;
        }

        $servicioId = Establecimiento::whereNotNull('servicio_id')->value('servicio_id');

        foreach (self::INTEGRANTES as $integrante) {
            $usuario = User::firstOrCreate(
                ['email' => $integrante['email']],
                [
                    'name'        => $integrante['nombre'],
                    'password'    => Hash::make(self::CLAVE),
                    'activo'      => true,
                    'servicio_id' => $servicioId,
                ]
            );

            // El nombre se corrige si alguien lo arregló a mano: `firstOrCreate`
            // con la misma clave no lo toca, y un nombre de la Unidad mal
            // escrito se arregla solo con volver a sembrar.
            if (! $usuario->wasRecentlyCreated) {
                $usuario->update(['name' => $integrante['nombre']]);
            }

            $usuario->syncRoles(['revisor']);

            $this->command?->line("  {$integrante['nombre']} — {$integrante['email']}");
        }

        $this->command?->info('Equipo REM: ' . count(self::INTEGRANTES) . ' integrantes. Asigne sus establecimientos en Administración → Asignaciones.');
    }
}
