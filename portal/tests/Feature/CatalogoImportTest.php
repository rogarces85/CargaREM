<?php

namespace Tests\Feature;

use App\Exceptions\CatalogoInvalidoException;
use App\Models\CatalogoSnapshot;
use App\Models\Diccionario;
use App\Models\Establecimiento;
use App\Models\PlantillaHoja;
use App\Models\Serie;
use App\Services\CatalogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Importador de catálogos (T-F1-20) y activación del snapshot (T-F1-21).
 */
class CatalogoImportTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = base_path('tests/fixtures/catalogo');
    }

    private function servicio(): CatalogoService
    {
        return app(CatalogoService::class);
    }

    public function test_importa_un_snapshot_completo_y_lo_deja_inactivo(): void
    {
        $snapshot = $this->servicio()->importarDirectorio($this->fixture);

        $this->assertFalse($snapshot->activo, 'Importar nunca activa: eso es un acto aparte');
        $this->assertSame(2, $snapshot->filas_diccionario);
        $this->assertSame(2, Establecimiento::count());
        $this->assertSame(2, Serie::count(), 'SERIE A y SERIE A1 colapsan en una serie externa');
        $this->assertSame(2, Diccionario::count());
        $this->assertSame(3, PlantillaHoja::count());
    }

    public function test_conserva_la_serie_interna_en_el_diccionario(): void
    {
        $this->servicio()->importarDirectorio($this->fixture);

        $fila = Diccionario::first();

        $this->assertSame('SERIE A1', $fila->serie, 'El diccionario se indexa por el nombre interno');
        $this->assertSame('Consulta medica', $fila->texto_prestacion);
    }

    public function test_lee_campos_con_coma_entre_comillas(): void
    {
        $this->servicio()->importarDirectorio($this->fixture);

        $fila = Diccionario::where('codigo_prestacion', '01010101')->first();

        $this->assertNotNull($fila, 'Un campo entrecomillado con coma no debe romper la fila');
        $this->assertSame('Control con texto, con coma', $fila->texto_prestacion);
    }

    public function test_solo_un_snapshot_queda_activo(): void
    {
        $primero = $this->servicio()->importarDirectorio($this->fixture);
        $this->servicio()->activar($primero);

        $segundo = $this->servicio()->importarDirectorio($this->fixture);
        $this->servicio()->activar($segundo);

        $this->assertSame(1, CatalogoSnapshot::where('activo', true)->count());
        $this->assertTrue($segundo->fresh()->activo);
        $this->assertFalse($primero->fresh()->activo);
    }

    public function test_falla_si_falta_un_archivo(): void
    {
        $copia = $this->copiarFixture();
        File::delete($copia.'/diccionario.csv');

        try {
            $this->servicio()->importarDirectorio($copia);
            $this->fail('Debería haber lanzado excepción');
        } catch (CatalogoInvalidoException $e) {
            $this->assertSame('APP-CAT-001', $e->codigo);
        } finally {
            File::deleteDirectory($copia);
        }
    }

    public function test_falla_si_las_columnas_no_coinciden(): void
    {
        $copia = $this->copiarFixture();
        File::put($copia.'/series.csv', "OtraCosa,SerieExcel\nSERIE A,SERIE A\n");

        try {
            $this->servicio()->importarDirectorio($copia);
            $this->fail('Debería haber lanzado excepción');
        } catch (CatalogoInvalidoException $e) {
            $this->assertSame('APP-CAT-002', $e->codigo);
        } finally {
            File::deleteDirectory($copia);
        }
    }

    public function test_falla_si_el_manifiesto_declara_otro_conteo(): void
    {
        $copia = $this->copiarFixture();
        File::put($copia.'/manifest.json', json_encode([
            'tablas' => [['Archivo' => 'diccionario.csv', 'Filas' => 99]],
        ]));

        try {
            $this->servicio()->importarDirectorio($copia);
            $this->fail('Debería haber lanzado excepción');
        } catch (CatalogoInvalidoException $e) {
            $this->assertSame('APP-CAT-005', $e->codigo);
        } finally {
            File::deleteDirectory($copia);
        }
    }

    public function test_una_importacion_fallida_no_deja_snapshot(): void
    {
        $copia = $this->copiarFixture();
        // Sin manifiesto, el primer control que falla es el de columnas.
        File::delete($copia.'/manifest.json');
        File::put($copia.'/diccionario.csv', "CodigoPrestacion,Mes,Year,TextoPrestacion,Hoja,Serie,Posicion,tipoDato,linea,Inicio,Fin\nfila,con,pocas,columnas\n");

        try {
            $this->servicio()->importarDirectorio($copia);
            $this->fail('Debería haber lanzado excepción');
        } catch (CatalogoInvalidoException $e) {
            $this->assertSame('APP-CAT-002', $e->codigo);
            $this->assertSame(0, CatalogoSnapshot::count(), 'La transacción no debe dejar restos');
        } finally {
            File::deleteDirectory($copia);
        }
    }

    private function copiarFixture(): string
    {
        $destino = storage_path('framework/testing/catalogo_'.uniqid());

        File::copyDirectory($this->fixture, $destino);

        return $destino;
    }
}
