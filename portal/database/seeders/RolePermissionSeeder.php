<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos del portal (RF-013, principio 8).
 *
 * Fuente: SPECS.md §3.2 y tests/Parity/fixtures/rbac-matrix.json.
 *
 * La matriz se declara como dato y no como código repetido: así el test que
 * verifica la matriz puede leer exactamente lo mismo que se sembró.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Permiso => roles que lo tienen.
     *
     * @var array<string, list<string>>
     */
    public const MATRIZ = [
        'envios.crear'         => ['encargado', 'admin'],
        'envios.ver.propio'    => ['encargado', 'admin'],
        'envios.ver.ss'        => ['revisor', 'admin', 'consulta'],
        'envios.enviar'        => ['encargado', 'admin'],
        'envios.observar'      => ['revisor', 'admin'],
        'envios.aprobar'       => ['revisor', 'admin'],
        'envios.cargar'        => ['revisor', 'admin'],
        'envios.reabrir'       => ['admin'],
        'envios.anular'        => ['admin'],
        'archivos.descargar'   => ['encargado', 'revisor', 'admin', 'consulta'],
        'reportes.descargar'   => ['encargado', 'revisor', 'admin', 'consulta'],
        'completitud.ver'      => ['encargado', 'revisor', 'admin', 'consulta'],
        'paquetes.generar'     => ['revisor', 'admin'],
        'catalogos.importar'   => ['admin'],
        'catalogos.gestionar'  => ['admin'],
        'usuarios.gestionar'   => ['admin'],
        'periodos.gestionar'   => ['admin'],
        'auditoria.ver'        => ['admin'],
    ];

    /** @var list<string> */
    public const ROLES = ['encargado', 'revisor', 'admin', 'consulta'];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_keys(self::MATRIZ) as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $rol) {
            $modelo = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);

            $permisos = collect(self::MATRIZ)
                ->filter(fn (array $roles) => in_array($rol, $roles, true))
                ->keys()
                ->all();

            $modelo->syncPermissions($permisos);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
