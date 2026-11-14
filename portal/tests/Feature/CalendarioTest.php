<?php

namespace Tests\Feature;

use App\Models\Feriado;
use App\Services\CalendarioService;
use Database\Seeders\FeriadosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Calendario de plazos: quinto día hábil del mes siguiente (D-029).
 */
class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    private function calendario(): CalendarioService
    {
        return app(CalendarioService::class);
    }

    public function test_el_plazo_es_el_quinto_dia_habil_del_mes_siguiente(): void
    {
        $this->seed(FeriadosSeeder::class);

        // Octubre 2026: 1 jue, 2 vie, 5 lun, 6 mar, 7 mié → quinto día hábil.
        $this->assertSame('2026-10-07', $this->calendario()->fechaLimite(2026, 9)->toDateString());
    }

    public function test_un_feriado_corre_el_plazo(): void
    {
        $this->seed(FeriadosSeeder::class);

        // Mayo 2026: 1 viernes es feriado; 4, 5, 6, 7 y 8 son los hábiles.
        $this->assertSame('2026-05-08', $this->calendario()->fechaLimite(2026, 4)->toDateString());
    }

    public function test_el_fin_de_semana_no_cuenta(): void
    {
        $this->seed(FeriadosSeeder::class);

        // Agosto 2026 empieza sábado: 3, 4, 5, 6 y 7 son los hábiles.
        $this->assertSame('2026-08-07', $this->calendario()->fechaLimite(2026, 7)->toDateString());
    }

    public function test_sin_feriados_el_plazo_se_corre(): void
    {
        // Sin feriados cargados, el 1 de mayo cuenta como hábil: el plazo se
        // adelanta un día respecto del calendario real.
        $this->assertFalse($this->calendario()->hayFeriados(2026));
        $this->assertSame('2026-05-07', $this->calendario()->fechaLimite(2026, 4)->toDateString());
    }

    public function test_hay_feriados_por_anio(): void
    {
        $this->seed(FeriadosSeeder::class);

        $this->assertTrue($this->calendario()->hayFeriados(2026));
        $this->assertFalse($this->calendario()->hayFeriados(2027));
        $this->assertSame(16, Feriado::count());
    }

    public function test_el_feriado_registrado_bloquea_el_dia(): void
    {
        Feriado::create(['fecha' => '2026-11-04', 'nombre' => 'Feriado de prueba']);

        $this->assertFalse($this->calendario()->esHabil(\Carbon\Carbon::parse('2026-11-04')));
        $this->assertTrue($this->calendario()->esHabil(\Carbon\Carbon::parse('2026-11-05')));
        $this->assertFalse($this->calendario()->esHabil(\Carbon\Carbon::parse('2026-11-07')), 'sábado');
    }
}
