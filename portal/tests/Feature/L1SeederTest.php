<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\L1MinimumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeder mínimo L1 (T-F1-30).
 */
class L1SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_el_juego_minimo_completo(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Servicio::where('codigo', L1MinimumSeeder::SERVICIO['codigo'])->count());
        $this->assertSame(count(L1MinimumSeeder::ESTABLECIMIENTOS), Establecimiento::count());
        $this->assertSame(count(L1MinimumSeeder::SERIES), Serie::count());
        $this->assertSame(count(L1MinimumSeeder::PERIODOS), Periodo::count());
        $this->assertSame(4, User::count());
    }

    public function test_sax_queda_inactiva_y_fuera_de_la_matriz(): void
    {
        $this->seed(DatabaseSeeder::class);

        $sax = Serie::where('codigo', 'SAX')->first();

        $this->assertFalse($sax->activo, 'SAX está fuera del MVP (D-006)');
        $this->assertSame(0, $sax->establecimientos()->count());
        $this->assertSame(6, Serie::where('activo', true)->count());
    }

    public function test_la_matriz_exige_las_seis_series_activas_a_cada_establecimiento(): void
    {
        $this->seed(DatabaseSeeder::class);

        $activas = Serie::where('activo', true)->count();

        $this->assertSame(
            count(L1MinimumSeeder::ESTABLECIMIENTOS) * $activas,
            \DB::table('establecimiento_serie')->count()
        );

        foreach (Establecimiento::withCount('series')->get() as $establecimiento) {
            $this->assertSame($activas, $establecimiento->series_count);
        }
    }

    public function test_los_encargados_quedan_con_sus_establecimientos(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hospital = User::where('email', 'encargado.hospital@ssosorno.cl')->first();
        $postas = User::where('email', 'encargado.postas@ssosorno.cl')->first();

        $this->assertSame(4, $hospital->establecimientos()->count());
        $this->assertSame(3, $postas->establecimientos()->count());
        $this->assertTrue($hospital->hasRole('encargado'));
        $this->assertTrue(User::where('email', 'revisor@ssosorno.cl')->first()->hasRole('revisor'));
        $this->assertTrue(User::where('email', 'admin@ssosorno.cl')->first()->hasRole('admin'));
    }

    public function test_correrlo_dos_veces_no_duplica_nada(): void
    {
        $this->seed(DatabaseSeeder::class);

        $conteos = [
            Establecimiento::count(),
            Serie::count(),
            Periodo::count(),
            User::count(),
            \DB::table('establecimiento_serie')->count(),
        ];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($conteos, [
            Establecimiento::count(),
            Serie::count(),
            Periodo::count(),
            User::count(),
            \DB::table('establecimiento_serie')->count(),
        ]);
    }
}
