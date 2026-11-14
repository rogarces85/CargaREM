<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\User;
use App\Services\EnvioService;
use App\Services\EstadoEnvioService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marca «fuera de plazo» (D-028: sin valor por defecto).
 *
 * D4 sigue abierta: si el período no declara fecha límite, el gateway no
 * inventa una y el envío queda sin marca.
 */
class PlazoTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;
    private User $encargado;
    private Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base',
        ]);

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($this->establecimiento->id);

        $this->serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $this->establecimiento->series()->attach($this->serie->id, ['obligatoria' => true]);
    }

    private function envio(?string $fecha): Envio
    {
        Periodo::create([
            'anio'         => 2026,
            'mes'          => 9,
            'estado'       => Periodo::ABIERTO,
            'fecha_limite' => $fecha,
        ]);

        $this->actingAs($this->encargado);

        return app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $this->encargado);
    }

    private function aRevision(Envio $envio): Envio
    {
        $envio->update(['estado' => Envio::VALIDADO]);

        return app(EstadoEnvioService::class)->transicionar($envio->fresh(), Envio::EN_REVISION, $this->encargado);
    }

    public function test_sin_fecha_manual_el_calendario_fija_el_plazo(): void
    {
        $envio = $this->envio(null);

        // 09/2026: el quinto día hábil de octubre de 2026 es el 7.
        $this->assertSame('2026-10-07', $envio->fecha_limite->toDateString());

        $envio = $this->aRevision($envio);

        $this->assertTrue($envio->fuera_de_plazo, 'Hoy (14-11) ya pasó el plazo del período 09/2026');
    }

    public function test_un_envio_tarde_queda_marcado(): void
    {
        $envio = $this->envio(now()->subDay()->toDateString());

        $envio = $this->aRevision($envio);

        $this->assertTrue($envio->fuera_de_plazo);
    }

    public function test_un_envio_a_tiempo_no_queda_marcado(): void
    {
        $envio = $this->envio(now()->addDays(5)->toDateString());

        $envio = $this->aRevision($envio);

        $this->assertFalse($envio->fuera_de_plazo);
    }

    public function test_la_marca_se_calcula_una_sola_vez(): void
    {
        $envio = $this->envio(now()->subDay()->toDateString());
        $envio = $this->aRevision($envio);

        $this->assertTrue($envio->fuera_de_plazo);

        // Vuelve a manos del establecimiento y reenvía: la marca no cambia.
        app(EstadoEnvioService::class)->transicionar($envio->fresh(), Envio::OBSERVADO, $this->revisor());
        $envio = $this->aRevision($envio->fresh());

        $this->assertTrue($envio->fuera_de_plazo);
    }

    private function revisor(): User
    {
        $revisor = User::factory()->create(['activo' => true]);
        $revisor->assignRole('revisor');

        return $revisor;
    }
}
