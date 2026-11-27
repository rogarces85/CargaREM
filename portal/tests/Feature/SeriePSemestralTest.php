<?php

namespace Tests\Feature;

use App\Dominio\MatrizSeries;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use App\Models\User;
use App\Services\CompletitudService;
use App\Services\EstadoEnvioService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Serie P semestral (RF-045, D-045).
 *
 * La P no es mensual: se informa en los períodos de junio y diciembre. La
 * regla vive en `MatrizSeries` y la consumen cuatro lectores —V11, la pantalla
 * del envío, la aprobación y la completitud— así que este test comprueba que
 * los cuatro digan lo mismo. Es el mismo fallo que ya ocurrió una vez con la
 * ventana de vigencia.
 */
class SeriePSemestralTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;
    private User $encargado;
    private User $revisor;
    private Serie $serieA;
    private Serie $serieP;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base', 'comuna' => 'Osorno',
        ]);

        $this->serieA = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A', 'orden' => 1]);
        $this->serieP = Serie::create([
            'codigo' => 'P', 'nombre' => 'SERIE P', 'orden' => 5, 'meses_exigidos' => [6, 12],
        ]);

        foreach ([$this->serieA, $this->serieP] as $serie) {
            $this->establecimiento->series()->attach($serie->id, ['obligatoria' => true]);
        }

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
        $this->encargado->establecimientos()->attach($this->establecimiento->id);

        $this->revisor = User::factory()->create(['activo' => true]);
        $this->revisor->assignRole('revisor');
        $this->revisor->establecimientos()->attach($this->establecimiento->id);
    }

    private function exigidas(int $anio, int $mes): array
    {
        $matriz = app(MatrizSeries::class);

        return $matriz->exigidas($this->establecimiento, $matriz->cierreDelPeriodo($anio, $mes))
            ->pluck('codigo')
            ->all();
    }

    public function test_la_p_se_exige_en_junio_y_diciembre(): void
    {
        $this->assertSame(['A', 'P'], $this->exigidas(2026, 6));
        $this->assertSame(['A', 'P'], $this->exigidas(2026, 12));
    }

    public function test_la_p_no_se_exige_en_los_demas_meses(): void
    {
        foreach ([1, 2, 3, 4, 5, 7, 8, 9, 10, 11] as $mes) {
            $this->assertSame(['A'], $this->exigidas(2026, $mes), "La P no se exige en el mes {$mes}");
        }
    }

    public function test_el_mes_se_toma_del_periodo_y_no_de_la_fecha_de_hoy(): void
    {
        // Hoy es 26-11-2026: mes 11. La consulta del período de junio tiene que
        // juzgarse con junio, aunque se abra en noviembre.
        $this->travelTo(\Carbon\Carbon::parse('2026-11-26'));

        $this->assertSame(['A', 'P'], $this->exigidas(2026, 6));
        $this->assertSame(['A'], $this->exigidas(2026, 9));
    }

    public function test_la_aprobacion_no_exige_la_p_fuera_de_su_mes(): void
    {
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);

        $envio = Envio::create([
            'establecimiento_id' => $this->establecimiento->id,
            'anio' => 2026, 'mes' => 9, 'estado' => Envio::EN_REVISION,
            'fecha_envio' => now(),
        ]);

        $envio->archivos()->create([
            'serie_id' => $this->serieA->id, 'version' => 1, 'es_vigente' => true,
            'nombre_original' => 'x.xlsx', 'ruta' => 'envios/x.xlsx', 'disco' => 'local',
            'sha256' => str_repeat('b', 64), 'tamano_bytes' => 10, 'mime' => 'application/vnd.ms-excel',
            'estado' => EnvioArchivo::VALIDO, 'subido_por' => $this->encargado->id,
        ]);

        $aprobado = app(EstadoEnvioService::class)->aprobar($envio->fresh(), $this->revisor);

        $this->assertSame(Envio::APROBADO, $aprobado->estado);
    }

    public function test_la_completitud_no_cuenta_la_p_fuera_de_su_mes(): void
    {
        $matriz = app(CompletitudService::class)->matriz(2026, 9, $this->revisor);

        $fila = $matriz['filas'][0];

        // La columna de la P no existe en septiembre: la matriz solo arma
        // columnas con las series exigidas ese mes.
        $this->assertArrayNotHasKey($this->serieP->id, $fila['celdas']);
        $this->assertArrayHasKey($this->serieA->id, $fila['celdas']);
        $this->assertSame(1, $fila['exigidas']);
    }

    public function test_la_periodicidad_se_puede_leer_para_la_interfaz(): void
    {
        $this->assertSame('Mensual', $this->serieA->periodicidadLegible());
        $this->assertSame('Solo jun y dic', $this->serieP->periodicidadLegible());
    }

    public function test_una_p_que_llega_fuera_de_temporada_se_acepta(): void
    {
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);

        $envio = Envio::create([
            'establecimiento_id' => $this->establecimiento->id,
            'anio' => 2026, 'mes' => 9, 'estado' => Envio::RECIBIDO,
        ]);

        // Nada impide subirla: la regla es de exigencia, no de prohibición.
        $archivo = $envio->archivos()->create([
            'serie_id' => $this->serieP->id, 'version' => 1, 'es_vigente' => true,
            'nombre_original' => 'p.xlsx', 'ruta' => 'envios/p.xlsx', 'disco' => 'local',
            'sha256' => str_repeat('c', 64), 'tamano_bytes' => 10, 'mime' => 'application/vnd.ms-excel',
            'estado' => EnvioArchivo::VALIDO, 'subido_por' => $this->encargado->id,
        ]);

        $this->assertNotNull($archivo->id);
        $this->assertSame(1, $envio->archivosVigentes()->count());
    }
}
