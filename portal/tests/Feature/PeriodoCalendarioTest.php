<?php

namespace Tests\Feature;

use App\Models\Periodo;
use Database\Seeders\FeriadosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fecha límite del período calculada por el calendario (D-029).
 */
class PeriodoCalendarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FeriadosSeeder::class);
    }

    public function test_un_periodo_sin_fecha_la_calcula_del_calendario(): void
    {
        $periodo = Periodo::create([
            'anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO,
        ]);

        $this->assertSame('2026-10-07', $periodo->fecha_limite->toDateString());
        $this->assertFalse($periodo->fecha_limite_manual);
    }

    public function test_una_fecha_explicita_se_respeta(): void
    {
        $periodo = Periodo::create([
            'anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO,
            'fecha_limite' => '2026-10-20',
        ]);

        $this->assertSame('2026-10-20', $periodo->fecha_limite->toDateString());
    }

    public function test_al_cambiar_el_periodo_se_recalcula(): void
    {
        $periodo = Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);

        $periodo->update(['mes' => 10]);

        $this->assertSame('2026-11-06', $periodo->fresh()->fecha_limite->toDateString());
    }

    public function test_el_override_manual_sobrevive_al_cambio_de_periodo(): void
    {
        $periodo = Periodo::create([
            'anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO,
            'fecha_limite' => '2026-10-20', 'fecha_limite_manual' => true,
        ]);

        $periodo->update(['mes' => 10]);

        $this->assertSame('2026-10-20', $periodo->fresh()->fecha_limite->toDateString());
        $this->assertTrue($periodo->fresh()->fecha_limite_manual);
    }

    public function test_la_fecha_calculada_se_puede_consultar_aunque_haya_override(): void
    {
        $periodo = Periodo::create([
            'anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO,
            'fecha_limite' => '2026-10-20', 'fecha_limite_manual' => true,
        ]);

        $this->assertSame('2026-10-07', $periodo->fechaLimiteCalculada()->toDateString());
    }
}
