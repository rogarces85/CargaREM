<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Orden obligatorio: los permisos existen antes que los usuarios que los
     * reciben, y las series antes que la matriz que las referencia.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            L1MinimumSeeder::class,
        ]);
    }
}
