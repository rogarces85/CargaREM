<?php

namespace App\Validador;

/**
 * Revisión de la hoja NOMBRE, la portada del REM (RF-040).
 *
 * Se ejecuta **antes** que las reglas de negocio: si la portada está mal, el
 * archivo completo se carga a nombre del establecimiento o del período
 * equivocado, y ningún cuadre posterior tiene sentido.
 *
 * El código de comuna, el de establecimiento y el mes vienen repartidos en una
 * celda por dígito (así está armada la plantilla oficial), por lo que hay que
 * concatenarlos y limpiarlos antes de compararlos con la nómina.
 */
final class HojaNombre
{
    private const HOJA = 'NOMBRE';

    private const CELDAS_COMUNA = ['C2', 'D2', 'E2', 'F2', 'G2'];
    private const CELDAS_ESTABLECIMIENTO = ['C3', 'D3', 'E3', 'F3', 'G3', 'H3'];
    private const CELDAS_MES = ['C6', 'D6'];

    /**
     * @return array{resultados: list<ResultadoRegla>, errorVersion: string|null}
     */
    public function revisar(
        LectorLibro $libro,
        NombreArchivo $archivo,
        NominaEstablecimientos $nomina,
    ): array {
        $resultados = [];
        $errorVersion = null;

        if (! $libro->tieneHoja(self::HOJA)) {
            $resultados[] = $this->hallazgo(
                'VAL_NOM00',
                'El archivo no contiene la hoja NOMBRE, que identifica al establecimiento y el período.',
                '(ausente)',
                'una hoja llamada NOMBRE',
                'A1',
            );

            return ['resultados' => $resultados, 'errorVersion' => null];
        }

        $porCodigo = [];
        $codigosComuna = [];

        foreach ($nomina->todos() as $establecimiento) {
            $porCodigo[$establecimiento['codigo']] = $establecimiento;
            $codigosComuna[$establecimiento['comuna']] = true;
        }

        // 1. Versión de la plantilla (A9).
        $aceptadas = NombreArchivo::versionesAceptadas($archivo->serie);
        $version = $this->texto($libro, 'A9');

        if ($aceptadas !== [] && ! in_array($version, $aceptadas, true)) {
            $errorVersion = $version !== ''
                ? "La versión de plantilla «{$version}» no corresponde a la serie {$archivo->serie}. Se acepta: " . implode(' o ', $aceptadas) . '.'
                : 'La celda A9 no indica la versión de la plantilla. Se acepta: ' . implode(' o ', $aceptadas) . '.';

            $resultados[] = $this->hallazgo('VAL_NOM01', $errorVersion, $version, implode(' o ', $aceptadas), 'A9');
        }

        // 2. Comuna.
        if ($this->texto($libro, 'B2') === '') {
            $resultados[] = $this->hallazgo('VAL_NOM02', 'El nombre de la comuna (celda B2) está vacío.', '', 'el nombre de la comuna', 'B2');
        }

        $vaciasComuna = array_values(array_filter(self::CELDAS_COMUNA, fn ($c) => $this->texto($libro, $c) === ''));

        foreach ($vaciasComuna as $celda) {
            $resultados[] = $this->hallazgo('VAL_NOM03', "La celda {$celda}, que contiene un dígito del código de comuna, está vacía.", '', 'un dígito', $celda);
        }

        if ($vaciasComuna === []) {
            $codigoComuna = $this->concatenarDigitos($libro, self::CELDAS_COMUNA);

            if (! isset($codigosComuna[$codigoComuna])) {
                $resultados[] = $this->hallazgo(
                    'VAL_NOM03',
                    'El código de comuna «' . $codigoComuna . '» (celdas ' . implode('+', self::CELDAS_COMUNA) . ') no corresponde a ninguna comuna del ' . $nomina->servicio . '.',
                    $codigoComuna,
                    'un código de comuna del catálogo',
                    self::CELDAS_COMUNA[0],
                );
            }
        }

        // 3. Establecimiento.
        if ($this->texto($libro, 'B3') === '') {
            $resultados[] = $this->hallazgo('VAL_NOM04', 'El nombre del establecimiento (celda B3) está vacío.', '', 'el nombre del establecimiento', 'B3');
        }

        $vaciasEstab = array_values(array_filter(self::CELDAS_ESTABLECIMIENTO, fn ($c) => $this->texto($libro, $c) === ''));

        foreach ($vaciasEstab as $celda) {
            $resultados[] = $this->hallazgo('VAL_NOM05', "La celda {$celda}, que contiene un dígito del código de establecimiento, está vacía.", '', 'un dígito', $celda);
        }

        if ($vaciasEstab === []) {
            $codigoHoja = $this->concatenarDigitos($libro, self::CELDAS_ESTABLECIMIENTO);

            if (! isset($porCodigo[$codigoHoja])) {
                $resultados[] = $this->hallazgo(
                    'VAL_NOM05',
                    'El código de establecimiento «' . $codigoHoja . '» (celdas ' . implode('+', self::CELDAS_ESTABLECIMIENTO) . ') no pertenece al catálogo del ' . $nomina->servicio . '.',
                    $codigoHoja,
                    'un código del catálogo',
                    self::CELDAS_ESTABLECIMIENTO[0],
                );
            }

            if ($codigoHoja !== $archivo->codigo) {
                $resultados[] = $this->hallazgo(
                    'VAL_NOM10',
                    'El código de establecimiento de la hoja NOMBRE («' . $codigoHoja . '») no coincide con el del nombre del archivo («' . $archivo->codigo . '»).',
                    $codigoHoja,
                    $archivo->codigo,
                    self::CELDAS_ESTABLECIMIENTO[0],
                );
            }

            $nombreEscrito = $this->texto($libro, 'B3');
            $establecimiento = $porCodigo[$codigoHoja] ?? null;

            if ($nombreEscrito !== '' && $establecimiento !== null) {
                $revision = NombreEstablecimiento::verificar($nombreEscrito, $establecimiento, $nomina->comoNomina());

                if (! $revision['coincide']) {
                    $nombrado = $revision['nombrado'];
                    $mensaje = $nombrado !== null
                        ? 'El nombre del establecimiento (celda B3) es el de «' . $nombrado['nombre'] . '» (código ' . $nombrado['codigo'] . '), pero la hoja declara el código ' . $codigoHoja . ', que corresponde a «' . $revision['esperado'] . '».'
                        : 'El nombre del establecimiento (celda B3) no corresponde al código ' . $codigoHoja . ' de la hoja NOMBRE. Se esperaba «' . $revision['esperado'] . '».';

                    $resultados[] = $this->hallazgo(
                        'VAL_NOM13',
                        $mensaje,
                        $nombreEscrito,
                        '«' . $revision['esperado'] . '»',
                        'B3',
                        // Un nombre mal escrito hay que corregirlo, pero no
                        // invalida las cifras del REM: se informa sin rechazar.
                        bloquea: false,
                    );
                }
            }
        }

        // 4. Mes.
        if ($this->texto($libro, 'B6') === '') {
            $resultados[] = $this->hallazgo('VAL_NOM06', 'El nombre del mes (celda B6) está vacío.', '', 'el nombre del mes', 'B6');
        }

        $vaciasMes = array_values(array_filter(self::CELDAS_MES, fn ($c) => $this->texto($libro, $c) === ''));

        foreach ($vaciasMes as $celda) {
            $resultados[] = $this->hallazgo('VAL_NOM07', "La celda {$celda}, que contiene un dígito del código de mes, está vacía.", '', 'un dígito', $celda);
        }

        if ($vaciasMes === []) {
            $mesHoja = str_pad($this->concatenarDigitos($libro, self::CELDAS_MES), 2, '0', STR_PAD_LEFT);

            if ((int) $mesHoja < 1 || (int) $mesHoja > 12) {
                $resultados[] = $this->hallazgo('VAL_NOM07', 'El código de mes «' . $mesHoja . '» (celdas ' . implode('+', self::CELDAS_MES) . ') no es un mes válido.', $mesHoja, 'un mes entre 01 y 12', self::CELDAS_MES[0]);
            } elseif (! NombreArchivo::mesEsValidoParaSerie($archivo->serie, $mesHoja)) {
                $resultados[] = $this->hallazgo(
                    'VAL_NOM12',
                    'El mes «' . $mesHoja . '» no corresponde a la serie ' . $archivo->serie . ', que se reporta en ' . NombreArchivo::etiquetaMeses($archivo->serie) . '.',
                    $mesHoja,
                    NombreArchivo::etiquetaMeses($archivo->serie),
                    self::CELDAS_MES[0],
                );
            }

            if ($mesHoja !== $archivo->mes) {
                $resultados[] = $this->hallazgo(
                    'VAL_NOM11',
                    'El mes de la hoja NOMBRE («' . $mesHoja . '») no coincide con el del nombre del archivo («' . $archivo->mes . '»).',
                    $mesHoja,
                    $archivo->mes,
                    self::CELDAS_MES[0],
                );
            }
        }

        // 5. Responsables.
        if ($this->texto($libro, 'B11') === '') {
            $resultados[] = $this->hallazgo('VAL_NOM08', 'El nombre del responsable del establecimiento (celda B11) está vacío.', '', 'el nombre del responsable', 'B11');
        }

        if ($this->texto($libro, 'B12') === '') {
            $resultados[] = $this->hallazgo('VAL_NOM09', 'El nombre del jefe de estadística (celda B12) está vacío.', '', 'el nombre del jefe de estadística', 'B12');
        }

        return ['resultados' => $resultados, 'errorVersion' => $errorVersion];
    }

    private function hallazgo(
        string $reglaId,
        string $mensaje,
        string $obtenido,
        string $esperado,
        string $celda,
        bool $bloquea = true,
    ): ResultadoRegla {
        return new ResultadoRegla(
            reglaId: $reglaId,
            severidad: ReglaCalidad::ERROR,
            estado: ResultadoRegla::HALLAZGO,
            mensaje: $mensaje,
            hoja: self::HOJA,
            expresion1: $celda,
            valor1: null,
            operador: '==',
            expresion2: $esperado,
            valor2: null,
            celdas: [self::HOJA . '!' . $celda],
            evidencia: 'Se leyó «' . ($obtenido !== '' ? $obtenido : '(vacío)') . '». Se esperaba ' . $esperado . '.',
            bloqueaVeredicto: $bloquea,
        );
    }

    private function texto(LectorLibro $libro, string $celda): string
    {
        $valor = $libro->celda(self::HOJA, $celda);

        return $valor === null ? '' : trim((string) $valor);
    }

    /**
     * Concatena las celdas y deja solo los dígitos.
     *
     * Las plantillas traen espacios o caracteres invisibles pegados desde el
     * formato original, que romperían la comparación contra la nómina.
     *
     * @param  list<string>  $celdas
     */
    private function concatenarDigitos(LectorLibro $libro, array $celdas): string
    {
        $texto = '';

        foreach ($celdas as $celda) {
            $texto .= $this->texto($libro, $celda);
        }

        return preg_replace('/\D/', '', $texto) ?? '';
    }
}
