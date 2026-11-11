<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aislamiento por consulta (T-F1-16) y autorización por policy (T-F1-15).
 * RN-ENV-1, RN-ENV-7 y principio 18.
 */
class EnvioPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function servicio(string $codigo, string $nombre): Servicio
    {
        return Servicio::create(['codigo' => $codigo, 'nombre' => $nombre]);
    }

    private function establecimiento(Servicio $servicio, string $codigo): Establecimiento
    {
        return Establecimiento::create([
            'codigo' => $codigo, 'nombre' => "Establecimiento {$codigo}", 'servicio_id' => $servicio->id,
        ]);
    }

    private function envio(Establecimiento $establecimiento, int $mes = 9): Envio
    {
        return Envio::create([
            'establecimiento_id' => $establecimiento->id,
            'anio'               => 2026,
            'mes'                => $mes,
            'estado'             => Envio::RECIBIDO,
        ]);
    }

    private function usuario(string $rol, array $extra = []): User
    {
        $u = User::factory()->create(array_merge(['activo' => true], $extra));
        $u->assignRole($rol);

        return $u;
    }

    public function test_el_admin_ve_todos_los_envios(): void
    {
        $ss = $this->servicio('23', 'Osorno');
        $this->envio($this->establecimiento($ss, 'A1'));
        $this->envio($this->establecimiento($ss, 'A2'), 8);

        $admin = $this->usuario('admin');

        $this->assertSame(2, Envio::forUser($admin)->count());
    }

    public function test_el_encargado_solo_ve_los_suyos(): void
    {
        $ss = $this->servicio('23', 'Osorno');
        $propio = $this->establecimiento($ss, 'A1');
        $ajeno = $this->establecimiento($ss, 'A2');
        $this->envio($propio);
        $this->envio($ajeno, 8);

        $encargado = $this->usuario('encargado');
        $encargado->establecimientos()->attach($propio->id);

        $visibles = Envio::forUser($encargado)->get();

        $this->assertCount(1, $visibles);
        $this->assertSame($propio->id, $visibles->first()->establecimiento_id);
    }

    public function test_el_revisor_ve_su_servicio_y_no_el_ajeno(): void
    {
        $osorno = $this->servicio('23', 'Osorno');
        $otro = $this->servicio('24', 'Otro SS');
        $this->envio($this->establecimiento($osorno, 'A1'));
        $this->envio($this->establecimiento($otro, 'B1'), 8);

        $revisor = $this->usuario('revisor', ['servicio_id' => $osorno->id]);

        $this->assertSame(1, Envio::forUser($revisor)->count());
    }

    public function test_un_encargado_no_crea_envio_en_establecimiento_ajeno(): void
    {
        $ss = $this->servicio('23', 'Osorno');
        $propio = $this->establecimiento($ss, 'A1');
        $ajeno = $this->establecimiento($ss, 'A2');

        $encargado = $this->usuario('encargado');
        $encargado->establecimientos()->attach($propio->id);

        $this->assertTrue($encargado->can('create', [Envio::class, $propio]));
        $this->assertFalse($encargado->can('create', [Envio::class, $ajeno]));
    }

    public function test_el_revisor_no_aprueba_su_propio_establecimiento(): void
    {
        $ss = $this->servicio('23', 'Osorno');
        $establecimiento = $this->establecimiento($ss, 'A1');
        $envio = $this->envio($establecimiento);
        $envio->update(['estado' => Envio::EN_REVISION]);

        // Revisor que además es encargado de ese establecimiento (RN-ENV-7).
        $revisor = $this->usuario('revisor', ['servicio_id' => $ss->id]);
        $revisor->assignRole('encargado');
        $revisor->establecimientos()->attach($establecimiento->id);

        $this->assertFalse($revisor->can('aprobar', $envio));

        // Otro revisor del mismo servicio sí puede.
        $otro = $this->usuario('revisor', ['servicio_id' => $ss->id]);
        $this->assertTrue($otro->can('aprobar', $envio));
    }

    public function test_no_se_puede_aprobar_ni_cargar_en_estado_equivocado(): void
    {
        $ss = $this->servicio('23', 'Osorno');
        $envio = $this->envio($this->establecimiento($ss, 'A1'));
        $revisor = $this->usuario('revisor', ['servicio_id' => $ss->id]);

        $this->assertFalse($revisor->can('aprobar', $envio), 'recibido no se aprueba');
        $this->assertFalse($revisor->can('cargar', $envio), 'solo se carga lo aprobado');
    }

    public function test_solo_el_admin_reabre_y_anula(): void
    {
        $ss = $this->servicio('23', 'Osorno');
        $envio = $this->envio($this->establecimiento($ss, 'A1'));

        $admin = $this->usuario('admin');
        $revisor = $this->usuario('revisor', ['servicio_id' => $ss->id]);

        $this->assertTrue($admin->can('anular', $envio));
        $this->assertFalse($revisor->can('anular', $envio));
        $this->assertFalse($admin->can('reabrir', $envio), 'un envío recibido no se reabre');
    }

    public function test_un_envio_no_se_borra(): void
    {
        $ss = $this->servicio('23', 'Osorno');
        $envio = $this->envio($this->establecimiento($ss, 'A1'));
        $admin = $this->usuario('admin');

        $this->assertFalse($admin->can('delete', $envio));
    }
}
