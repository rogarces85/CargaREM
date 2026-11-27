<?php

namespace App\Services;

use App\Models\ValidadorEjecucion;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte descargable del Validador (RF-040.3).
 *
 * Tres hojas, calcadas del Validador de escritorio para que un revisor que
 * conoce una herramienta lea la otra sin manual:
 *
 * - **Resumen**: qué archivo se revisó, con qué resultado y con qué cifras.
 * - **Resultados**: **todas** las reglas evaluadas, no solo las que fallan.
 *   Es lo que vuelve auditable el veredicto: se ve qué se comparó, qué se
 *   omitió y por qué.
 * - **Solo Errores**: lo que hay que corregir, sin ruido.
 *
 * El archivo se arma con los datos guardados en la ejecución: no vuelve a
 * abrir el Excel original, que puede haberse borrado por retención.
 */
class ValidadorReporteService
{
    /** Columnas de las hojas de detalle, en orden. */
    private const COLUMNAS = [
        'Hoja REM',
        'Regla',
        'Severidad',
        'Estado',
        'Detalle',
        'Expresión evaluada',
        'Valor obtenido',
        'Comparación',
        'Valor esperado',
        'Diferencia',
        'Celdas leídas',
        'Observación',
    ];

    private const ANCHOS_DETALLE = [10, 16, 11, 13, 70, 26, 13, 12, 26, 11, 34, 44];

    private const ESTADO_LEGIBLE = [
        'CUMPLE'        => 'Cumple',
        'HALLAZGO'      => 'No Cumple',
        'OMITIDA'       => 'No Aplica',
        'ERROR_TECNICO' => 'Fuera de rango',
    ];

    private const OMISION_LEGIBLE = [
        'AMBOS_CERO'                 => 'Sin datos en ambos lados de la comparación.',
        'CONDICION_PREVIA_NO_CUMPLE' => 'La condición previa no se cumple: no hay datos que comparar.',
        'SECCION_SIN_ACTIVIDAD'      => 'La sección corresponde al establecimiento, pero no trae registro este mes.',
        'HOJA_AUSENTE'               => 'El archivo no trae la hoja que esta regla necesita.',
    ];

    private const VEREDICTO_LEGIBLE = [
        'APROBADO'          => 'Aprobado',
        'CON_OBSERVACIONES' => 'Requiere corrección',
        'RECHAZADO'         => 'Rechazado',
    ];

    /** Encabezado de las hojas de detalle: fondo tenue y negrita. */
    private const FONDO_ENCABEZADO = 'FFE8EEF4';

    /** Color por severidad del hallazgo, igual que el escritorio. */
    private const FONDO_ERROR = 'FFFDE2E2';
    private const FONDO_REVISAR = 'FFFDF3D9';

    public function descargar(ValidadorEjecucion $ejecucion): StreamedResponse
    {
        $libro = $this->construir($ejecucion);

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $this->nombre($ejecucion), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Nombre del archivo: establecimiento, serie y mes, con sello de tiempo. */
    public function nombre(ValidadorEjecucion $ejecucion): string
    {
        $meta = $ejecucion->metadatos();

        return sprintf(
            'Validacion_%s_%s%s_%s.xlsx',
            $meta['codigo'] ?? 'sin-codigo',
            $meta['serie'] ?? '',
            $meta['mes'] ?? '',
            now()->format('Ymd-Hi'),
        );
    }

    /** Arma el libro completo. Separado de la descarga para poder probarlo. */
    public function construir(ValidadorEjecucion $ejecucion): Spreadsheet
    {
        $libro = new Spreadsheet();

        $this->hojaResumen($libro->getActiveSheet(), $ejecucion);

        $todas = $this->resultados($ejecucion);
        $soloErrores = array_values(array_filter(
            $todas,
            fn (array $r) => $r['estado'] === 'HALLAZGO' && $r['severidad'] === 'ERROR',
        ));

        $this->hojaDetalle(
            $libro->createSheet(),
            'Resultados',
            $todas,
            'No hay resultados guardados para esta revisión.',
        );

        $this->hojaDetalle(
            $libro->createSheet(),
            'Solo Errores',
            $soloErrores,
            'Sin errores. Revise la hoja «Resultados» por las observaciones.',
        );

        return $libro;
    }

    /**
     * Resultados guardados, con respaldo para ejecuciones anteriores a que se
     * guardara el detalle completo: en esas solo hay hallazgos.
     *
     * @return list<array<string, mixed>>
     */
    private function resultados(ValidadorEjecucion $ejecucion): array
    {
        $guardados = $ejecucion->resultado['resultados'] ?? null;

        if (is_array($guardados)) {
            return array_values($guardados);
        }

        return $ejecucion->listaHallazgos();
    }

    private function hojaResumen($hoja, ValidadorEjecucion $ejecucion): void
    {
        $meta = $ejecucion->metadatos();
        $resumen = $ejecucion->resumen() ?? [];

        $hoja->setTitle('Resumen');

        $hoja->setCellValue('A1', 'Validador REM · DEIS 2026');
        $hoja->setCellValue('A2', 'Servicio de Salud Osorno — revisión de archivos REM');

        $filas = [
            ['Archivo', $meta['nombre'] ?? $ejecucion->nombre_original],
            ['Establecimiento', $meta['establecimiento'] ?? 'No identificado'],
            ['Código', $meta['codigo'] ?? '—'],
            ['Tipo', $meta['tipo'] ?? '—'],
            ['Serie', $meta['serie'] ?? '—'],
            ['Período', $this->periodo($meta, $ejecucion)],
            ['Fecha de revisión', $this->fecha($ejecucion)],
        ];

        $fila = 4;
        foreach ($filas as [$rotulo, $valor]) {
            $hoja->setCellValueExplicit("A{$fila}", $rotulo, DataType::TYPE_STRING);
            // El valor va como texto: el código de establecimiento «123100» no
            // es un número y no debe convertirse en uno.
            $hoja->setCellValueExplicit("B{$fila}", (string) $valor, DataType::TYPE_STRING);
            $fila++;
        }

        // Bloque de cifras: separado del contexto por una fila en blanco, como
        // en el escritorio.
        $fila++;

        $cifras = [
            ['Veredicto', self::VEREDICTO_LEGIBLE[$resumen['veredicto'] ?? ''] ?? ($resumen['veredicto'] ?? '—')],
            ['Reglas evaluadas', $resumen['evaluadas'] ?? 0],
            ['Cumplen', $resumen['cumplen'] ?? 0],
            ['No cumplen', $resumen['hallazgos'] ?? 0],
            ['  · Errores', $resumen['errores'] ?? 0],
            ['  · Por revisar', $resumen['revisar'] ?? 0],
            ['  · Indicadores', 0],
            ['No aplican', $resumen['omitidas'] ?? 0],
            ['No corresponde al establecimiento', $resumen['fueraDeAlcance'] ?? 0],
            ['Fuera de rango', $resumen['erroresTecnicos'] ?? 0],
            ['Cumplimiento', ($resumen['porcentaje'] ?? 0) . ' %'],
        ];

        foreach ($cifras as [$rotulo, $valor]) {
            $hoja->setCellValue("A{$fila}", $rotulo);
            $hoja->setCellValue("B{$fila}", $valor);
            $fila++;
        }

        $fila++;
        $hoja->setCellValue("A{$fila}", 'Revisión realizada en el portal del Servicio de Salud Osorno. '
            . 'El archivo se conserva 90 días y solo lo ve quien lo subió y el administrador.');

        // Avisos que condicionan la lectura del resultado.
        if (($ejecucion->resultado['errorVersion'] ?? null) !== null) {
            $fila++;
            $hoja->setCellValue("A{$fila}", 'Aviso de plantilla');
            $hoja->setCellValue("B{$fila}", $ejecucion->resultado['errorVersion']);
        }

        if (($ejecucion->resultado['hojasAusentes'] ?? []) !== []) {
            $fila++;
            $hoja->setCellValue("A{$fila}", 'Hojas no encontradas');
            $hoja->setCellValue("B{$fila}", implode(', ', $ejecucion->resultado['hojasAusentes']));
        }

        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->getStyle('A2')->getFont()->setItalic(true);

        // Rótulos en negrita: las filas pares son rótulo, las impares valor.
        foreach ([4, 5, 6, 7, 8, 9, 10, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22] as $f) {
            $hoja->getStyle("A{$f}")->getFont()->setBold(true);
        }

        $hoja->getColumnDimension('A')->setWidth(36);
        $hoja->getColumnDimension('B')->setWidth(62);
    }

    /** «septiembre 2026» a partir de los metadatos y de la fecha de la revisión. */
    private function periodo(array $meta, ValidadorEjecucion $ejecucion): string
    {
        $mes = (int) ($meta['mes'] ?? 0);

        if ($mes < 1 || $mes > 12) {
            return '—';
        }

        $nombres = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        // El nombre del archivo REM no trae el año: se toma de la fecha en que
        // se hizo la revisión, que es el período al que corresponde el archivo.
        $anio = $ejecucion->created_at?->year;

        return $anio
            ? $nombres[$mes - 1] . ' ' . $anio
            : $nombres[$mes - 1];
    }

    /** Fecha y hora en el formato del escritorio: «27-11-2026, 11:47:21». */
    private function fecha(ValidadorEjecucion $ejecucion): string
    {
        return $ejecucion->created_at?->format('d-m-Y, H:i:s') ?? '—';
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     */
    private function hojaDetalle($hoja, string $titulo, array $resultados, string $vacio): void
    {
        $hoja->setTitle($titulo);

        foreach (self::COLUMNAS as $indice => $encabezado) {
            $hoja->setCellValue([$indice + 1, 1], $encabezado);
        }

        $hoja->getStyle('A1:L1')->getFont()->setBold(true);
        $hoja->getStyle('A1:L1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::FONDO_ENCABEZADO);

        $fila = 2;

        if ($resultados === []) {
            $hoja->setCellValue("A{$fila}", $vacio);
        } else {
            foreach ($resultados as $r) {
                $this->filaDetalle($hoja, $fila, $r);
                $fila++;
            }
        }

        foreach (self::ANCHOS_DETALLE as $indice => $ancho) {
            $hoja->getColumnDimensionByColumn($indice + 1)->setWidth($ancho);
        }

        // Autofiltro sobre el encabezado: deja ordenar y acotar dentro de Excel.
        if ($resultados !== []) {
            $hoja->setAutoFilter('A1:L' . ($fila - 1));
        }
    }

    /** @param  array<string, mixed>  $r */
    private function filaDetalle($hoja, int $fila, array $r): void
    {
        $estado = $r['estado'] ?? '';
        $omision = $r['omision'] ?? null;

        $observacion = $r['evidencia'] ?? '';

        if ($omision !== null) {
            $observacion = self::OMISION_LEGIBLE[$omision] ?? (string) $omision;
        }

        $valores = [
            $r['hoja'] ?? '',
            $r['regla'] ?? '',
            $this->capitalizar((string) ($r['severidad'] ?? '')),
            self::ESTADO_LEGIBLE[$estado] ?? $estado,
            $r['mensaje'] ?? '',
            $r['expresion1'] ?? '',
            $this->numero($r['valor1'] ?? null),
            $r['operador'] ?? '',
            $r['expresion2'] ?? '',
            $this->numero($r['diferencia'] ?? null),
            implode(', ', $r['celdas'] ?? []),
            $observacion,
        ];

        foreach ($valores as $indice => $valor) {
            // Todo va como texto salvo los dos valores numericos. Es
            // obligatorio: «==» en la columna Comparación se interpreta como
            // fórmula y PhpSpreadsheet falla al escribir el libro.
            if (in_array($indice, [6, 9], true) && $valor !== '') {
                $hoja->setCellValue([$indice + 1, $fila], $valor);
            } else {
                $hoja->setCellValueExplicit(
                    [$indice + 1, $fila],
                    $valor === null || $valor === '' ? '' : (string) $valor,
                    DataType::TYPE_STRING,
                );
            }
        }

        // El color va en la severidad, como en el escritorio: es la columna que
        // se mira al recorrer la hoja.
        if ($estado === 'HALLAZGO') {
            $color = ($r['severidad'] ?? '') === 'ERROR' ? self::FONDO_ERROR : self::FONDO_REVISAR;
            $hoja->getStyle([3, $fila])->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($color);
        }

        $hoja->getStyle([5, $fila])->getAlignment()->setWrapText(true);
    }

    private function capitalizar(string $texto): string
    {
        return $texto === '' ? '' : mb_strtoupper(mb_substr($texto, 0, 1)) . mb_strtolower(mb_substr($texto, 1));
    }

    /**
     * Los valores numéricos van como número; null queda **vacío**, no «0».
     *
     * La diferencia es la que más importa: una regla omitida no tiene
     * diferencia que mostrar, y un 0 ahí se lee como «cuadra», que es
     * justamente lo que no se comprobó.
     */
    private function numero(mixed $valor): mixed
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return (float) $valor;
    }
}
