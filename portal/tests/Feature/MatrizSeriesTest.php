<?php

namespace Tests\Feature;

use App\Dominio\MatrizSeries;
use App\Models\Establecimiento;
use App\Models\Serie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La regla de la matriz, en el único lugar donde vive (R-SER-03 a R-SER-07).
 *
 * Antes estaba escrita cuatro veces y las copias no coincidían. Estas pruebas
 * fijan la condición en el dominio, para que ninguna copia futura pueda volver a
 * discrepar en silencio: la ventana de vigencia se evalúa contra el **cierre del
 * período informado**, no contra la fecha en que corre la consulta.
 */
class MatrizSeriesTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;

    /** @var array<string, Serie> */
    private array $series = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->establecimiento = Establecimiento::create([
            'codigo' => '123100',
            'nombre' => 'Hospital Base',
        ]);

        foreach (['A' => 1, 'BS' => 2, 'BM' => 3] as $codigo => $orden) {
            $this->series[$codigo] = Serie::create([
                'codigo' => $codigo,
                'nombre' => 'SERIE '.$codigo,
                'orden' => $orden,
                'activo' => true,
            ]);
        }
    }

    /** @param array<string, mixed> $pivot */
    private function exigir(string $codigo, array $pivot = []): void
    {
        $this->establecimiento->series()->attach(
            $this->series[$codigo]->id,
            $pivot + ['obligatoria' => true]
        );
    }

    private function codigosExigidos(?int $anio = null, ?int $mes = null): array
    {
        $matriz = app(MatrizSeries::class);
        $periodo = $anio === null ? null : $matriz->cierreDelPeriodo($anio, $mes);

        return $matriz->exigidas($this->establecimiento, $periodo)
            ->pluck('codigo')
            ->all();
    }

    public function test_sin_filas_no_se_exige_nada(): void
    {
        $this->assertSame([], $this->codigosExigidos());
    }

    public function test_solo_devuelve_las_series_marcadas_como_obligatorias(): void
    {
        $this->exigir('A');
        $this->exigir('BS', ['obligatoria' => false]);

        $this->assertSame(['A'], $this->codigosExigidos());
    }

    public function test_una_serie_inactiva_no_se_exige_aunque_tenga_fila(): void
    {
        $this->exigir('A');
        $this->exigir('BS');

        $this->series['BS']->update(['activo' => false]);

        $this->assertSame(['A'], $this->codigosExigidos());
    }

    public function test_respeta_el_orden_del_catalogo(): void
    {
        $this->exigir('BM');
        $this->exigir('A');
        $this->exigir('BS');

        $this->assertSame(['A', 'BS', 'BM'], $this->codigosExigidos());
    }

    public function test_una_serie_con_vigencia_terminada_no_se_exige(): void
    {
        $this->exigir('A', ['vigente_hasta' => '2026-06-30']);

        // Se evalúa con el cierre de julio: la serie venció en junio.
        $this->assertSame([], $this->codigosExigidos(2026, 7));
    }

    public function test_una_serie_con_vigencia_terminada_sigue_exigiendose_en_su_mes(): void
    {
        $this->exigir('A', ['vigente_hasta' => '2026-06-30']);

        // R-SER-04: un mes ya cerrado no se reinterpreta con la matriz de hoy.
        $this->assertSame(['A'], $this->codigosExigidos(2026, 6));
    }

    public function test_una_serie_con_vigencia_futura_no_se_exige_todavia_pero_si_en_su_mes(): void
    {
        $this->exigir('BS', ['vigente_desde' => '2026-09-01']);

        $this->assertSame([], $this->codigosExigidos(2026, 8));
        $this->assertSame(['BS'], $this->codigosExigidos(2026, 9));
    }

    public function test_la_vigencia_sin_fecha_rige_desde_siempre_y_sin_termino(): void
    {
        $this->exigir('A');

        $this->assertSame(['A'], $this->codigosExigidos(2020, 1));
        $this->assertSame(['A'], $this->codigosExigidos(2030, 12));
    }

    public function test_el_ancla_de_la_vigencia_es_el_cierre_del_periodo_evaluado(): void
    {
        // Con la fecha de hoy (noviembre de 2026) esta serie ya venció, pero el
        // período de junio todavía la exigía. Es el fallo que tenía la
        // aprobación: usaba now() en vez del cierre del período.
        $this->travelTo(Carbon::parse('2026-11-25'));

        $this->exigir('A', ['vigente_hasta' => '2026-06-30']);

        $this->assertSame([], $this->codigosExigidos(2026, 11), 'En noviembre ya no se exige');
        $this->assertSame(['A'], $this->codigosExigidos(2026, 6), 'En junio sí se exigía');

        $this->travelBack();
    }

    public function test_los_identificadores_coinciden_con_las_series_exigidas(): void
    {
        $this->exigir('A');
        $this->exigir('BM');

        $matriz = app(MatrizSeries::class);

        $this->assertSame(
            [$this->series['A']->id, $this->series['BM']->id],
            $matriz->idsExigidos($this->establecimiento)->all()
        );
    }
}
