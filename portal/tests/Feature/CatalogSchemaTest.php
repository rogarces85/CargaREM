<?php

namespace Tests\Feature;

use App\Models\Diccionario;
use App\Models\Establecimiento;
use App\Models\Periodo;
use App\Models\Serie;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * El esquema de catálogos (T-F1-18) y sus modelos (T-F1-19).
 */
class CatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_existen_las_tablas_de_catalogos(): void
    {
        foreach ([
            'servicios', 'establecimientos', 'series', 'periodos',
            'establecimiento_serie', 'usuario_establecimiento',
            'plantillas', 'plantilla_hojas', 'plantilla_cabecera',
            'diccionario',
        ] as $tabla) {
            $this->assertTrue(
                Schema::hasTable($tabla),
                "Falta la tabla {$tabla}"
            );
        }
    }

    public function test_el_mvp_v2_no_tiene_snapshots_ni_excepciones(): void
    {
        $this->assertFalse(Schema::hasTable('catalogo_snapshots'));
        $this->assertFalse(Schema::hasTable('excepciones'));
        $this->assertFalse(Schema::hasColumn('envio_archivos', 'snapshot_id'));
        $this->assertFalse(Schema::hasColumn('diccionario', 'snapshot_id'));
    }

    public function test_el_catalogo_de_servicios_y_sus_columnas_existen(): void
    {
        $this->assertTrue(Schema::hasTable('servicios'));
        $this->assertTrue(Schema::hasColumn('establecimientos', 'servicio_id'));
        $this->assertTrue(Schema::hasColumn('users', 'servicio_id'));
    }

    public function test_el_periodo_es_unico_por_anio_y_mes(): void
    {
        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);

        $this->expectException(QueryException::class);

        Periodo::create(['anio' => 2026, 'mes' => 9, 'estado' => Periodo::ABIERTO]);
    }

    public function test_la_serie_tiene_relacion_con_establecimientos_y_plantillas(): void
    {
        $estab = Establecimiento::create([
            'codigo' => '123100',
            'nombre' => 'Hospital Base',
        ]);
        $serie = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);

        $estab->series()->attach($serie->id, ['obligatoria' => true]);

        $this->assertCount(1, $estab->fresh()->series);
        $this->assertTrue((bool) $estab->series->first()->pivot->obligatoria);
    }

    public function test_el_diccionario_se_consulta_por_anio_serie_y_hoja(): void
    {
        Diccionario::insert([
            [ 'anio' => 2026, 'serie' => 'A', 'hoja' => 'A01', 'codigo_prestacion' => '01010100', 'linea' => 5, 'inicio' => 'C', 'fin' => 'L'],
            [ 'anio' => 2026, 'serie' => 'A', 'hoja' => 'A02', 'codigo_prestacion' => '01010101', 'linea' => 6, 'inicio' => 'C', 'fin' => 'L'],
            [ 'anio' => 2026, 'serie' => 'BS', 'hoja' => 'B01', 'codigo_prestacion' => '02020200', 'linea' => 7, 'inicio' => 'C', 'fin' => 'L'],
        ]);

        $filas = Diccionario::deHoja(2026, 'A', 'A01')->get();

        $this->assertCount(1, $filas);
        $this->assertSame('01010100', $filas->first()->codigo_prestacion);
    }
}
