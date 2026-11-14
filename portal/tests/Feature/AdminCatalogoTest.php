<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de administración de catálogos (T-F1-22).
 *
 * El permiso se comprueba en el servidor: la vista no es la que decide.
 */
class AdminCatalogoTest extends TestCase
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

    private function encargado(): User
    {
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole('encargado');

        return $u;
    }

    public function test_un_invitado_va_al_login(): void
    {
        $this->get('/admin/establecimientos')->assertRedirect('/login');
    }

    public function test_un_encargado_no_entra_a_la_administracion(): void
    {
        $this->actingAs($this->encargado())
            ->get('/admin/establecimientos')
            ->assertForbidden();
    }

    public function test_el_admin_ve_el_listado(): void
    {
        Establecimiento::create(['codigo' => '123100', 'nombre' => 'Hospital Base']);

        $this->actingAs($this->admin())
            ->get('/admin/establecimientos')
            ->assertOk()
            ->assertSee('Hospital Base');
    }

    public function test_el_admin_crea_un_periodo(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/periodos', [
                'anio'   => 2026,
                'mes'    => 9,
                'estado' => Periodo::ABIERTO,
            ])
            ->assertRedirect(route('admin.periodos.index'));

        $this->assertDatabaseHas('periodos', ['anio' => 2026, 'mes' => 9, 'estado' => 'abierto']);
    }

    public function test_no_se_pueden_repetir_periodo_anio_mes(): void
    {
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);

        $this->actingAs($this->admin())
            ->post('/admin/periodos', [
                'anio'   => 2026,
                'mes'    => 9,
                'estado' => Periodo::ABIERTO,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(1, Periodo::count());
    }

    public function test_valida_los_datos_del_establecimiento(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/establecimientos', ['codigo' => '', 'nombre' => ''])
            ->assertSessionHasErrors(['codigo', 'nombre']);
    }

    public function test_el_admin_edita_un_establecimiento(): void
    {
        $establecimiento = Establecimiento::create(['codigo' => '123100', 'nombre' => 'Hospital Base']);

        $this->actingAs($this->admin())
            ->put("/admin/establecimientos/{$establecimiento->id}", [
                'codigo' => '123100',
                'nombre' => 'Hospital Base San José',
                'activo' => 1,
            ])
            ->assertRedirect(route('admin.establecimientos.index'));

        $this->assertSame('Hospital Base San José', $establecimiento->fresh()->nombre);
    }

    public function test_el_admin_gestiona_servicios(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/servicios', [
                'codigo' => '23',
                'nombre' => 'Servicio de Salud Osorno',
                'region' => 'Los Lagos',
                'activo' => 1,
            ])
            ->assertRedirect(route('admin.servicios.index'));

        $this->assertDatabaseHas('servicios', ['codigo' => '23', 'nombre' => 'Servicio de Salud Osorno']);

        $this->actingAs($this->admin())
            ->get('/admin/servicios')
            ->assertOk()
            ->assertSee('Servicio de Salud Osorno');
    }

    public function test_un_encargado_no_gestiona_servicios(): void
    {
        $this->actingAs($this->encargado())
            ->post('/admin/servicios', ['codigo' => '23', 'nombre' => 'Otro'])
            ->assertForbidden();

        $this->assertDatabaseCount('servicios', 0);
    }

    public function test_ya_no_existen_las_pantallas_de_snapshots_ni_auditoria(): void
    {
        $this->actingAs($this->admin())->get('/admin/snapshots')->assertNotFound();
        $this->actingAs($this->admin())->get('/admin/auditoria')->assertNotFound();
    }
}
