<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Serie;
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
        return Establecimiento::create([
            'codigo' => '123100',
            'nombre' => 'Hospital Base',
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

    /**
     * R-SER-06: «serie desmarcada» significa `obligatoria = 0`, no «fila
     * borrada». La fila tiene que quedar: si se descarta, la serie desaparece
     * de la matriz y la siguiente importación la vuelve a exigir, porque una
     * matriz vacía se lee como «nadie configuró esto».
     */
    public function test_guarda_tambien_las_desmarcadas_con_obligatoria_en_falso(): void
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

        $this->assertSame(2, $establecimiento->series()->count(), 'Las dos filas se guardan');

        $filaA = $establecimiento->series->firstWhere('codigo', 'A');
        $filaBS = $establecimiento->series->firstWhere('codigo', 'BS');

        $this->assertTrue((bool) $filaA->pivot->obligatoria);
        $this->assertSame('2026-01-01', $filaA->pivot->vigente_desde);
        $this->assertFalse((bool) $filaBS->pivot->obligatoria, 'BS se acepta pero no cuenta');

        // Y lo que importa: una desmarcada no se exige.
        $this->assertSame(
            ['A'],
            app(\App\Dominio\MatrizSeries::class)->exigidas($establecimiento)->pluck('codigo')->all()
        );
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
