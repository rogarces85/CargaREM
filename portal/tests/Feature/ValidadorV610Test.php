<?php

namespace Tests\Feature;

use App\Models\CatalogoSnapshot;
use App\Models\Diccionario;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Excepcion;
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
 * V6 a V10 — datos del archivo (T-F2-19 a T-F2-21).
 */
class ValidadorV610Test extends TestCase
{
    use RefreshDatabase;

    private Envio $envio;
    private Serie $serie;
    private CatalogoSnapshot $snapshot;
    private User $usuario;

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
        $this->snapshot = CatalogoSnapshot::create(['origen' => 'prueba', 'activo' => true]);

        $this->usuario = User::factory()->create(['activo' => true]);
        $this->usuario->assignRole('encargado');
        $this->usuario->establecimientos()->attach($establecimiento->id);
        $this->actingAs($this->usuario);

        $this->serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        $this->envio = app(EnvioService::class)->crear($establecimiento, 2026, 9, $this->usuario);

        Plantilla::create([
            'anio' => 2026, 'mes' => 9, 'serie_id' => $this->serie->id,
            'serie_excel' => 'SERIE A', 'serie_interna' => 'SERIE A1',
            'version' => '1.2', 'snapshot_id' => $this->snapshot->id,
        ]);
        PlantillaHoja::create([
            'anio' => 2026, 'serie_interna' => 'SERIE A1', 'hoja' => 'NOMBRE', 'obligatoria' => true,
        ]);
        PlantillaHoja::create([
            'anio' => 2026, 'serie_interna' => 'SERIE A1', 'hoja' => 'A01', 'obligatoria' => true,
        ]);
    }

    private function prestacion(string $codigo, int $linea, string $inicio = 'C', string $fin = 'D'): Diccionario
    {
        return Diccionario::create([
            'snapshot_id' => $this->snapshot->id,
            'anio'        => 2026,
            'mes'         => null,
            'serie'       => 'SERIE A1',
            'hoja'        => 'A01',
            'codigo_prestacion' => $codigo,
            'texto_prestacion'  => "Prestación {$codigo}",
            'linea'       => $linea,
            'inicio'      => $inicio,
            'fin'         => $fin,
            'tipo_dato'   => 'N',
        ]);
    }

    /** @param array<string, mixed> $valoresA01 celda => valor */
    private function archivo(array $valoresA01): EnvioArchivo
    {
        $libro = new Spreadsheet();
        $nombre = $libro->getActiveSheet();
        $nombre->setTitle('NOMBRE');
        foreach (['C3' => '123100', 'C6' => '09', 'B7' => '2026', 'B17' => 'SERIE A', 'A9' => 'Version 1.2'] as $c => $v) {
            $nombre->setCellValue($c, $v);
        }

        $hoja = $libro->createSheet();
        $hoja->setTitle('A01');
        foreach ($valoresA01 as $celda => $valor) {
            $hoja->setCellValue($celda, $valor);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rem').'.xlsx';
        (new Xlsx($libro))->save($tmp);
        $libro->disconnectWorksheets();

        $contenido = file_get_contents($tmp);
        $ruta = 'envios/2026/09/123100/v9/'.uniqid('v610_', true).'.xlsx';
        Storage::disk('local')->put($ruta, $contenido);

        return $this->envio->archivos()->create([
            'serie_id'        => $this->serie->id,
            'version'         => 9,
            'es_vigente'      => true,
            'nombre_original' => '123100A09.xlsx',
            'ruta'            => $ruta,
            'disco'           => 'local',
            'sha256'          => hash('sha256', $contenido),
            'tamano_bytes'    => strlen($contenido),
            'mime'            => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'estado'          => EnvioArchivo::SUBIDO,
            'snapshot_id'     => $this->snapshot->id,
            'subido_por'      => $this->usuario->id,
        ]);
    }

    /** @return list<string> */
    private function codigos(EnvioArchivo $archivo): array
    {
        return array_map(
            fn ($h) => $h->codigo,
            app(ValidadorArchivo::class)->validar($archivo)->hallazgos
        );
    }

    public function test_v6_avisa_una_prestacion_sin_datos(): void
    {
        $this->prestacion('01010100', 5);

        $codigos = $this->codigos($this->archivo([]));

        // Sin datos también dispara ENV-024: el archivo entero está vacío.
        $this->assertContains('ENV-018', $codigos);
        $this->assertContains('ENV-024', $codigos);
    }

    public function test_v6_bloquea_un_rango_ilegible(): void
    {
        // Rango invertido: el diccionario declara fin antes que inicio.
        $this->prestacion('01010100', 5, 'D', 'C');

        $this->assertSame(['ENV-019'], $this->codigos($this->archivo(['C5' => 1, 'D5' => 2])));
    }

    public function test_v7_avisa_un_valor_no_numerico(): void
    {
        $this->prestacion('01010100', 5);

        $this->assertSame(['ENV-020'], $this->codigos($this->archivo(['C5' => 'N/A', 'D5' => 2])));
    }

    public function test_v7_avisa_decimales_donde_se_exigen_enteros(): void
    {
        $this->prestacion('01010100', 5);

        $this->assertSame(['ENV-021'], $this->codigos($this->archivo(['C5' => 1.5, 'D5' => 2])));
    }

    public function test_una_serie_bs_si_acepta_decimales(): void
    {
        // La serie de la plantilla y del archivo pasa a BS, que admite decimal.
        $this->serie->update(['codigo' => 'BS']);
        $this->envio->update([]);

        $libro = new Spreadsheet();
        $nombre = $libro->getActiveSheet();
        $nombre->setTitle('NOMBRE');
        foreach (['C3' => '123100', 'C6' => '09', 'B7' => '2026', 'B17' => 'SERIE BS', 'A9' => 'Version 1.2'] as $c => $v) {
            $nombre->setCellValue($c, $v);
        }
        $hoja = $libro->createSheet();
        $hoja->setTitle('A01');
        $hoja->setCellValue('C5', 1.5);
        $hoja->setCellValue('D5', 2);

        $tmp = tempnam(sys_get_temp_dir(), 'rem').'.xlsx';
        (new Xlsx($libro))->save($tmp);
        $libro->disconnectWorksheets();

        $this->prestacion('01010100', 5);

        $contenido = file_get_contents($tmp);
        $ruta = 'envios/2026/09/123100/v9/'.uniqid('bs_', true).'.xlsx';
        Storage::disk('local')->put($ruta, $contenido);

        $archivo = $this->envio->archivos()->create([
            'serie_id' => $this->serie->id, 'version' => 8, 'es_vigente' => true,
            'nombre_original' => '123100BS09.xlsx', 'ruta' => $ruta, 'disco' => 'local',
            'sha256' => hash('sha256', $contenido), 'tamano_bytes' => strlen($contenido),
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'estado' => EnvioArchivo::SUBIDO, 'snapshot_id' => $this->snapshot->id,
            'subido_por' => $this->usuario->id,
        ]);

        $this->assertSame([], $this->codigos($archivo));
    }

    public function test_v8_avisa_una_columna_que_no_aplica_con_valor(): void
    {
        $this->prestacion('01010100', 5);

        Excepcion::create([
            'snapshot_id' => $this->snapshot->id,
            'anio'        => 2026,
            'mes'         => null,
            'serie'       => 'SERIE A1',
            'hoja'        => 'A01',
            'codigo_prestacion' => '01010100',
            'columna'     => 'C',
            'motivo'      => 'No aplica',
        ]);

        $this->assertSame(['ENV-022'], $this->codigos($this->archivo(['C5' => 7, 'D5' => 2])));
    }

    public function test_v9_avisa_de_una_prestacion_duplicada(): void
    {
        $this->prestacion('01010100', 5);
        $this->prestacion('01010100', 6);

        $this->assertSame(['ENV-023'], $this->codigos($this->archivo(['C5' => 1, 'D5' => 2, 'C6' => 3, 'D6' => 4])));
    }

    public function test_v10_bloquea_un_archivo_sin_ningun_dato(): void
    {
        $this->prestacion('01010100', 5);
        $this->prestacion('01010101', 6);

        $codigos = $this->codigos($this->archivo([]));

        $this->assertContains('ENV-024', $codigos);
    }

    public function test_un_archivo_con_datos_no_tiene_env_024(): void
    {
        $this->prestacion('01010100', 5);

        $codigos = $this->codigos($this->archivo(['C5' => 1, 'D5' => 2]));

        $this->assertNotContains('ENV-024', $codigos);
    }
}
