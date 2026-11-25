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

    public function test_siembra_el_servicio_unico_y_lo_asigna(): void
    {
        $this->seed(DatabaseSeeder::class);

        $servicio = Servicio::where('codigo', L1MinimumSeeder::SERVICIO['codigo'])->first();

        $this->assertNotNull($servicio, 'El MVP sirve a un solo Servicio (D-022)');
        $this->assertSame(0, Establecimiento::whereNull('servicio_id')->count());
        $this->assertSame(0, User::whereNull('servicio_id')->count());
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

    /**
     * Los seis códigos de prueba existen en el catálogo real del Minsal. Cuando
     * el catálogo ya está importado, el nombre bueno es el suyo: el seeder no
     * puede revertirlo a la etiqueta corta de L1.
     *
     * Antes lo hacía en cada corrida de `iniciar.bat`, y con ella borraba la
     * matriz de series editada por el administrador.
     */
    public function test_no_pisa_los_nombres_del_catalogo_importado(): void
    {
        $this->seed(DatabaseSeeder::class);

        $establecimiento = Establecimiento::where('codigo', '123100')->first();
        $establecimiento->update(['nombre' => 'Hospital Base San José de Osorno']);

        $this->seed(L1MinimumSeeder::class);

        $this->assertSame(
            'Hospital Base San José de Osorno',
            $establecimiento->fresh()->nombre,
            'El nombre del catálogo manda sobre la etiqueta de L1'
        );
    }

    public function test_no_borra_una_matriz_ya_editada(): void
    {
        $this->seed(DatabaseSeeder::class);

        $establecimiento = Establecimiento::where('codigo', '123100')->first();
        $serieF = Serie::where('codigo', 'F')->first();

        // El administrador deja de exigir la serie F en ese establecimiento.
        $establecimiento->series()->detach($serieF->id);

        $this->seed(L1MinimumSeeder::class);

        $this->assertFalse(
            $establecimiento->fresh()->series->contains('id', $serieF->id),
            'La siembra no puede resucitar la serie que el administrador quitó'
        );
    }
}
