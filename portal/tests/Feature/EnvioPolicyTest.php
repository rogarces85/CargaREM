<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\Establecimiento;
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

    private function establecimiento(string $codigo): Establecimiento
    {
        return Establecimiento::create([
            'codigo' => $codigo, 'nombre' => "Establecimiento {$codigo}",
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

    private function usuario(string $rol): User
    {
        $u = User::factory()->create(['activo' => true]);
        $u->assignRole($rol);

        return $u;
    }

    public function test_el_admin_ve_todos_los_envios(): void
    {
        $this->envio($this->establecimiento('A1'));
        $this->envio($this->establecimiento('A2'), 8);

        $admin = $this->usuario('admin');

        $this->assertSame(2, Envio::forUser($admin)->count());
    }

    public function test_el_encargado_solo_ve_los_suyos(): void
    {
        $propio = $this->establecimiento('A1');
        $ajeno = $this->establecimiento('A2');
        $this->envio($propio);
        $this->envio($ajeno, 8);

        $encargado = $this->usuario('encargado');
        $encargado->establecimientos()->attach($propio->id);

        $visibles = Envio::forUser($encargado)->get();

        $this->assertCount(1, $visibles);
        $this->assertSame($propio->id, $visibles->first()->establecimiento_id);
    }

    public function test_el_revisor_ve_todos_los_envios(): void
    {
        $this->envio($this->establecimiento('A1'));
        $this->envio($this->establecimiento('A2'), 8);

        $revisor = $this->usuario('revisor');

        $this->assertSame(2, Envio::forUser($revisor)->count());
    }

    public function test_un_encargado_no_crea_envio_en_establecimiento_ajeno(): void
    {
        $propio = $this->establecimiento('A1');
        $ajeno = $this->establecimiento('A2');

        $encargado = $this->usuario('encargado');
        $encargado->establecimientos()->attach($propio->id);

        $this->assertTrue($encargado->can('create', [Envio::class, $propio]));
        $this->assertFalse($encargado->can('create', [Envio::class, $ajeno]));
    }

    public function test_el_revisor_no_aprueba_su_propio_establecimiento(): void
    {
        $establecimiento = $this->establecimiento('A1');
        $envio = $this->envio($establecimiento);
        $envio->update(['estado' => Envio::EN_REVISION]);

        // Revisor que además es encargado de ese establecimiento (RN-ENV-7).
        $revisor = $this->usuario('revisor');
        $revisor->assignRole('encargado');
        $revisor->establecimientos()->attach($establecimiento->id);

        $this->assertFalse($revisor->can('aprobar', $envio));

        // Otro revisor sí puede.
        $otro = $this->usuario('revisor');
        $this->assertTrue($otro->can('aprobar', $envio));
    }

    public function test_no_se_puede_aprobar_en_estado_equivocado(): void
    {
        $envio = $this->envio($this->establecimiento('A1'));
        $revisor = $this->usuario('revisor');

        $this->assertFalse($revisor->can('aprobar', $envio), 'recibido no se aprueba');
    }

    public function test_un_envio_no_se_borra(): void
    {
        $envio = $this->envio($this->establecimiento('A1'));
        $admin = $this->usuario('admin');

        $this->assertFalse($admin->can('delete', $envio));
    }
}
