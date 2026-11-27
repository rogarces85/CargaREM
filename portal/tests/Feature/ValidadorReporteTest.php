<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ValidadorEjecucion;
use App\Services\ValidadorReporteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Reporte descargable del Validador (RF-040.3).
 *
 * Lo que se fija aquí es que el reporte sea **legible igual** que el del
 * Validador de escritorio: mismas tres hojas, mismas doce columnas, mismas
 * cifras de resumen. Un revisor que conoce una herramienta no debe reaprender
 * la otra.
 *
 * Los datos de la ejecución son los de un archivo real de la serie A: 87
 * reglas evaluadas, un error bloqueante y dieciséis reglas que no
 * corresponden al establecimiento.
 */
class ValidadorReporteTest extends TestCase
{
    use RefreshDatabase;

    private User $encargado;
    private User $otro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->encargado = User::factory()->create(['activo' => true]);
        $this->encargado->assignRole('encargado');

        $this->otro = User::factory()->create(['activo' => true]);
        $this->otro->assignRole('encargado');
    }

    /** Ejecución con el resultado que produce un archivo REM real. */
    private function ejecucion(?User $duenio = null): ValidadorEjecucion
    {
        return ValidadorEjecucion::create([
            'usuario_id'      => ($duenio ?? $this->encargado)->id,
            'nombre_original' => '123100A09.xlsm',
            'ruta'            => 'validador/2026/11/1/abc.xlsm',
            'sha256'          => str_repeat('a', 64),
            'tamano_bytes'    => 1024,
            'reglas_version'  => '2026.09',
            'hallazgos'       => 1,
            'bloqueantes'     => 1,
            'advertencias'    => 0,
            'duracion_ms'     => 1200,
            'estado'          => ValidadorEjecucion::CON_HALLAZGOS,
            'resultado'       => $this->resultado(),
        ]);
    }

    /** @return array<string, mixed> */
    private function resultado(): array
    {
        return [
            'metadatos' => [
                'nombre'          => '123100A09.xlsm',
                'codigo'          => '123100',
                'serie'           => 'A',
                'mes'             => '09',
                'extension'       => 'xlsm',
                'establecimiento' => 'Hospital Base San José de Osorno',
                'tipo'            => 'HOSPITAL',
            ],
            'resumen' => [
                'evaluadas'       => 51,
                'cumplen'         => 50,
                'hallazgos'       => 1,
                'omitidas'        => 36,
                'erroresTecnicos' => 0,
                'fueraDeAlcance'  => 16,
                'hojasAusentes'   => 0,
                'errores'         => 1,
                'revisar'         => 0,
                'bloqueantes'     => 1,
                'porcentaje'      => 98.0,
                'veredicto'       => 'CON_OBSERVACIONES',
            ],
            'errorVersion'     => null,
            'hojasAusentes'    => [],
            'serieSoloPortada' => false,
            'duracionMs'       => 1200,
            'reglasVersion'    => '2026.09',
            'hallazgos'        => [$this->reglaError()],
            'resultados'       => [
                $this->reglaCumple(),
                $this->reglaError(),
                $this->reglaOmitida(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function reglaCumple(): array
    {
        return [
            'regla' => 'A01-VAL001', 'severidad' => 'REVISAR', 'estado' => 'CUMPLE',
            'mensaje' => 'REM A01 | Control Preconcepcional en edades extremas',
            'hoja' => 'A01', 'hoja2' => null,
            'expresion1' => 'F11', 'valor1' => 0.0, 'operador' => '==',
            'expresion2' => '0', 'valor2' => 0.0, 'diferencia' => 0.0,
            'celdas' => ['A01!F11'], 'omision' => null,
            'evidencia' => 'F11 = 0; 0 = 0. Se esperaba 0 == 0.',
            'bloquea' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function reglaError(): array
    {
        return [
            'regla' => 'A05-VAL005', 'severidad' => 'ERROR', 'estado' => 'HALLAZGO',
            'mensaje' => 'REM A05 | Ingresos al programa',
            'hoja' => 'A05', 'hoja2' => null,
            'expresion1' => 'C204', 'valor1' => 15.0, 'operador' => '<=',
            'expresion2' => 'C205+C206', 'valor2' => 10.0, 'diferencia' => 5.0,
            'celdas' => ['A05!C204', 'A05!C205', 'A05!C206'], 'omision' => null,
            'evidencia' => 'C204 = 15; C205+C206 = 10. Se esperaba 15 <= 10.',
            'bloquea' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function reglaOmitida(): array
    {
        return [
            'regla' => 'A08-VAL001a', 'severidad' => 'REVISAR', 'estado' => 'OMITIDA',
            'mensaje' => 'REM A08 | Sección exclusiva del titular',
            'hoja' => 'A08', 'hoja2' => null,
            'expresion1' => 'E12+E13', 'valor1' => 0.0, 'operador' => '>',
            'expresion2' => '0', 'valor2' => 0.0, 'diferencia' => null,
            'celdas' => ['A08!E12', 'A08!E13'],
            'omision' => 'SECCION_SIN_ACTIVIDAD',
            'evidencia' => '',
            'bloquea' => true,
        ];
    }

    private function libro(ValidadorEjecucion $ejecucion)
    {
        return app(ValidadorReporteService::class)->construir($ejecucion);
    }

    public function test_el_reporte_tiene_las_tres_hojas_del_escritorio(): void
    {
        $libro = $this->libro($this->ejecucion());

        $this->assertSame(['Resumen', 'Resultados', 'Solo Errores'], $libro->getSheetNames());
    }

    public function test_la_hoja_de_resultados_trae_las_doce_columnas_del_escritorio(): void
    {
        $hoja = $this->libro($this->ejecucion())->getSheetByName('Resultados');

        $esperadas = [
            'Hoja REM', 'Regla', 'Severidad', 'Estado', 'Detalle',
            'Expresión evaluada', 'Valor obtenido', 'Comparación',
            'Valor esperado', 'Diferencia', 'Celdas leídas', 'Observación',
        ];

        foreach ($esperadas as $indice => $titulo) {
            $this->assertSame(
                $titulo,
                $hoja->getCell([$indice + 1, 1])->getValue(),
                "La columna {$indice} debe llamarse «{$titulo}»"
            );
        }
    }

    /**
     * La hoja trae TODAS las reglas, no solo las que fallan: es lo que hace
     * auditable el veredicto.
     */
    public function test_la_hoja_de_resultados_trae_todas_las_reglas_evaluadas(): void
    {
        $hoja = $this->libro($this->ejecucion())->getSheetByName('Resultados');

        // Encabezado + 3 resultados.
        $this->assertSame(4, $hoja->getHighestRow());

        $this->assertSame('A01-VAL001', $hoja->getCell('B2')->getValue());
        $this->assertSame('A05-VAL005', $hoja->getCell('B3')->getValue());
        $this->assertSame('A08-VAL001a', $hoja->getCell('B4')->getValue());
    }

    public function test_la_hoja_solo_errores_trae_unicamente_los_bloqueantes(): void
    {
        $hoja = $this->libro($this->ejecucion())->getSheetByName('Solo Errores');

        // Encabezado + 1 error.
        $this->assertSame(2, $hoja->getHighestRow());
        $this->assertSame('A05-VAL005', $hoja->getCell('B2')->getValue());
        $this->assertSame('Error', $hoja->getCell('C2')->getValue());
        $this->assertSame('No Cumple', $hoja->getCell('D2')->getValue());
    }

    /** Los estados se leen como en el escritorio, no como los nombra el motor. */
    public function test_los_estados_y_severidades_se_escriben_en_castellano(): void
    {
        $hoja = $this->libro($this->ejecucion())->getSheetByName('Resultados');

        $this->assertSame('Cumple', $hoja->getCell('D2')->getValue());
        $this->assertSame('No Cumple', $hoja->getCell('D3')->getValue());
        $this->assertSame('No Aplica', $hoja->getCell('D4')->getValue());

        $this->assertSame('Revisar', $hoja->getCell('C2')->getValue());
        $this->assertSame('Error', $hoja->getCell('C3')->getValue());
    }

    /** Una regla omitida explica por qué, no deja la celda vacía. */
    public function test_una_regla_omitida_explica_el_motivo(): void
    {
        $hoja = $this->libro($this->ejecucion())->getSheetByName('Resultados');

        $this->assertSame(
            'La sección corresponde al establecimiento, pero no trae registro este mes.',
            $hoja->getCell('L4')->getValue()
        );
    }

    public function test_el_resumen_reproduce_las_cifras_del_escritorio(): void
    {
        $hoja = $this->libro($this->ejecucion())->getSheetByName('Resumen');

        $cifras = [];
        foreach (range(1, $hoja->getHighestRow()) as $fila) {
            $rotulo = $hoja->getCell("A{$fila}")->getValue();
            if (is_string($rotulo) && $rotulo !== '') {
                $cifras[$rotulo] = $hoja->getCell("B{$fila}")->getValue();
            }
        }

        $this->assertSame('Requiere corrección', $cifras['Veredicto']);
        $this->assertSame(51, $cifras['Reglas evaluadas']);
        $this->assertSame(50, $cifras['Cumplen']);
        $this->assertSame(1, $cifras['No cumplen']);
        $this->assertSame(1, $cifras['  · Errores']);
        $this->assertSame(36, $cifras['No aplican']);
        $this->assertSame(16, $cifras['No corresponde al establecimiento']);
        $this->assertSame(0, $cifras['Fuera de rango']);
        $this->assertSame('98 %', $cifras['Cumplimiento']);
    }

    /**
     * El pie del escritorio afirma que el archivo no salió del equipo. En el
     * portal eso sería falso: el archivo se subió y se conserva. El reporte
     * tiene que decir la verdad de este sistema.
     */
    public function test_el_pie_del_resumen_dice_la_verdad_del_portal(): void
    {
        $hoja = $this->libro($this->ejecucion())->getSheetByName('Resumen');

        $texto = '';
        foreach (range(1, $hoja->getHighestRow()) as $fila) {
            $valor = $hoja->getCell("A{$fila}")->getValue();
            if (is_string($valor) && str_contains($valor, 'portal')) {
                $texto = $valor;
            }
        }

        $this->assertStringContainsString('portal del Servicio de Salud Osorno', $texto);
        $this->assertStringContainsString('90 días', $texto);
        $this->assertStringNotContainsString('no se envió a ningún servidor', $texto);
    }

    public function test_el_resumen_identifica_el_archivo_y_el_establecimiento(): void
    {
        $hoja = $this->libro($this->ejecucion())->getSheetByName('Resumen');

        $this->assertSame('123100A09.xlsm', $hoja->getCell('B4')->getValue());
        $this->assertSame('Hospital Base San José de Osorno', $hoja->getCell('B5')->getValue());
        $this->assertSame('123100', $hoja->getCell('B6')->getValue());
        $this->assertSame('HOSPITAL', $hoja->getCell('B7')->getValue());
        $this->assertSame('A', $hoja->getCell('B8')->getValue());
    }

    /**
     * El período se toma del año en que se hizo la revisión: el nombre del
     * archivo REM no lo trae, y sin él «septiembre» a secas no ubica el dato.
     */
    public function test_el_periodo_lleva_mes_y_anio(): void
    {
        $ejecucion = $this->ejecucion();
        $ejecucion->created_at = '2026-11-27 11:47:21';

        $hoja = $this->libro($ejecucion)->getSheetByName('Resumen');

        $this->assertSame('septiembre 2026', $hoja->getCell('B9')->getValue());
        $this->assertSame('27-11-2026, 11:47:21', $hoja->getCell('B10')->getValue());
    }

    /**
     * Una regla omitida no tiene diferencia que mostrar: la celda queda vacía,
     * no en 0. Un 0 ahí se lee como «cuadra», que es justo lo que no se revisó.
     */
    public function test_una_regla_omitida_no_inventa_una_diferencia_de_cero(): void
    {
        $hoja = $this->libro($this->ejecucion())->getSheetByName('Resultados');

        $this->assertSame('', $hoja->getCell('J4')->getValue());
        // Y una regla que sí se comparó, la muestra.
        $this->assertSame(5.0, (float) $hoja->getCell('J3')->getValue());
    }

    /** El nombre del archivo sigue el del escritorio: código, serie y mes. */
    public function test_el_nombre_del_reporte_lleva_codigo_serie_y_mes(): void
    {
        $nombre = app(ValidadorReporteService::class)->nombre($this->ejecucion());

        $this->assertStringStartsWith('Validacion_123100_A09_', $nombre);
        $this->assertStringEndsWith('.xlsx', $nombre);
    }

    // ─── Descarga por HTTP ────────────────────────────────────────────────

    public function test_el_duenio_descarga_su_reporte(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)
            ->get(route('validador.reporte', $ejecucion));

        $respuesta->assertOk();
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString(
            'spreadsheetml',
            $respuesta->headers->get('Content-Type') ?? ''
        );
    }

    public function test_otro_usuario_no_descarga_un_reporte_ajeno(): void
    {
        $ejecucion = $this->ejecucion();

        $this->actingAs($this->otro)
            ->get(route('validador.reporte', $ejecucion))
            ->assertForbidden();
    }

    public function test_el_administrador_descarga_cualquier_reporte(): void
    {
        $admin = User::factory()->create(['activo' => true]);
        $admin->assignRole('admin');

        $ejecucion = $this->ejecucion();

        $this->actingAs($admin)
            ->get(route('validador.reporte', $ejecucion))
            ->assertOk();
    }

    /**
     * El reporte se arma desde el resultado guardado, no releyendo el Excel:
     * el archivo pudo borrarse por retención y la revisión debe seguir
     * exportándose.
     */
    public function test_el_reporte_sale_aunque_el_archivo_original_ya_no_exista(): void
    {
        $ejecucion = $this->ejecucion();
        Storage::disk('local')->assertMissing($ejecucion->ruta);

        $this->actingAs($this->encargado)
            ->get(route('validador.reporte', $ejecucion))
            ->assertOk();
    }

    public function test_el_archivo_descargado_se_abre_como_libro(): void
    {
        $ejecucion = $this->ejecucion();

        $respuesta = $this->actingAs($this->encargado)
            ->get(route('validador.reporte', $ejecucion));

        $contenido = $respuesta->streamedContent();
        $temporal = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        file_put_contents($temporal, $contenido);

        $libro = IOFactory::load($temporal);
        $hojas = $libro->getSheetNames();
        @unlink($temporal);

        $this->assertSame(['Resumen', 'Resultados', 'Solo Errores'], $hojas);
    }
}
