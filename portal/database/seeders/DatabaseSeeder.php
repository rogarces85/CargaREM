<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Orden obligatorio: los permisos existen antes que los usuarios que los
     * reciben, las series antes que la matriz que las referencia y el Servicio
     * antes de la matriz de completitud.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            FeriadosSeeder::class,
            L1MinimumSeeder::class,
            CatalogoSeeder::class,
        ]);
    }
}
