<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\User;
use App\Models\ValidadorEjecucion;
use App\Services\ValidadorEstadisticaService;
use App\Validador\CatalogoCalidad;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Módulo Validador (RF-040, D-047).
 *
 * Lo que fijan estos tests, en orden de importancia:
 *
 * 1. **No toca el proceso.** Usar el Validador no crea ni modifica envíos ni
 *    validaciones (R-VAL-01).
 * 2. **Se registra.** Cada ejecución queda con autor, archivo, versión de
 *    reglas y resultado (R-VAL-03).
 * 3. **Un tipo desconocido no se ignora.** Aborta y queda como fallida.
 */
class ModuloValidadorTest extends TestCase
{
    use RefreshDatabase;

    private User $encargado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');
    }

    /**
     * Excel de prueba con la hoja NOMBRE.
     *
     * @param  array<int, array<int, mixed>>  $filas
     */
    private function excel(array $filas = [], string $hoja = 'NOMBRE'): UploadedFile
    {
        $libro = new Spreadsheet();
        $activa = $libro->getActiveSheet();
        $activa->setTitle($hoja);

        $activa->setCellValue('A1', 'Código');
        $activa->setCellValue('B1', 'Prestación');
        $activa->setCellValue('C1', 'Total');

        foreach ($filas as $indice => $fila) {
            $numero = $indice + 2;
            $activa->setCellValue("A{$numero}", $fila[0] ?? null);
            $activa->setCellValue("B{$numero}", $fila[1] ?? null);
            $activa->setCellValue("C{$numero}", $fila[2] ?? null);
        }

        $temporal = tempnam(sys_get_temp_dir(), 'validador') . '.xlsx';
        (new Xlsx($libro))->save($temporal);
        $contenido = (string) file_get_contents($temporal);
        $libro->disconnectWorksheets();
        @unlink($temporal);

        return UploadedFile::fake()->createWithContent('estadistica.xlsx', $contenido);
    }

    public function test_un_archivo_limpio_no_deja_hallazgos(): void
    {
        $this->actingAs($this->encargado)
            ->post(route('validador.store'), [
                'archivo' => $this->excel([
                    ['01010101', 'Control', 10],
                    ['01010102', 'Consulta', 20],
                ]),
            ])
            ->assertRedirect();

        $ejecucion = ValidadorEjecucion::firstOrFail();

        $this->assertSame(ValidadorEjecucion::SIN_HALLAZGOS, $ejecucion->estado);
        $this->assertSame(0, $ejecucion->hallazgos);
        $this->assertSame($this->encargado->id, $ejecucion->usuario_id);
        $this->assertSame('estadistica.xlsx', $ejecucion->nombre_original);
    }

    public function test_una_hoja_faltante_es_un_hallazgo_bloqueante(): void
    {
        $this->actingAs($this->encargado)
            ->post(route('validador.store'), [
                'archivo' => $this->excel([['01010101', 'Control', 10]], 'OTRA'),
            ]);

        $ejecucion = ValidadorEjecucion::firstOrFail();

        $this->assertSame(ValidadorEjecucion::CON_HALLAZGOS, $ejecucion->estado);
        $this->assertGreaterThanOrEqual(1, $ejecucion->bloqueantes);
        $this->assertContains('CAL-001', array_column($ejecucion->listaHallazgos(), 'regla'));
    }

    public function test_una_celda_negativa_la_detecta_la_regla_de_rango(): void
    {
        $this->actingAs($this->encargado)
            ->post(route('validador.store'), [
                'archivo' => $this->excel([['01010101', 'Control', -5]]),
            ]);

        $ejecucion = ValidadorEjecucion::firstOrFail();

        $this->assertContains('CAL-004', array_column($ejecucion->listaHallazgos(), 'regla'));
    }

    public function test_un_codigo_repetido_lo_detecta_la_regla_de_unicidad(): void
    {
        $this->actingAs($this->encargado)
            ->post(route('validador.store'), [
                'archivo' => $this->excel([
                    ['01010101', 'Control', 10],
                    ['01010101', 'Control repetido', 12],
                ]),
            ]);

        $ejecucion = ValidadorEjecucion::firstOrFail();

        $this->assertContains('CAL-005', array_column($ejecucion->listaHallazgos(), 'regla'));
    }

    public function test_el_validador_no_toca_ninguna_tabla_del_flujo(): void
    {
        $establecimiento = Establecimiento::create(['codigo' => '123100', 'nombre' => 'Hospital Base']);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);
        Envio::create([
            'establecimiento_id' => $establecimiento->id,
            'anio' => 2026, 'mes' => 9, 'estado' => Envio::RECIBIDO,
        ]);

        $antes = [
            'envios' => Envio::count(),
            'validaciones' => \DB::table('validaciones')->count(),
            'envio_archivos' => \DB::table('envio_archivos')->count(),
            'envio_transiciones' => \DB::table('envio_transiciones')->count(),
        ];

        $this->actingAs($this->encargado)
            ->post(route('validador.store'), ['archivo' => $this->excel([['01010101', 'Control', 10]])]);

        // R-VAL-01: el módulo es una ayuda, no una etapa del proceso.
        $this->assertSame($antes['envios'], Envio::count());
        $this->assertSame($antes['validaciones'], \DB::table('validaciones')->count());
        $this->assertSame($antes['envio_archivos'], \DB::table('envio_archivos')->count());
        $this->assertSame($antes['envio_transiciones'], \DB::table('envio_transiciones')->count());

        $this->assertSame(1, ValidadorEjecucion::count());
    }

    public function test_un_tipo_de_regla_desconocido_aborta_la_ejecucion(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'reglas') . '.json';
        file_put_contents($ruta, json_encode([
            'version' => 'prueba',
            'nombre' => 'Prueba',
            'reglas' => [
                ['id' => 'X-1', 'tipo' => 'regla_inventada', 'severidad' => 'advertencia', 'mensaje' => 'No existe.'],
            ],
        ]));

        $this->expectException(\RuntimeException::class);

        CatalogoCalidad::desdeArchivo($ruta);
    }

    public function test_el_administrador_ve_el_registro_de_uso(): void
    {
        $admin = User::factory()->create(['activo' => true]);
        $admin->assignRole('admin');

        $this->actingAs($this->encargado)
            ->post(route('validador.store'), ['archivo' => $this->excel([['01010101', 'Control', 10]])]);

        $this->actingAs($admin)
            ->get(route('validador.estadisticas'))
            ->assertOk()
            ->assertSee($this->encargado->name);
    }

    public function test_el_encargado_no_ve_el_registro_de_uso(): void
    {
        $this->actingAs($this->encargado)
            ->get(route('validador.estadisticas'))
            ->assertForbidden();
    }

    public function test_cada_usuario_ve_solo_sus_revisiones(): void
    {
        $this->actingAs($this->encargado)
            ->post(route('validador.store'), ['archivo' => $this->excel([['01010101', 'Control', 10]])]);

        $ejecucion = ValidadorEjecucion::firstOrFail();

        $otro = User::factory()->create(['activo' => true]);
        $otro->assignRole('encargado');

        $this->actingAs($otro)
            ->get(route('validador.show', $ejecucion))
            ->assertForbidden();
    }

    public function test_el_servicio_registra_la_version_de_reglas(): void
    {
        $this->actingAs($this->encargado)
            ->post(route('validador.store'), ['archivo' => $this->excel([['01010101', 'Control', 10]])]);

        $ejecucion = ValidadorEjecucion::firstOrFail();

        $this->assertSame(CatalogoCalidad::desdeArchivo()->version, $ejecucion->reglas_version);
        $this->assertNotSame('', $ejecucion->sha256);
    }

    public function test_la_pantalla_del_validador_carga(): void
    {
        $this->actingAs($this->encargado)
            ->get(route('validador.index'))
            ->assertOk()
            ->assertSee('Validador de calidad');
    }

    public function test_el_archivo_se_guarda_en_disco_privado_con_nombre_generado(): void
    {
        $archivo = $this->excel([['01010101', 'Control', 10]]);

        $resultado = app(ValidadorEstadisticaService::class)->ejecutar($this->encargado, $archivo);

        $ruta = $resultado['ejecucion']->ruta;

        $this->assertStringStartsWith('validador/', $ruta);
        $this->assertStringNotContainsString('estadistica.xlsx', $ruta);
        Storage::disk(config('rem.archivos.disco'))->assertExists($ruta);
    }
}
