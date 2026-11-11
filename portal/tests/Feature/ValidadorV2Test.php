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
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * V2 — cabecera interna del archivo (T-F2-15).
 */
class ValidadorV2Test extends TestCase
{
    use RefreshDatabase;

    private Envio $envio;
    private Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $servicio = Servicio::create(['codigo' => '23', 'nombre' => 'Osorno']);
        $establecimiento = Establecimiento::create([
            'codigo' => '123100', 'nombre' => 'Hospital Base', 'servicio_id' => $servicio->id,
        ]);
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);
        CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $usuario = User::factory()->create(['activo' => true]);
        $usuario->assignRole('encargado');
        $usuario->establecimientos()->attach($establecimiento->id);
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
        $this->envio = app(EnvioService::class)->crear($establecimiento, 2026, 9, $usuario);

        // Matriz: la serie del archivo es la única exigida, así V11 no
        // añade avisos de completitud que no son el objeto de esta prueba.
        $this->envio->establecimiento->series()->attach($this->serie->id, ['obligatoria' => true]);
    }

    /**
     * Construye un .xlsx real con la cabecera en las celdas del validador.
     *
     * @param  array<string, string>  $celdas
     */
    private function excelCon(array $celdas, string $nombreHoja = 'NOMBRE'): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($nombreHoja);

        foreach ($celdas as $celda => $valor) {
            $hoja->setCellValue($celda, $valor);
        }

        $ruta = tempnam(sys_get_temp_dir(), 'rem').'.xlsx';
        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return $ruta;
    }

    /** Archivo en el almacén, con su fila, sin pasar por la subida. */
    private function archivo(string $nombre, string $rutaExcel): EnvioArchivo
    {
        $contenido = file_get_contents($rutaExcel);
        $ruta = 'envios/2026/09/123100/v9/'.uniqid('v2_', true).'.xlsx';
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
            'mime'            => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'estado'          => EnvioArchivo::SUBIDO,
            'snapshot_id'     => CatalogoSnapshot::activo()->first()->id,
            'subido_por'      => auth()->id(),
        ]);
    }

    private function cabecera(array $cambios = []): array
    {
        return array_merge([
            'C3'  => '123100',
            'C6'  => '09',
            'B7'  => '2026',
            'B17' => 'SERIE A',
            'A9'  => 'Version 1.2',
        ], $cambios);
    }

    private function codigos(EnvioArchivo $archivo): array
    {
        $resultado = app(ValidadorArchivo::class)->validar($archivo);

        return array_map(fn ($h) => $h->codigo, $resultado->hallazgos);
    }

    public function test_una_cabecera_correcta_no_produce_hallazgos(): void
    {
        $archivo = $this->archivo('123100A09.xlsx', $this->excelCon($this->cabecera()));

        $this->assertSame([], $this->codigos($archivo));
    }

    public function test_un_anio_distinto_falla_con_env_011(): void
    {
        $archivo = $this->archivo('123100A09.xlsx', $this->excelCon($this->cabecera(['B7' => '2025'])));

        $this->assertSame(['ENV-011'], $this->codigos($archivo));
    }

    public function test_un_mes_distinto_falla_con_env_012(): void
    {
        $archivo = $this->archivo('123100A09.xlsx', $this->excelCon($this->cabecera(['C6' => '08'])));

        $this->assertSame(['ENV-012'], $this->codigos($archivo));
    }

    public function test_una_serie_distinta_falla_con_env_010(): void
    {
        $archivo = $this->archivo('123100A09.xlsx', $this->excelCon($this->cabecera(['B17' => 'SERIE BS'])));

        $this->assertSame(['ENV-010'], $this->codigos($archivo));
    }

    public function test_un_establecimiento_distinto_falla_con_env_013(): void
    {
        $archivo = $this->archivo('123100A09.xlsx', $this->excelCon($this->cabecera(['C3' => '999999'])));

        $this->assertSame(['ENV-013'], $this->codigos($archivo));
    }

    public function test_una_cabecera_vacia_no_se_puede_identificar(): void
    {
        $archivo = $this->archivo('123100A09.xlsx', $this->excelCon(['A1' => 'nada']));

        $this->assertSame(['ENV-009'], $this->codigos($archivo));
    }

    public function test_el_mes_se_acepta_por_nombre(): void
    {
        $archivo = $this->archivo('123100A09.xlsx', $this->excelCon($this->cabecera(['C6' => 'SEPTIEMBRE', 'D6' => ''])));

        $this->assertSame([], $this->codigos($archivo));
    }

    public function test_un_libro_ilegible_falla_con_env_009(): void
    {
        // Firma ZIP correcta para pasar V0, pero no es un libro de verdad.
        $ruta = tempnam(sys_get_temp_dir(), 'roto').'.xlsx';
        file_put_contents($ruta, "PK\x03\x04".str_repeat('X', 2000));

        $archivo = $this->archivo('123100A09.xlsx', $ruta);

        $this->assertSame(['ENV-009'], $this->codigos($archivo));
    }
}
