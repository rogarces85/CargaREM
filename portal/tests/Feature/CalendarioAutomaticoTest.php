<?php

namespace Tests\Feature;

use App\Models\Feriado;
use App\Models\Periodo;
use App\Services\CalendarioService;
use App\Support\Plazos;
use Database\Seeders\FeriadosSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Calendario automático (RF-046, D-046).
 *
 * Tres cosas: el plazo es el quinto día hábil con los feriados chilenos, los
 * períodos se generan solos sin tocar estados, y una fecha fijada a mano se
 * respeta. Las fechas esperadas están calculadas a mano contra la lista de
 * `FeriadosSeeder`; si alguien cambia un feriado, este test lo dice.
 */
class CalendarioAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function calendario(): CalendarioService
    {
        return app(CalendarioService::class);
    }

    private function plazos(): Plazos
    {
        return app(Plazos::class);
    }

    public function test_el_plazo_de_2026_coincide_con_el_calendario_chileno(): void
    {
        // El plazo de diciembre cae en enero del año siguiente: se cargan los
        // dos años porque el de enero es un feriado de 2027.
        (new FeriadosSeeder())->sembrar(2026);
        (new FeriadosSeeder())->sembrar(2027);

        $esperadas = [
            1 => '2026-02-06',
            2 => '2026-03-06',
            3 => '2026-04-08', // Semana Santa: el 3 y el 4 son feriados
            4 => '2026-05-08', // 1 de mayo
            5 => '2026-06-05',
            6 => '2026-07-07',
            7 => '2026-08-07',
            8 => '2026-09-07',
            9 => '2026-10-07',
            10 => '2026-11-06',
            11 => '2026-12-07',
            12 => '2027-01-08',
        ];

        foreach ($esperadas as $mes => $fecha) {
            $this->assertSame(
                $fecha,
                $this->calendario()->fechaLimite(2026, $mes)->toDateString(),
                "El plazo de {$mes}/2026 no coincide"
            );
        }
    }

    public function test_el_plazo_de_2027_incluye_el_feriado_adicional_de_septiembre(): void
    {
        (new FeriadosSeeder())->sembrar(2027);

        $this->assertTrue(
            Feriado::whereDate('fecha', '2027-09-17')->exists(),
            'La Ley 20.983 agrega el 17-09-2027 porque el 18 y el 19 caen sábado y domingo.'
        );

        // Junio de 2027 tiene dos feriados (21 y 28): el plazo de mayo se corre.
        $this->assertSame('2027-06-07', $this->calendario()->fechaLimite(2027, 5)->toDateString());
        $this->assertSame('2027-07-07', $this->calendario()->fechaLimite(2027, 6)->toDateString());
    }

    public function test_la_semilla_de_feriados_tiene_los_anios_verificados(): void
    {
        $this->assertSame(16, count(FeriadosSeeder::FERIADOS[2026]));
        $this->assertSame(17, count(FeriadosSeeder::FERIADOS[2027]));
        $this->assertSame([2026, 2027], FeriadosSeeder::anios());
    }

    public function test_generar_crea_los_doce_periodos_sin_tocar_estados(): void
    {
        (new FeriadosSeeder())->sembrar(2026);

        // Un período existente y cerrado no debe reabrirse.
        Periodo::create(['anio' => 2026, 'mes' => 1, 'estado' => Periodo::CERRADO]);

        $resultado = $this->plazos()->generar(2026);

        $this->assertSame(11, $resultado['creados']);
        $this->assertSame(12, Periodo::where('anio', 2026)->count());
        $this->assertSame(Periodo::CERRADO, Periodo::where('anio', 2026)->where('mes', 1)->first()->estado);
        $this->assertSame('2026-02-06', Periodo::where('anio', 2026)->where('mes', 1)->first()->fecha_limite->toDateString());
    }

    public function test_generar_dos_veces_no_duplica_ni_reescribe(): void
    {
        $this->plazos()->generar(2027);
        $segunda = $this->plazos()->generar(2027);

        $this->assertSame(0, $segunda['creados']);
        $this->assertSame(0, $segunda['actualizados']);
        $this->assertSame(12, Periodo::where('anio', 2027)->count());
    }

    public function test_una_fecha_manual_se_respeta(): void
    {
        Periodo::create([
            'anio' => 2026, 'mes' => 3, 'estado' => Periodo::ABIERTO,
            'fecha_limite' => '2026-04-20', 'fecha_limite_manual' => true,
        ]);

        $this->plazos()->generar(2026);

        $this->assertSame('2026-04-20', Periodo::where('anio', 2026)->where('mes', 3)->first()->fecha_limite->toDateString());
        $this->assertSame([], array_filter(
            $this->plazos()->diferencias(2026),
            fn (array $d) => $d['periodo'] === '03/2026'
        ));
    }

    public function test_verificar_detecta_un_desfase(): void
    {
        (new FeriadosSeeder())->sembrar(2026);

        Periodo::create([
            'anio' => 2026, 'mes' => 4, 'estado' => Periodo::ABIERTO,
            'fecha_limite' => '2026-05-01',
        ]);

        $diferencias = $this->plazos()->diferencias(2026);

        $this->assertCount(1, $diferencias);
        $this->assertSame('04/2026', $diferencias[0]['periodo']);
        $this->assertSame('2026-05-01', $diferencias[0]['guardada']);
        $this->assertSame('2026-05-08', $diferencias[0]['calculada']);
    }

    public function test_el_comando_de_verificacion_falla_con_un_desfase(): void
    {
        Periodo::create([
            'anio' => 2026, 'mes' => 5, 'estado' => Periodo::ABIERTO,
            'fecha_limite' => '2026-06-01',
        ]);

        $this->artisan('rem:calendario', ['anio' => 2026, '--verificar' => true])
            ->assertExitCode(1);
    }

    public function test_el_comando_de_generacion_crea_el_anio(): void
    {
        $this->artisan('rem:periodos:generar', ['anio' => 2028])
            ->assertSuccessful();

        $this->assertSame(12, Periodo::where('anio', 2028)->count());
    }

    public function test_el_comando_de_feriados_carga_el_anio(): void
    {
        $this->artisan('rem:feriados:cargar', ['anio' => 2027])
            ->assertSuccessful();

        $this->assertSame(17, Feriado::whereYear('fecha', 2027)->count());
    }

    public function test_un_anio_sin_feriados_lo_advierte_el_comando(): void
    {
        $this->artisan('rem:calendario', ['anio' => 2031])
            ->expectsOutputToContain('No hay feriados cargados para 2031')
            ->assertSuccessful();
    }
}
