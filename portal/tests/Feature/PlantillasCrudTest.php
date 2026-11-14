<?php

namespace Tests\Feature;

use App\Models\Plantilla;
use App\Models\PlantillaHoja;
use App\Models\Serie;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gestión de plantillas (RF-012).
 */
class PlantillasCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['activo' => true]);
        $this->admin->assignRole('admin');

        $this->serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'anio'          => 2026,
            'mes'           => 9,
            'serie_id'      => $this->serie->id,
            'serie_excel'   => 'SERIE A',
            'serie_interna' => 'SERIE A1',
            'version'       => 'Version 1.2',
            'hojas'         => "A01|A3:L35\nNOMBRE|A1:H17",
        ], $extra);
    }

    public function test_el_admin_crea_una_plantilla_con_sus_hojas(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/plantillas', $this->datos())
            ->assertRedirect(route('admin.plantillas.index'));

        $this->assertDatabaseHas('plantillas', [
            'anio' => 2026, 'mes' => 9, 'serie_id' => $this->serie->id, 'version' => 'Version 1.2',
        ]);

        $this->assertSame(2, PlantillaHoja::where('anio', 2026)->where('serie_interna', 'SERIE A1')->count());
        $this->assertDatabaseHas('plantilla_hojas', ['hoja' => 'A01', 'rango' => 'A3:L35']);
    }

    public function test_la_version_no_se_repite_para_el_mismo_periodo_y_serie(): void
    {
        Plantilla::create(['anio' => 2026, 'mes' => 9, 'serie_id' => $this->serie->id, 'version' => 'Version 1.2', 'serie_excel' => 'SERIE A', 'serie_interna' => 'SERIE A1']);

        $this->actingAs($this->admin)
            ->post('/admin/plantillas', $this->datos())
            ->assertSessionHasErrors('version');

        $this->assertSame(1, Plantilla::count());
    }

    public function test_el_admin_edita_y_reemplaza_las_hojas(): void
    {
        $plantilla = Plantilla::create([
            'anio' => 2026, 'mes' => 9, 'serie_id' => $this->serie->id,
            'serie_excel' => 'SERIE A', 'serie_interna' => 'SERIE A1', 'version' => 'Version 1.2',
        ]);
        PlantillaHoja::create(['anio' => 2026, 'serie_interna' => 'SERIE A1', 'hoja' => 'VIEJA', 'obligatoria' => true]);

        $this->actingAs($this->admin)
            ->put("/admin/plantillas/{$plantilla->id}", $this->datos(['hojas' => 'A02|A3:L35']))
            ->assertRedirect(route('admin.plantillas.index'));

        $this->assertSame(1, PlantillaHoja::where('anio', 2026)->count());
        $this->assertDatabaseHas('plantilla_hojas', ['hoja' => 'A02']);
        $this->assertDatabaseMissing('plantilla_hojas', ['hoja' => 'VIEJA']);
    }

    public function test_un_encargado_no_gestiona_plantillas(): void
    {
        $encargado = User::factory()->create(['activo' => true]);
        $encargado->assignRole('encargado');

        $this->actingAs($encargado)
            ->post('/admin/plantillas', $this->datos())
            ->assertForbidden();

        $this->assertSame(0, Plantilla::count());
    }
}
