<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriz establecimiento × serie (T-F1-23).
 */
class MatrizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole('admin');

        return $u;
    }

    private function establecimiento(): Establecimiento
    {
        $servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);

        return Establecimiento::create([
            'codigo'      => '123100',
            'nombre'      => 'Hospital Base',
            'servicio_id' => $servicio->id,
        ]);
    }

    public function test_el_admin_ve_la_matriz(): void
    {
        $this->establecimiento();

        $this->actingAs($this->admin())
            ->get('/admin/matriz')
            ->assertOk()
            ->assertSee('Hospital Base');
    }

    public function test_editar_muestra_las_series_del_catalogo(): void
    {
        $establecimiento = $this->establecimiento();
        Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);

        $this->actingAs($this->admin())
            ->get("/admin/matriz/{$establecimiento->id}/edit")
            ->assertOk()
            ->assertSee('SERIE A');
    }

    public function test_guarda_solo_las_series_marcadas_con_su_vigencia(): void
    {
        $establecimiento = $this->establecimiento();
        $a = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $bs = Serie::create(['codigo' => 'BS', 'nombre' => 'SERIE BS']);

        $this->actingAs($this->admin())
            ->put("/admin/matriz/{$establecimiento->id}", [
                'series' => [
                    $a->id  => ['obligatoria' => '1', 'vigente_desde' => '2026-01-01'],
                    $bs->id => ['obligatoria' => '0'],
                ],
            ])
            ->assertRedirect(route('admin.matriz.index'));

        $this->assertSame(1, $establecimiento->series()->count());
        $this->assertTrue((bool) $establecimiento->series->first()->pivot->obligatoria);
        $this->assertSame('2026-01-01', $establecimiento->series->first()->pivot->vigente_desde);
    }

    public function test_reemplaza_la_matriz_anterior(): void
    {
        $establecimiento = $this->establecimiento();
        $a = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $bs = Serie::create(['codigo' => 'BS', 'nombre' => 'SERIE BS']);

        $establecimiento->series()->attach($a->id, ['obligatoria' => true]);

        $this->actingAs($this->admin())
            ->put("/admin/matriz/{$establecimiento->id}", [
                'series' => [
                    $bs->id => ['obligatoria' => '1'],
                ],
            ]);

        $this->assertSame(
            ['BS'],
            $establecimiento->fresh()->series->pluck('codigo')->all()
        );
    }

    public function test_un_encargado_no_puede_tocar_la_matriz(): void
    {
        $establecimiento = $this->establecimiento();
        $encargado = User::factory()->create(['activo' => true]);
        $encargado->assignRole('encargado');

        $this->actingAs($encargado)
            ->put("/admin/matriz/{$establecimiento->id}", ['series' => []])
            ->assertForbidden();
    }
}
