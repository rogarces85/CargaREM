<?php

namespace Tests\Feature;

use App\Validador\ArchivoNoValidable;
use App\Validador\CatalogoCalidad;
use App\Validador\ContextoEstablecimiento;
use App\Validador\Expresion;
use App\Validador\LectorLibro;
use App\Validador\MotorReglas;
use App\Validador\NombreArchivo;
use App\Validador\ResultadoRegla;
use Tests\TestCase;

/**
 * El motor del Validador contra el catálogo real de la Unidad (RF-040).
 *
 * Lo que se prueba aquí no es un fixture inventado: son las 144 reglas de
 * `config/validador/reglas.json` tal como las entrega la herramienta de
 * escritorio. Si la Unidad cambia el catálogo y algo deja de leerse, esto falla
 * antes de que nadie apruebe con un validador que ya no entiende sus reglas.
 */
class ValidadorMotorTest extends TestCase
{
    /** Libro falso en memoria: hoja => celda => valor. */
    private function libro(array $hojas): LectorLibro
    {
        return new class($hojas) implements LectorLibro
        {
            public function __construct(private array $hojas) {}

            public function celda(string $hoja, string $ref): mixed
            {
                return $this->hojas[$hoja][$ref] ?? null;
            }

            public function sumaRango(string $hoja, string $desde, string $hasta): float
            {
                // Suma real sobre las claves «COLUMNAfila» del arreglo.
                $total = 0.0;

                foreach ($this->hojas[$hoja] ?? [] as $celda => $valor) {
                    if (preg_match('/^([A-Z]+)(\d+)$/', (string) $celda, $m)) {
                        if ($m[1] >= $desde && $m[1] <= $hasta && is_numeric($valor)) {
                            $total += (float) $valor;
                        }
                    }
                }

                return $total;
            }

            public function hojas(): array
            {
                return array_keys($this->hojas);
            }

            public function tieneHoja(string $hoja): bool
            {
                return isset($this->hojas[$hoja]);
            }
        };
    }

    private function catalogo(): CatalogoCalidad
    {
        return CatalogoCalidad::desdeArchivo();
    }

    private function regla(string $id): \App\Validador\ReglaCalidad
    {
        foreach ($this->catalogo()->reglasPorHoja as $reglas) {
            foreach ($reglas as $regla) {
                if ($regla->id === $id) {
                    return $regla;
                }
            }
        }

        $this->fail("No se encontró la regla {$id} en el catálogo.");
    }

    // ─── Catálogo ───────────────────────────────────────────────────────────

    public function test_el_catalogo_real_se_carga_completo(): void
    {
        $catalogo = $this->catalogo();

        $this->assertSame(144, $catalogo->total());
        $this->assertCount(23, $catalogo->hojas());
        $this->assertCount(103, $catalogo->reglasDeSerie('A'));
        $this->assertCount(41, $catalogo->reglasDeSerie('P'));
    }

    public function test_todas_las_expresiones_del_catalogo_se_interpretan(): void
    {
        $fallidas = [];

        foreach ($this->catalogo()->reglasPorHoja as $hoja => $reglas) {
            foreach ($reglas as $regla) {
                try {
                    Expresion::compilar($regla->expresion1);

                    if (is_string($regla->expresion2)) {
                        Expresion::compilar($regla->expresion2);
                    }
                } catch (\Throwable $e) {
                    $fallidas[] = "{$hoja}/{$regla->id}: {$e->getMessage()}";
                }
            }
        }

        $this->assertSame([], $fallidas, "Expresiones ilegibles:\n" . implode("\n", $fallidas));
    }

    // ─── Parser ─────────────────────────────────────────────────────────────

    public function test_un_rango_conserva_sus_dos_extremos(): void
    {
        $expr = Expresion::compilar('SUM(H34:AG34)');

        $this->assertSame(['P2!H34:AG34'], $expr->referencias('P2'));
    }

    public function test_las_operaciones_respetan_la_precedencia(): void
    {
        $libro = $this->libro(['H' => ['A1' => 2, 'B1' => 3, 'C1' => 4]]);

        // 2 + 3 * 4 = 14, no 20.
        $expr = Expresion::compilar('A1+B1*C1');
        $this->assertSame(14.0, app(MotorReglas::class)->evaluarNodo($expr->raiz, $libro, 'H'));

        // (2 + 3) * 4 = 20.
        $expr = Expresion::compilar('(A1+B1)*C1');
        $this->assertSame(20.0, app(MotorReglas::class)->evaluarNodo($expr->raiz, $libro, 'H'));
    }

    public function test_una_celda_vacia_cuenta_como_cero(): void
    {
        $expr = Expresion::compilar('Z99+5');

        $this->assertSame(5.0, app(MotorReglas::class)->evaluarNodo($expr->raiz, $this->libro([]), 'H'));
    }

    public function test_la_division_por_cero_no_propaga_nan(): void
    {
        $expr = Expresion::compilar('A1/A2');

        $this->assertSame(0.0, app(MotorReglas::class)->evaluarNodo($expr->raiz, $this->libro(['H' => ['A1' => 5]]), 'H'));
    }

    // ─── Reglas ─────────────────────────────────────────────────────────────

    public function test_una_regla_simple_que_cumple_no_es_hallazgo(): void
    {
        // A01-VAL001: F11 == 0.
        $resultado = app(MotorReglas::class)->evaluar(
            $this->regla('A01-VAL001'),
            $this->libro(['A01' => ['F11' => 0]]),
        );

        $this->assertSame(ResultadoRegla::CUMPLE, $resultado->estado);
    }

    public function test_una_regla_simple_que_no_cumple_es_hallazgo(): void
    {
        $resultado = app(MotorReglas::class)->evaluar(
            $this->regla('A01-VAL001'),
            $this->libro(['A01' => ['F11' => 1]]),
        );

        $this->assertSame(ResultadoRegla::HALLAZGO, $resultado->estado);
        $this->assertSame(1.0, $resultado->valor1);
        $this->assertSame(0.0, $resultado->valor2);
    }

    public function test_una_regla_doble_compara_dos_celdas(): void
    {
        // A02-VAL001: B11 == B21.
        $regla = $this->regla('A02-VAL001');

        $cumple = app(MotorReglas::class)->evaluar($regla, $this->libro(['A02' => ['B11' => 7, 'B21' => 7]]));
        $this->assertSame(ResultadoRegla::CUMPLE, $cumple->estado);

        $rompe = app(MotorReglas::class)->evaluar($regla, $this->libro(['A02' => ['B11' => 7, 'B21' => 9]]));
        $this->assertSame(ResultadoRegla::HALLAZGO, $rompe->estado);
        $this->assertSame(-2.0, $rompe->diferencia);
    }

    public function test_una_regla_compuesta_suma_celdas_y_compara(): void
    {
        // A01-VAL023: C19+C20+…+F38 == A05!C89.
        $regla = $this->regla('A01-VAL023');

        $libro = $this->libro([
            'A01' => ['C19' => 1, 'C20' => 2, 'C21' => 3, 'C22' => 0, 'C23' => 0, 'C24' => 0,
                      'C25' => 0, 'C26' => 0, 'F36' => 0, 'F37' => 0, 'F38' => 0],
            'A05' => ['C89' => 6],
        ]);

        $this->assertSame(ResultadoRegla::CUMPLE, app(MotorReglas::class)->evaluar($regla, $libro)->estado);
    }

    public function test_ambos_lados_en_cero_se_omite_y_no_se_cuenta_como_aprobado(): void
    {
        // Sin datos en ninguno de los dos lados no hay nada que comparar.
        $resultado = app(MotorReglas::class)->evaluar(
            $this->regla('P2-VAL002'),
            $this->libro(['P2' => []]),
        );

        $this->assertSame(ResultadoRegla::OMITIDA, $resultado->estado);
        $this->assertSame(ResultadoRegla::OMISION_AMBOS_CERO, $resultado->motivoOmision);
    }

    public function test_el_cero_contra_un_literal_si_se_evalua(): void
    {
        // «F11 == 0» con F11 vacío es un archivo correcto, no una omisión: el
        // cero es justamente lo que la regla comprueba.
        $resultado = app(MotorReglas::class)->evaluar(
            $this->regla('A01-VAL001'),
            $this->libro(['A01' => []]),
        );

        $this->assertSame(ResultadoRegla::CUMPLE, $resultado->estado);
    }

    public function test_la_seccion_sin_actividad_no_reclama(): void
    {
        // P11-VAL001a: SUM(D12:D17) > 0, con omitir_si_sin_actividad y solo
        // para el 123100. Un mes sin actividad no es un hallazgo.
        $resultado = app(MotorReglas::class)->evaluar(
            $this->regla('P11-VAL001a'),
            $this->libro(['P11' => []]),
        );

        $this->assertSame(ResultadoRegla::OMITIDA, $resultado->estado);
        $this->assertSame(ResultadoRegla::OMISION_SECCION_SIN_ACTIVIDAD, $resultado->motivoOmision);
    }

    public function test_una_expresion_rota_da_error_tecnico_y_no_cumple(): void
    {
        $regla = \App\Validador\ReglaCalidad::desdeArreglo([
            'id' => 'X-ROTA', 'tipo' => 'SIMPLE', 'severidad' => 'ERROR',
            'expresion_1' => 'A1+++', 'operador' => '==', 'expresion_2' => 0,
            'mensaje' => 'Rota.', 'rem_sheet' => 'X',
        ], 0, 'X');

        $resultado = app(MotorReglas::class)->evaluar($regla, $this->libro([]));

        // R-VAL-02: una regla que no se entiende no puede parecer cumplida.
        $this->assertSame(ResultadoRegla::ERROR_TECNICO, $resultado->estado);
    }

    // ─── Alcance ────────────────────────────────────────────────────────────

    public function test_una_regla_con_alcance_por_codigo_no_aplica_a_otro(): void
    {
        $motor = app(MotorReglas::class);
        $regla = $this->regla('P11-VAL001a');

        $this->assertTrue($motor->aplica($regla, new ContextoEstablecimiento('123100', 'HOSPITAL')));
        $this->assertFalse($motor->aplica($regla, new ContextoEstablecimiento('123999', 'HOSPITAL')));
    }

    public function test_excluir_gana_sobre_incluir(): void
    {
        $motor = app(MotorReglas::class);

        // Una regla con excluir_a excluye aunque el código esté en aplicar_a.
        $regla = \App\Validador\ReglaCalidad::desdeArreglo([
            'id' => 'X-EXCL', 'tipo' => 'SIMPLE', 'severidad' => 'ERROR',
            'expresion_1' => 'A1', 'operador' => '==', 'expresion_2' => 0,
            'mensaje' => 'Alcance.', 'rem_sheet' => 'X',
            'aplicar_a' => '123100', 'excluir_a' => '123100',
        ], 0, 'X');

        $this->assertFalse($motor->aplica($regla, new ContextoEstablecimiento('123100', 'HOSPITAL')));
    }

    // ─── Nombre del archivo ─────────────────────────────────────────────────

    public function test_el_nombre_deis_se_lee(): void
    {
        $archivo = NombreArchivo::parsear('123100A02.xlsx');

        $this->assertTrue($archivo->esValido());
        $this->assertSame('123100', $archivo->codigo);
        $this->assertSame('A', $archivo->serie);
        $this->assertSame('02', $archivo->mes);
    }

    public function test_la_serie_p_solo_acepta_los_meses_de_corte(): void
    {
        $this->assertTrue(NombreArchivo::parsear('123100P06.xlsx')->esValido());
        $this->assertTrue(NombreArchivo::parsear('123100P12.xlsx')->esValido());

        $fuera = NombreArchivo::parsear('123100P03.xlsx');

        $this->assertFalse($fuera->esValido());
        $this->assertStringContainsString('no corresponde a la serie P', implode(' ', $fuera->errores));
    }

    public function test_un_nombre_fuera_de_formato_se_rechaza(): void
    {
        $archivo = NombreArchivo::parsear('estadistica.xlsx');

        $this->assertFalse($archivo->esValido());
        $this->assertStringContainsString('formato DEIS', implode(' ', $archivo->errores));
    }

    public function test_la_variante_con_separadores_tambien_se_acepta(): void
    {
        $archivo = NombreArchivo::parsear('REM_A01_123100_2026.xlsx');

        $this->assertTrue($archivo->esValido());
        $this->assertSame('123100', $archivo->codigo);
        $this->assertSame('01', $archivo->mes);
    }

    public function test_la_serie_p_exige_todas_sus_hojas(): void
    {
        $archivo = NombreArchivo::parsear('123100P06.xlsx');

        $faltantes = $archivo->hojasObligatoriasFaltantes(['NOMBRE', 'P1', 'P2']);

        $this->assertContains('P3', $faltantes);
        $this->assertContains('P12', $faltantes);
        $this->assertNotContains('NOMBRE', $faltantes);
    }
}
