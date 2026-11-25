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
    /** El único Servicio del MVP v2 (D-022). */
    public const SERVICIO = [
        'codigo' => '23',
        'nombre' => 'Servicio de Salud Osorno',
        'region' => 'Los Lagos',
        'activo' => true,
    ];

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
            [
                'nombre' => self::SERVICIO['nombre'],
                'region' => self::SERVICIO['region'],
                'activo' => self::SERVICIO['activo'],
            ]
        );

        $establecimientos = [];

        foreach (self::ESTABLECIMIENTOS as $datos) {
            // `firstOrCreate`, no `updateOrCreate`: estos siete códigos existen
            // en el catálogo real del Minsal. Si el catálogo ya se importó, el
            // nombre bueno es el del catálogo y este seeder no puede pisarlo.
            // Antes lo pisaba en cada `iniciar.bat`: «Hospital Base San José de
            // Osorno» volvía a «Hospital Base San José».
            $establecimientos[] = Establecimiento::firstOrCreate(
                ['codigo' => $datos['codigo']],
                [
                    'nombre'      => $datos['nombre'],
                    'comuna'      => $datos['comuna'],
                    'activo'      => true,
                    'servicio_id' => $servicio->id,
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
            // Sin fecha explícita: el modelo la calcula con el calendario
            // (quinto día hábil del mes siguiente, D-029).
            Periodo::updateOrCreate(
                ['anio' => $datos['anio'], 'mes' => $datos['mes']],
                ['estado' => $datos['estado']]
            );
        }

        // Matriz de los establecimientos de prueba: todas las series activas.
        //
        // Solo se rellena donde **no hay ninguna fila**. `detach()` + `attach()`
        // borraba la matriz que el administrador hubiera editado cada vez que se
        // corría `db:seed`. Ahora la decisión del administrador sobrevive a
        // cualquier siembra.
        DB::transaction(function () use ($establecimientos, $series) {
            foreach ($establecimientos as $establecimiento) {
                if ($establecimiento->series()->exists()) {
                    continue;
                }

                foreach ($series as $serie) {
                    if (! $serie->activo) {
                        continue;
                    }

                    $establecimiento->series()->attach($serie->id, [
                        'obligatoria'   => true,
                        // `null` y no una fecha: la regla es «rige desde
                        // siempre». El importador usa el mismo valor; antes
                        // había dos criterios para la misma noción y un período
                        // anterior a 2026-01 se comportaba distinto según quién
                        // hubiera creado la fila.
                        'vigente_desde' => null,
                    ]);
                }
            }
        });

        $this->usuarios($establecimientos, $servicio);
    }

    /**
     * Los usuarios de prueba pertenecen al Servicio sembrado: sin eso el
     * revisor no vería ningún envío.
     *
     * @param list<Establecimiento> $establecimientos
     */
    private function usuarios(array $establecimientos, Servicio $servicio): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@ssosorno.cl'],
            ['name' => 'Administrador REM', 'password' => Hash::make(self::CLAVE), 'activo' => true, 'servicio_id' => $servicio->id]
        );
        $admin->syncRoles(['admin']);

        $revisor = User::updateOrCreate(
            ['email' => 'revisor@ssosorno.cl'],
            ['name' => 'Revisor Unidad de Estadísticas', 'password' => Hash::make(self::CLAVE), 'activo' => true, 'servicio_id' => $servicio->id]
        );
        $revisor->syncRoles(['revisor']);

        $encargado1 = User::updateOrCreate(
            ['email' => 'encargado.hospital@ssosorno.cl'],
            ['name' => 'Encargado Hospital Base', 'password' => Hash::make(self::CLAVE), 'activo' => true, 'servicio_id' => $servicio->id]
        );
        $encargado1->syncRoles(['encargado']);
        $encargado1->establecimientos()->sync(
            collect($establecimientos)->take(4)->pluck('id')->all()
        );

        $encargado2 = User::updateOrCreate(
            ['email' => 'encargado.postas@ssosorno.cl'],
            ['name' => 'Encargado Postas Rurales', 'password' => Hash::make(self::CLAVE), 'activo' => true, 'servicio_id' => $servicio->id]
        );
        $encargado2->syncRoles(['encargado']);
        $encargado2->establecimientos()->sync(
            collect($establecimientos)->slice(4)->pluck('id')->all()
        );
    }
}
