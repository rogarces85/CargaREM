<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Periodo;
use App\Models\User;
use App\Services\AuditoriaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Auditoría append-only (T-F1-29).
 */
class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function usuario(string $rol = 'admin', array $extra = []): User
    {
        $u = User::factory()->create(array_merge([
            'password' => Hash::make('ContraseñaLarga123'),
            'activo'   => true,
        ], $extra));
        $u->assignRole($rol);

        return $u;
    }

    public function test_registra_una_fila_con_usuario_y_evento(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario);

        $fila = AuditoriaService::registrar('prueba.evento', 'Cosa', 7, ['a' => 1]);

        $this->assertDatabaseHas('auditoria', [
            'id'         => $fila->id,
            'usuario_id' => $usuario->id,
            'evento'     => 'prueba.evento',
            'entidad'    => 'Cosa',
            'entidad_id' => '7',
        ]);
        $this->assertSame(['a' => 1], $fila->fresh()->datos);
    }

    public function test_no_se_puede_modificar_una_fila(): void
    {
        $fila = AuditoriaService::registrar('prueba.evento');

        $this->expectException(RuntimeException::class);

        $fila->update(['evento' => 'reescrito']);
    }

    public function test_no_se_puede_borrar_una_fila(): void
    {
        $fila = AuditoriaService::registrar('prueba.evento');

        $this->expectException(RuntimeException::class);

        $fila->delete();
    }

    public function test_el_login_queda_registrado(): void
    {
        $this->usuario('encargado', ['email' => 'entra@ssosorno.cl']);

        $this->post('/login', [
            'email'    => 'entra@ssosorno.cl',
            'password' => 'ContraseñaLarga123',
        ]);

        $this->assertDatabaseHas('auditoria', ['evento' => 'acceso.login']);
    }

    public function test_el_login_fallido_queda_registrado_con_el_correo(): void
    {
        $this->post('/login', [
            'email'    => 'noexiste@ssosorno.cl',
            'password' => 'mala',
        ]);

        $fila = Auditoria::where('evento', 'acceso.fallido')->first();

        $this->assertNotNull($fila);
        $this->assertSame('noexiste@ssosorno.cl', $fila->datos['email']);
    }

    public function test_el_crud_de_catalogos_queda_registrado(): void
    {
        $admin = $this->usuario();
        $this->actingAs($admin);

        $periodo = Periodo::create(['anio' => 2026, 'mes' => 12, 'estado' => Periodo::ABIERTO]);

        $this->assertDatabaseHas('auditoria', [
            'evento'  => 'catalogo.creado',
            'entidad' => 'Periodo',
        ]);

        $periodo->update(['estado' => Periodo::CERRADO]);

        $this->assertDatabaseHas('auditoria', [
            'evento'  => 'catalogo.actualizado',
            'entidad' => 'Periodo',
        ]);
    }

    public function test_no_existe_pantalla_de_auditoria_en_v2(): void
    {
        // La UI de auditoría quedó fuera de v2.0: la bitácora se consulta en
        // base de datos y la pantalla no existe.
        $this->actingAs($this->usuario())
            ->get('/admin/auditoria')
            ->assertNotFound();
    }

    public function test_la_auditoria_no_guarda_el_contenido_de_las_celdas(): void
    {
        // El servicio solo acepta lo que le pasan; esta prueba fija el
        // contrato: los datos son identificadores, no valores del Excel.
        $fila = AuditoriaService::registrar('prueba', 'Cosa', 1, ['codigo' => '01010100']);

        $this->assertSame(['codigo' => '01010100'], $fila->datos);
        $this->assertStringNotContainsString('col01', json_encode($fila->datos));
    }
}
