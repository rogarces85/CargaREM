<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\User;
use App\Models\ValidadorEjecucion;
use App\Services\ValidadorEstadisticaService;
use App\Validador\ArchivoNoValidable;
use App\Validador\CatalogoCalidad;
use App\Validador\ValidadorEstadistico;
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
 * 2. **Entiende el catálogo real.** Las 144 reglas de la Unidad se cargan y se
 *    evalúan; un archivo correcto no produce hallazgos y uno roto sí.
 * 3. **Se registra.** Cada ejecución queda con autor, archivo, versión de
 *    reglas y resultado (R-VAL-03).
 * 4. **Rechaza lo que no puede leer**, con motivo, en vez de aprobarlo.
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
     * Un Excel REM mínimo con la hoja NOMBRE armada como la plantilla oficial.
     *
     * @param  array<string, float>  $valores  celda => valor (en la hoja que se indique)
     * @param  list<string>  $hojasExtra
     */
    private function excelRem(
        array $valores = [],
        string $codigo = '123100',
        string $serie = 'A',
        string $mes = '01',
        array $hojasExtra = ['A01'],
        string $hojaValores = 'A01',
        ?string $version = 'Versión 1.2: Febrero 2026',
    ): UploadedFile {
        $libro = new Spreadsheet();

        // Hoja NOMBRE: comuna en C2:G2, establecimiento en C3:H3, mes en C6:D6.
        $nombre = $libro->getActiveSheet();
        $nombre->setTitle('NOMBRE');
        $nombre->setCellValue('A9', $version);
        $nombre->setCellValue('B2', 'Osorno');
        $nombre->setCellValue('B3', 'Hospital Base San José de Osorno');
        $nombre->setCellValue('B6', 'Enero');
        $nombre->setCellValue('B11', 'Responsable');
        $nombre->setCellValue('B12', 'Jefe de Estadística');

        // Comuna 10301 y código 123100 repartidos dígito por dígito.
        foreach (str_split('10301') as $i => $digito) {
            $nombre->setCellValue(chr(67 + $i) . '2', $digito); // C..G
        }

        foreach (str_split($codigo) as $i => $digito) {
            $nombre->setCellValue(chr(67 + $i) . '3', $digito); // C..H
        }

        foreach (str_split($mes) as $i => $digito) {
            $nombre->setCellValue(chr(67 + $i) . '6', $digito); // C..D
        }

        foreach ($hojasExtra as $titulo) {
            $hoja = $libro->createSheet();
            $hoja->setTitle($titulo);

            if ($titulo === $hojaValores) {
                foreach ($valores as $celda => $valor) {
                    $hoja->setCellValue((string) $celda, $valor);
                }
            }
        }

        $libro->setActiveSheetIndex(0);

        $temporal = tempnam(sys_get_temp_dir(), 'rem') . '.xlsx';
        (new Xlsx($libro))->save($temporal);
        $contenido = (string) file_get_contents($temporal);
        $libro->disconnectWorksheets();
        @unlink($temporal);

        return UploadedFile::fake()->createWithContent("{$codigo}{$serie}{$mes}.xlsx", $contenido);
    }

    public function test_un_archivo_sin_errores_no_deja_hallazgos(): void
    {
        $this->actingAs($this->encargado)
            ->post(route('validador.store'), [
                'archivo' => $this->excelRem([
                    // Las primeras reglas de A01 exigen cero en F11, F12, M11, M12.
                    'F11' => 0, 'F12' => 0, 'M11' => 0, 'M12' => 0,
                ]),
            ])
            ->assertRedirect();

        $ejecucion = ValidadorEjecucion::firstOrFail();

        $this->assertSame($this->encargado->id, $ejecucion->usuario_id);
        $this->assertSame('123100A01.xlsx', $ejecucion->nombre_original);
        $this->assertSame('A', $ejecucion->metadatos()['serie']);
        $this->assertSame('Hospital Base San José de Osorno', $ejecucion->metadatos()['establecimiento']);
    }

    public function test_una_regla_rota_produce_su_hallazgo(): void
    {
        // A01-VAL001 exige F11 == 0. Con 1 tiene que saltar.
        $this->actingAs($this->encargado)
            ->post(route('validador.store'), [
                'archivo' => $this->excelRem(['F11' => 1]),
            ]);

        $ejecucion = ValidadorEjecucion::firstOrFail();

        $this->assertSame(ValidadorEjecucion::CON_HALLAZGOS, $ejecucion->estado);

        $reglas = array_column($ejecucion->listaHallazgos(), 'regla');

        $this->assertContains('A01-VAL001', $reglas, 'La regla de F11 == 0 debe saltar con F11 = 1');
    }

    public function test_un_archivo_que_cumple_todo_no_se_marca(): void
    {
        $resultado = app(ValidadorEstadistico::class)->validar(
            $this->materializar($this->excelRem(['F11' => 0, 'F12' => 0, 'M11' => 0, 'M12' => 0])),
            '123100A01.xlsx',
        );

        $this->assertSame(0, $resultado->resumen->erroresBloqueantes);
        $this->assertSame('APROBADO', $resultado->resumen->veredicto);
    }

    public function test_el_validador_no_toca_ninguna_tabla_del_flujo(): void
    {
        $establecimiento = Establecimiento::create(['codigo' => '123100', 'nombre' => 'Hospital Base']);
        Periodo::create(['anio' => 2026, 'mes' => 1, 'estado' => Periodo::ABIERTO]);

        Envio::create([
            'establecimiento_id' => $establecimiento->id,
            'anio' => 2026, 'mes' => 1, 'estado' => Envio::RECIBIDO,
        ]);

        $antes = [
            'envios'             => Envio::count(),
            'validaciones'       => \DB::table('validaciones')->count(),
            'envio_archivos'     => \DB::table('envio_archivos')->count(),
            'envio_transiciones' => \DB::table('envio_transiciones')->count(),
        ];

        $this->actingAs($this->encargado)
            ->post(route('validador.store'), ['archivo' => $this->excelRem(['F11' => 1])]);

        // R-VAL-01: el módulo es una ayuda, no una etapa del proceso.
        $this->assertSame($antes['envios'], Envio::count());
        $this->assertSame($antes['validaciones'], \DB::table('validaciones')->count());
        $this->assertSame($antes['envio_archivos'], \DB::table('envio_archivos')->count());
        $this->assertSame($antes['envio_transiciones'], \DB::table('envio_transiciones')->count());

        $this->assertSame(1, ValidadorEjecucion::count());
    }

    public function test_un_nombre_fuera_de_formato_se_rechaza_con_motivo(): void
    {
        $archivo = UploadedFile::fake()->createWithContent('estadistica.xlsx', 'PK-zzz');

        $this->actingAs($this->encargado)
            ->post(route('validador.store'), ['archivo' => $archivo])
            ->assertSessionHasErrors('archivo');

        // El intento queda registrado como fallido, con su nombre.
        $this->assertSame(ValidadorEjecucion::FALLIDO, ValidadorEjecucion::firstOrFail()->estado);
    }

    public function test_un_establecimiento_fuera_de_la_nomina_se_rechaza(): void
    {
        $this->expectException(ArchivoNoValidable::class);

        app(ValidadorEstadistico::class)->validar(
            $this->materializar($this->excelRem([], codigo: '999999')),
            '999999A01.xlsx',
        );
    }

    public function test_la_serie_p_solo_acepta_los_meses_de_corte(): void
    {
        $this->expectException(ArchivoNoValidable::class);

        // La P se informa en 06 y 12: un archivo P03 no corresponde.
        app(ValidadorEstadistico::class)->validar(
            $this->materializar($this->excelRem([], serie: 'P', mes: '03', hojasExtra: ['P1'])),
            '123100P03.xlsx',
        );
    }

    public function test_el_administrador_ve_el_registro_de_uso(): void
    {
        $admin = User::factory()->create(['activo' => true]);
        $admin->assignRole('admin');

        $this->actingAs($this->encargado)
            ->post(route('validador.store'), ['archivo' => $this->excelRem()]);

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
            ->post(route('validador.store'), ['archivo' => $this->excelRem()]);

        $ejecucion = ValidadorEjecucion::firstOrFail();

        $otro = User::factory()->create(['activo' => true]);
        $otro->assignRole('encargado');

        $this->actingAs($otro)
            ->get(route('validador.show', $ejecucion))
            ->assertForbidden();
    }

    public function test_el_archivo_se_guarda_en_disco_privado_con_nombre_generado(): void
    {
        $resultado = app(ValidadorEstadisticaService::class)->ejecutar(
            $this->encargado,
            $this->excelRem(),
        );

        $ruta = $resultado['ejecucion']->ruta;

        $this->assertStringStartsWith('validador/', $ruta);
        $this->assertStringNotContainsString('123100A01.xlsx', $ruta);
        Storage::disk(config('rem.archivos.disco'))->assertExists($ruta);
    }

    public function test_la_pantalla_del_validador_muestra_el_catalogo(): void
    {
        $this->actingAs($this->encargado)
            ->get(route('validador.index'))
            ->assertOk()
            ->assertSee('Validador de calidad')
            ->assertSee('144 regla(s)');
    }

    /** Guarda el archivo subido en una ruta real para el motor. */
    private function materializar(UploadedFile $archivo): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'rem') . '.xlsx';
        copy($archivo->getRealPath(), $ruta);

        return $ruta;
    }
}
