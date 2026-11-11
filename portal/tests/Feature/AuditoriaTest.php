<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\CatalogoSnapshot;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\CatalogoService;
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

    public function test_importar_y_activar_un_catalogo_queda_registrado(): void
    {
        $servicio = app(CatalogoService::class);

        $snapshot = $servicio->importarDirectorio(base_path('tests/fixtures/catalogo'));
        $servicio->activar($snapshot);

        $this->assertDatabaseHas('auditoria', ['evento' => 'catalogo.importado']);
        $this->assertDatabaseHas('auditoria', ['evento' => 'catalogo.activado']);
    }

    public function test_el_admin_ve_la_auditoria(): void
    {
        $admin = $this->usuario();
        AuditoriaService::registrar('prueba.visible');

        $this->actingAs($admin)
            ->get('/admin/auditoria')
            ->assertOk()
            ->assertSee('prueba.visible')
            ->assertDontSee('Editar');
    }

    public function test_un_encargado_no_ve_la_auditoria(): void
    {
        $this->actingAs($this->usuario('encargado'))
            ->get('/admin/auditoria')
            ->assertForbidden();
    }

    public function test_filtra_por_evento(): void
    {
        $admin = $this->usuario();
        AuditoriaService::registrar('uno.de.dos');
        AuditoriaService::registrar('el.otro');

        // El filtro no se comprueba por texto: la lista de eventos del
        // formulario contiene todos los nombres. Se mira el paginador.
        $this->actingAs($admin)
            ->get('/admin/auditoria?evento=uno.de.dos')
            ->assertOk()
            ->assertSee('uno.de.dos')
            ->assertViewHas('registros', fn ($pagina) => $pagina->total() === 1);
    }

    public function test_la_auditoria_no_guarda_el_contenido_de_las_celdas(): void
    {
        // El servicio solo acepta lo que le pasan; esta prueba fija el
        // contrato: los datos son identificadores, no valores del Excel.
        $fila = AuditoriaService::registrar('prueba', 'Cosa', 1, ['codigo' => '01010100']);

        $this->assertSame(['codigo' => '01010100'], $fila->datos);
        $this->assertStringNotContainsString('col01', json_encode($fila->datos));
    }

    public function test_el_observador_no_audita_actualizaciones_sin_cambio_de_estado(): void
    {
        $snapshot = CatalogoSnapshot::create(['origen' => 'sin activar']);
        $antes = Auditoria::count();

        $snapshot->update(['filas_diccionario' => 10]);

        $this->assertSame($antes, Auditoria::count(), 'Solo importar y activar generan fila');
    }
}
