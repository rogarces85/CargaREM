<?php

namespace Database\Seeders;

use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos mínimos para que el portal funcione en L1 (T-F1-30).
 *
 * No es el catálogo real: es un juego pequeño y predecible para poder probar
 * el flujo sin depender de la importación. El catálogo real llega por
 * `catalogos:importar`.
 *
 * Idempotente: correrlo dos veces no duplica nada.
 */
class L1MinimumSeeder extends Seeder
{
    public const SERVICIO = ['codigo' => '23', 'nombre' => 'Servicio de Salud Osorno', 'region' => 'Los Lagos'];

    /** Comunas de la provincia de Osorno, una por establecimiento. */
    public const ESTABLECIMIENTOS = [
        ['codigo' => '123100', 'nombre' => 'Hospital Base San José', 'comuna' => 'Osorno'],
        ['codigo' => '123101', 'nombre' => 'Hospital de Puerto Octay', 'comuna' => 'Puerto Octay'],
        ['codigo' => '123102', 'nombre' => 'Hospital de Purranque', 'comuna' => 'Purranque'],
        ['codigo' => '123103', 'nombre' => 'Hospital de Río Negro', 'comuna' => 'Río Negro'],
        ['codigo' => '123104', 'nombre' => 'Posta de Salud Rural Puyehue', 'comuna' => 'Puyehue'],
        ['codigo' => '123105', 'nombre' => 'Posta de Salud Rural San Pablo', 'comuna' => 'San Pablo'],
        ['codigo' => '123106', 'nombre' => 'Posta de Salud Rural San Juan de la Costa', 'comuna' => 'San Juan de la Costa'],
    ];

    /** Las seis series del REM están activas; SAX queda fuera del MVP (D-006). */
    public const SERIES = [
        ['codigo' => 'A', 'nombre' => 'SERIE A', 'orden' => 1, 'activo' => true],
        ['codigo' => 'BS', 'nombre' => 'SERIE BS', 'orden' => 2, 'activo' => true],
        ['codigo' => 'BM', 'nombre' => 'SERIE BM', 'orden' => 3, 'activo' => true],
        ['codigo' => 'D', 'nombre' => 'SERIE D', 'orden' => 4, 'activo' => true],
        ['codigo' => 'P', 'nombre' => 'SERIE P', 'orden' => 5, 'activo' => true],
        ['codigo' => 'F', 'nombre' => 'SERIE F', 'orden' => 6, 'activo' => true],
        ['codigo' => 'SAX', 'nombre' => 'SERIE SAX (local, fuera del MVP)', 'orden' => 7, 'activo' => false],
    ];

    public const PERIODOS = [
        ['anio' => 2026, 'mes' => 4, 'estado' => Periodo::CERRADO],
        ['anio' => 2026, 'mes' => 5, 'estado' => Periodo::CERRADO],
        ['anio' => 2026, 'mes' => 6, 'estado' => Periodo::EN_REVISION],
        ['anio' => 2026, 'mes' => 7, 'estado' => Periodo::ABIERTO],
        ['anio' => 2026, 'mes' => 8, 'estado' => Periodo::ABIERTO],
        ['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO],
    ];

    /** Contraseña de los usuarios de prueba. Solo para L1 local. */
    public const CLAVE = 'Portal2026!';

    public function run(): void
    {
        $servicio = Servicio::updateOrCreate(
            ['codigo' => self::SERVICIO['codigo']],
            ['nombre' => self::SERVICIO['nombre'], 'region' => self::SERVICIO['region'], 'activo' => true]
        );

        $establecimientos = [];

        foreach (self::ESTABLECIMIENTOS as $datos) {
            $establecimientos[] = Establecimiento::updateOrCreate(
                ['codigo' => $datos['codigo']],
                [
                    'nombre'      => $datos['nombre'],
                    'servicio_id' => $servicio->id,
                    'comuna'      => $datos['comuna'],
                    'activo'      => true,
                ]
            );
        }

        $series = [];

        foreach (self::SERIES as $datos) {
            $series[$datos['codigo']] = Serie::updateOrCreate(
                ['codigo' => $datos['codigo']],
                ['nombre' => $datos['nombre'], 'orden' => $datos['orden'], 'activo' => $datos['activo']]
            );
        }

        foreach (self::PERIODOS as $datos) {
            Periodo::updateOrCreate(
                ['anio' => $datos['anio'], 'mes' => $datos['mes']],
                ['estado' => $datos['estado'], 'fecha_limite' => now()->setDate($datos['anio'], $datos['mes'], 10)->addMonth()->toDateString()]
            );
        }

        // Matriz: todos los establecimientos deben las seis series activas.
        DB::transaction(function () use ($establecimientos, $series) {
            foreach ($establecimientos as $establecimiento) {
                $establecimiento->series()->detach();

                foreach ($series as $codigo => $serie) {
                    if (! $serie->activo) {
                        continue;
                    }

                    $establecimiento->series()->attach($serie->id, [
                        'obligatoria'   => true,
                        'vigente_desde' => '2026-01-01',
                    ]);
                }
            }
        });

        $this->usuarios($establecimientos);
    }

    /** @param list<Establecimiento> $establecimientos */
    private function usuarios(array $establecimientos): void
    {
        // El revisor ve todo su Servicio de Salud: sin este dato, su bandeja
        // y la matriz de completitud salen vacías.
        $servicio = Servicio::where('codigo', self::SERVICIO['codigo'])->first();

        $admin = User::updateOrCreate(
            ['email' => 'admin@ssosorno.cl'],
            ['name' => 'Administrador REM', 'password' => Hash::make(self::CLAVE), 'activo' => true, 'servicio_id' => $servicio?->id]
        );
        $admin->syncRoles(['admin']);

        $revisor = User::updateOrCreate(
            ['email' => 'revisor@ssosorno.cl'],
            ['name' => 'Revisor Unidad de Estadísticas', 'password' => Hash::make(self::CLAVE), 'activo' => true, 'servicio_id' => $servicio?->id]
        );
        $revisor->syncRoles(['revisor']);

        $encargado1 = User::updateOrCreate(
            ['email' => 'encargado.hospital@ssosorno.cl'],
            ['name' => 'Encargado Hospital Base', 'password' => Hash::make(self::CLAVE), 'activo' => true, 'servicio_id' => $servicio?->id]
        );
        $encargado1->syncRoles(['encargado']);
        $encargado1->establecimientos()->sync(
            collect($establecimientos)->take(4)->pluck('id')->all()
        );

        $encargado2 = User::updateOrCreate(
            ['email' => 'encargado.postas@ssosorno.cl'],
            ['name' => 'Encargado Postas Rurales', 'password' => Hash::make(self::CLAVE), 'activo' => true, 'servicio_id' => $servicio?->id]
        );
        $encargado2->syncRoles(['encargado']);
        $encargado2->establecimientos()->sync(
            collect($establecimientos)->slice(4)->pluck('id')->all()
        );
    }
}
