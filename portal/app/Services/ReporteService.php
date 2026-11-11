<?php

namespace App\Services;

use App\Models\Envio;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte de validación descargable (T-F2-09).
 *
 * El encargado necesita poder trabajar el reporte sin conexión: abrirlo en
 * Excel, marcar lo que corrigió y devolverlo. Un listado en pantalla no sirve
 * para eso.
 */
class ReporteService
{
    public function descargar(Envio $envio): StreamedResponse
    {
        $envio->load('establecimiento', 'archivos.serie', 'validaciones');

        $libro = $this->construir($envio);

        $nombre = sprintf(
            'reporte-%s-%02d-%d.xlsx',
            $envio->establecimiento->codigo,
            $envio->mes,
            $envio->anio
        );

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function construir(Envio $envio): Spreadsheet
    {
        $libro = new Spreadsheet();

        $this->hojaResumen($libro->getActiveSheet(), $envio);
        $this->hojaHallazgos($libro->createSheet(), $envio);

        return $libro;
    }

    private function hojaResumen($hoja, Envio $envio): void
    {
        $hoja->setTitle('Resumen');
        $hoja->setCellValue('A1', 'Reporte de validación');
        $hoja->setCellValue('A2', 'Establecimiento');
        $hoja->setCellValue('B2', $envio->establecimiento->codigo.' — '.$envio->establecimiento->nombre);
        $hoja->setCellValue('A3', 'Período');
        $hoja->setCellValue('B3', sprintf('%02d/%d', $envio->mes, $envio->anio));
        $hoja->setCellValue('A4', 'Estado del envío');
        $hoja->setCellValue('B4', $envio->estado);
        $hoja->setCellValue('A5', 'Generado');
        $hoja->setCellValue('B5', now()->format('d-m-Y H:i'));

        $hoja->setCellValue('A7', 'Serie');
        $hoja->setCellValue('B7', 'Versión');
        $hoja->setCellValue('C7', 'Estado');
        $hoja->setCellValue('D7', 'Bloqueantes');
        $hoja->setCellValue('E7', 'Advertencias');

        $fila = 8;

        foreach ($envio->archivos->sortBy(fn ($a) => $a->serie?->orden) as $archivo) {
            $hoja->setCellValue("A{$fila}", $archivo->serie?->codigo);
            $hoja->setCellValue("B{$fila}", $archivo->version);
            $hoja->setCellValue("C{$fila}", $archivo->estado);
            $hoja->setCellValue("D{$fila}", $archivo->validaciones->where('severidad', 'bloqueante')->count());
            $hoja->setCellValue("E{$fila}", $archivo->validaciones->where('severidad', '!=', 'bloqueante')->count());
            $fila++;
        }

        // Hallazgos del envío completo (V11), que no cuelgan de un archivo.
        $deEnvio = $envio->validaciones;

        $hoja->setCellValue("A{$fila}", 'Envío');
        $hoja->setCellValue("C{$fila}", 'completitud');
        $hoja->setCellValue("D{$fila}", $deEnvio->where('severidad', 'bloqueante')->count());
        $hoja->setCellValue("E{$fila}", $deEnvio->where('severidad', '!=', 'bloqueante')->count());

        $hoja->getStyle('A7:E7')->getFont()->setBold(true);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        foreach (['A' => 14, 'B' => 24, 'C' => 18, 'D' => 14, 'E' => 14] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }
    }

    private function hojaHallazgos($hoja, Envio $envio): void
    {
        $hoja->setTitle('Hallazgos');

        $encabezados = ['Serie', 'Hoja', 'Celda', 'Etapa', 'Código', 'Severidad', 'Mensaje', 'Valor'];

        foreach ($encabezados as $indice => $titulo) {
            $hoja->setCellValue([$indice + 1, 1], $titulo);
        }

        $hoja->getStyle('A1:H1')->getFont()->setBold(true);
        $hoja->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8EEF4');

        $fila = 2;

        foreach ($envio->archivos as $archivo) {
            foreach ($archivo->validaciones->sortBy('etapa') as $hallazgo) {
                $hoja->setCellValue([1, $fila], $archivo->serie?->codigo);
                $hoja->setCellValue([2, $fila], $hallazgo->hoja);
                $hoja->setCellValue([3, $fila], $hallazgo->celda);
                $hoja->setCellValue([4, $fila], $hallazgo->etapa);
                $hoja->setCellValue([5, $fila], $hallazgo->codigo);
                $hoja->setCellValue([6, $fila], $hallazgo->severidad);
                $hoja->setCellValue([7, $fila], $hallazgo->mensaje);
                $hoja->setCellValue([8, $fila], $hallazgo->valor);
                $fila++;
            }
        }

        foreach ($envio->validaciones as $hallazgo) {
            $hoja->setCellValue([1, $fila], 'Envío');
            $hoja->setCellValue([4, $fila], $hallazgo->etapa);
            $hoja->setCellValue([5, $fila], $hallazgo->codigo);
            $hoja->setCellValue([6, $fila], $hallazgo->severidad);
            $hoja->setCellValue([7, $fila], $hallazgo->mensaje);
            $fila++;
        }

        foreach (['A' => 10, 'B' => 12, 'C' => 10, 'D' => 8, 'E' => 12, 'F' => 14, 'G' => 80, 'H' => 16] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }

        if ($fila > 2) {
            $hoja->setAutoFilter('A1:H'.($fila - 1));
        }
    }
}
