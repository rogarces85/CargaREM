<?php

namespace Tests\Feature;

use App\Models\CatalogoSnapshot;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Plantilla;
use App\Models\PlantillaHoja;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use App\Services\EnvioService;
use App\Validacion\ValidadorArchivo;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Motor de validación: pipeline, V0 y V1 (T-F2-12 a T-F2-14).
 */
class ValidadorTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;
    private Envio $envio;
    private Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);
        $this->establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base', 'servicio_id' => $servicio->id,
        ]);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);
        CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $usuario = User::factory()->create(['activo' => true]);
        $usuario->assignRole('encargado');
        $usuario->establecimientos()->attach($this->establecimiento->id);
        $this->actingAs($usuario);

        $this->serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);

        // Plantilla y hojas en el catálogo: sin ellas V3 y V5 avisan de que no
        // pueden verificar, y un archivo correcto dejaría de estar limpio.
        $plantilla = Plantilla::create([
            'anio'          => 2026,
            'mes'           => 9,
            'serie_id'      => $this->serie->id,
            'serie_excel'   => 'SERIE A',
            'serie_interna' => 'SERIE A1',
            'version'       => '1.2',
            'snapshot_id'   => \App\Models\CatalogoSnapshot::activo()->first()->id,
        ]);
        PlantillaHoja::create([
            'anio' => 2026, 'serie_interna' => 'SERIE A1', 'hoja' => 'NOMBRE', 'obligatoria' => true,
        ]);
        $this->envio = app(EnvioService::class)->crear($this->establecimiento, 2026, 9, $usuario);

        // Matriz: la serie del archivo es la única exigida, así V11 no
        // añade avisos de completitud que no son el objeto de esta prueba.
        $this->envio->establecimiento->series()->attach($this->serie->id, ['obligatoria' => true]);
    }

    /** .xlsx real con cabecera válida, para que V2 tenga algo que leer. */
    private function workbookValido(): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('NOMBRE');
        $hoja->setCellValue('C3', '123100');
        $hoja->setCellValue('C6', '09');
        $hoja->setCellValue('B7', '2026');
        $hoja->setCellValue('B17', 'SERIE A');
        $hoja->setCellValue('A9', 'Version 1.2');

        $ruta = tempnam(sys_get_temp_dir(), 'rem').'.xlsx';
        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return $ruta;
    }

    private function subir(string $nombre, ?string $contenido = null): EnvioArchivo
    {
        $contenido ??= file_get_contents($this->workbookValido());

        return app(EnvioService::class)->subirVersion(
            $this->envio,
            $this->serie,
            UploadedFile::fake()->createWithContent($nombre, $contenido),
            auth()->user(),
        );
    }

    /**
     * Coloca un archivo directamente en el almacén, sin pasar por la subida.
     *
     * La subida ya valida V0, así que un archivo corrupto solo puede aparecer
     * por otra vía: corrupción posterior o un catálogo subido antes de que la
     * regla existiera. Es exactamente el caso que V0 en validación cubre.
     */
    private function archivoDirecto(string $nombre, string $contenido): EnvioArchivo
    {
        $ruta = 'envios/2026/09/123100/v9/'.uniqid('directo_', true).'.'.pathinfo($nombre, PATHINFO_EXTENSION);
        Storage::disk('local')->put($ruta, $contenido);

        return $this->envio->archivos()->create([
            'serie_id'        => $this->serie->id,
            'version'         => 9,
            'es_vigente'      => true,
            'nombre_original' => $nombre,
            'ruta'            => $ruta,
            'disco'           => 'local',
            'sha256'          => hash('sha256', $contenido),
            'tamano_bytes'    => strlen($contenido),
            'mime'            => 'application/octet-stream',
            'estado'          => EnvioArchivo::SUBIDO,
            'snapshot_id'     => \App\Models\CatalogoSnapshot::activo()->first()->id,
            'subido_por'      => auth()->id(),
        ]);
    }
    private function validador(): ValidadorArchivo
    {
        return app(ValidadorArchivo::class);
    }

    public function test_un_archivo_correcto_queda_valido_y_sin_hallazgos(): void
    {
        $archivo = $this->subir('123100A09.xlsx');

        $resultado = $this->validador()->validar($archivo);

        $this->assertTrue($resultado->esValido());
        $this->assertSame(0, $resultado->bloqueantes());
        $this->assertSame(EnvioArchivo::VALIDO, $archivo->fresh()->estado);
    }

    public function test_un_pdf_renombrado_falla_en_v0(): void
    {
        $archivo = $this->archivoDirecto('123100A09.xlsx', '%PDF-1.7 contenido falso');

        $resultado = $this->validador()->validar($archivo);

        $this->assertFalse($resultado->esValido());
        $this->assertSame('ENV-003', $resultado->hallazgos[0]->codigo);
        $this->assertSame('V0', $resultado->hallazgos[0]->etapa);
        $this->assertSame(EnvioArchivo::CON_ERRORES, $archivo->fresh()->estado);
    }

    public function test_un_nombre_fuera_de_convencion_falla_en_v1(): void
    {
        $archivo = $this->subir('reporte final.xlsx');

        $resultado = $this->validador()->validar($archivo);

        $this->assertSame('ENV-005', $resultado->hallazgos[0]->codigo);
        $this->assertSame('V1', $resultado->hallazgos[0]->etapa);
    }

    public function test_el_mes_del_nombre_debe_coincidir_con_el_periodo(): void
    {
        $archivo = $this->subir('123100A08.xlsx');

        $resultado = $this->validador()->validar($archivo);

        $codigos = array_map(fn ($h) => $h->codigo, $resultado->hallazgos);

        $this->assertContains('ENV-007', $codigos);
    }

    public function test_el_codigo_del_nombre_debe_coincidir_con_el_establecimiento(): void
    {
        $archivo = $this->subir('999999A09.xlsx');

        $resultado = $this->validador()->validar($archivo);

        $codigos = array_map(fn ($h) => $h->codigo, $resultado->hallazgos);

        $this->assertContains('ENV-008', $codigos);
    }

    public function test_una_serie_inexistente_en_el_nombre_falla(): void
    {
        $archivo = $this->subir('123100ZZ09.xlsx');

        $resultado = $this->validador()->validar($archivo);

        $codigos = array_map(fn ($h) => $h->codigo, $resultado->hallazgos);

        $this->assertContains('ENV-006', $codigos);
    }

    public function test_tolera_sufijos_de_correccion(): void
    {
        $archivo = $this->subir('123100A09 (1).xlsx');

        $resultado = $this->validador()->validar($archivo);

        $this->assertTrue($resultado->esValido(), 'Un sufijo de corrección no es un error de forma');
    }

    public function test_un_bloqueante_detiene_la_cadena(): void
    {
        // Firma inválida (V0 bloqueante) y además nombre malo (V1).
        $archivo = $this->archivoDirecto('malo.xlsx', '%PDF-1.7 x');

        $resultado = $this->validador()->validar($archivo);

        $this->assertCount(1, $resultado->hallazgos, 'V1 no debe ejecutarse tras un bloqueante en V0');
        $this->assertSame('ENV-003', $resultado->hallazgos[0]->codigo);
    }

    public function test_los_hallazgos_quedan_en_la_tabla_validaciones(): void
    {
        $archivo = $this->subir('123100A08.xlsx');

        $this->validador()->validar($archivo);

        $this->assertDatabaseHas('validaciones', [
            'envio_archivo_id' => $archivo->id,
            'codigo'           => 'ENV-007',
            'severidad'        => 'bloqueante',
            'etapa'            => 'V1',
        ]);
    }

    public function test_revalidar_regenera_el_reporte_sin_duplicar(): void
    {
        $archivo = $this->subir('123100A08.xlsx');

        $this->validador()->validar($archivo);
        $primera = $archivo->validaciones()->count();

        app(EnvioService::class)->revalidar($this->envio);

        $this->assertSame($primera, $archivo->fresh()->validaciones()->count());
    }

    public function test_las_macros_se_avisan_sin_bloquear(): void
    {
        $archivo = $this->subir('123100A09.xlsm');

        $resultado = $this->validador()->validar($archivo);

        $this->assertTrue($resultado->esValido(), 'Una macro no bloquea: no se ejecuta');
        $this->assertSame(1, $resultado->advertencias());
    }
}
