<?php

namespace App\Services;

use App\Models\Envio;
use App\Models\EnvioArchivo;
use App\Models\Establecimiento;
use App\Models\Serie;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Matriz de completitud del Servicio de Salud (T-F4-01, OP-09).
 *
 * Cruza los establecimientos con las series que la matriz les exige y con lo
 * que efectivamente llegó cada mes. Una celda responde a una pregunta: ¿este
 * establecimiento entregó esta serie, y en qué estado está?
 *
 * Se resuelve con dos consultas y el cruce en memoria, no con una por celda:
 * con 30 establecimientos y 7 series, una consulta por celda serían 210.
 */
class CompletitudService
{
    public const SIN_ENVIO = 'sin_envio';
    public const SIN_ARCHIVO = 'sin_archivo';
    public const CON_ERRORES = 'con_errores';
    public const EN_PROCESO = 'en_proceso';
    public const APROBADO = 'aprobado';

    public const ETIQUETAS = [
        self::SIN_ENVIO    => 'Sin envío',
        self::SIN_ARCHIVO  => 'Sin archivo',
        self::CON_ERRORES  => 'Con errores',
        self::EN_PROCESO   => 'En proceso',
        self::APROBADO     => 'Aprobado',
    ];

    /**
     * @return array{
     *     filas: list<array{establecimiento: Establecimiento, celdas: array<int, array{estado: string, envio_id: ?int}>, aprobadas: int, exigidas: int}>,
     *     series: Collection<int, Serie>,
     *     totales: array<string, int>,
     *     resumen: array{establecimientos: int, aprobadas: int, exigidas: int}
     * }
     */
    public function matriz(int $anio, int $mes, User $usuario, ?int $establecimientoId = null): array
    {
        $establecimientos = $this->establecimientos($usuario, $establecimientoId);

        if ($establecimientos->isEmpty()) {
            return [
                'filas'   => [],
                'series'  => collect(),
                'totales' => [],
                'resumen' => ['establecimientos' => 0, 'aprobadas' => 0, 'exigidas' => 0],
            ];
        }

        $envios = Envio::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->whereIn('establecimiento_id', $establecimientos->pluck('id'))
            ->with(['archivosVigentes' => fn ($q) => $q->select('id', 'envio_id', 'serie_id', 'estado')])
            ->get()
            ->keyBy('establecimiento_id');

        // Columnas: la unión de las series que alguien exige, en su orden.
        $series = $establecimientos
            ->flatMap(fn (Establecimiento $e) => $e->series)
            ->unique('id')
            ->sortBy('orden')
            ->values();

        $filas = [];
        $totales = array_fill_keys($series->pluck('id')->all(), 0);
        $aprobadasTotal = 0;
        $exigidasTotal = 0;

        foreach ($establecimientos as $establecimiento) {
            $exigidas = $establecimiento->series->pluck('id');
            $envio = $envios->get($establecimiento->id);

            $celdas = [];
            $aprobadas = 0;

            foreach ($series as $serie) {
                // Una serie se muestra solo si este establecimiento la debe.
                if (! $exigidas->contains($serie->id)) {
                    $celdas[$serie->id] = ['estado' => 'no_aplica', 'envio_id' => $envio?->id];

                    continue;
                }

                $estado = $this->estadoCelda($envio, $serie->id);
                $celdas[$serie->id] = ['estado' => $estado, 'envio_id' => $envio?->id];

                if ($estado === self::APROBADO) {
                    $aprobadas++;
                    $totales[$serie->id]++;
                }
            }

            $aprobadasTotal += $aprobadas;
            $exigidasTotal += $exigidas->count();

            $filas[] = [
                'establecimiento' => $establecimiento,
                'celdas'          => $celdas,
                'aprobadas'       => $aprobadas,
                'exigidas'        => $exigidas->count(),
            ];
        }

        return [
            'filas'   => $filas,
            'series'  => $series,
            'totales' => $totales,
            'resumen' => [
                'establecimientos' => $establecimientos->count(),
                'aprobadas'        => $aprobadasTotal,
                'exigidas'         => $exigidasTotal,
            ],
        ];
    }

    /**
     * Estado de una celda: qué pasó con esa serie en ese establecimiento.
     */
    public function estadoCelda(?Envio $envio, int $serieId): string
    {
        if ($envio === null) {
            return self::SIN_ENVIO;
        }

        $archivo = $envio->archivosVigentes->firstWhere('serie_id', $serieId);

        if ($archivo === null) {
            return self::SIN_ARCHIVO;
        }

        if ($archivo->estado === EnvioArchivo::CON_ERRORES) {
            return self::CON_ERRORES;
        }

        if (in_array($envio->estado, [Envio::APROBADO, Envio::CARGADO], true)) {
            return self::APROBADO;
        }

        return self::EN_PROCESO;
    }

    /**
     * Establecimientos con sus series exigidas, dentro del alcance del usuario.
     *
     * @return Collection<int, Establecimiento>
     */
    private function establecimientos(User $usuario, ?int $establecimientoId): Collection
    {
        $consulta = Establecimiento::query()
            ->where('activo', true)
            ->with(['series' => function ($q) {
                // Solo la matriz vigente: una serie que dejó de exigirse no
                // debe aparecer como pendiente.
                $q->wherePivot('obligatoria', true)
                    ->where(function ($sub) {
                        $sub->whereNull('establecimiento_serie.vigente_desde')
                            ->orWhere('establecimiento_serie.vigente_desde', '<=', now()->toDateString());
                    })
                    ->where(function ($sub) {
                        $sub->whereNull('establecimiento_serie.vigente_hasta')
                            ->orWhere('establecimiento_serie.vigente_hasta', '>=', now()->toDateString());
                    })
                    ->orderBy('orden');
            }])
            ->orderBy('nombre');

        if ($establecimientoId !== null) {
            $consulta->whereKey($establecimientoId);
        }

        if (! $usuario->hasRole('admin')) {
            if ($usuario->servicio_id !== null && ($usuario->hasRole('revisor') || $usuario->hasRole('consulta'))) {
                $consulta->where('servicio_id', $usuario->servicio_id);
            } else {
                $consulta->whereIn('id', $usuario->establecimientos()->select('establecimientos.id'));
            }
        }

        return $consulta->get();
    }
/**
     * Exporta la matriz a CSV (T-F4-02, OP-10).
     *
     * Se genera desde la misma estructura que la pantalla: si el CSV y la
     * vista se construyeran por caminos distintos, con el tiempo dirían cosas
     * distintas.
     *
     * @param  array<string, mixed>  $matriz
     */
    public function aCsv(array $matriz, int $anio, int $mes): string
    {
        $memoria = fopen('php://temp', 'r+');

        fwrite($memoria, "\xEF\xBB\xBF"); // BOM: Excel abre el UTF-8 bien

        $encabezado = ['Establecimiento', 'Código'];

        foreach ($matriz['series'] as $serie) {
            $encabezado[] = $serie->codigo;
        }

        $encabezado[] = 'Aprobadas';
        $encabezado[] = 'Exigidas';

        fputcsv($memoria, $encabezado, ';');

        foreach ($matriz['filas'] as $fila) {
            $linea = [$fila['establecimiento']->nombre, $fila['establecimiento']->codigo];

            foreach ($matriz['series'] as $serie) {
                $estado = $fila['celdas'][$serie->id]['estado'] ?? 'no_aplica';
                $linea[] = $estado === 'no_aplica' ? '' : (self::ETIQUETAS[$estado] ?? $estado);
            }

            $linea[] = $fila['aprobadas'];
            $linea[] = $fila['exigidas'];

            fputcsv($memoria, $linea, ';');
        }

        fputcsv($memoria, [], ';');
        fputcsv($memoria, ['Período', sprintf('%02d/%d', $mes, $anio)], ';');
        fputcsv($memoria, ['Establecimientos', $matriz['resumen']['establecimientos']], ';');
        fputcsv($memoria, ['Aprobadas', $matriz['resumen']['aprobadas']], ';');
        fputcsv($memoria, ['Exigidas', $matriz['resumen']['exigidas']], ';');

        rewind($memoria);
        $contenido = (string) stream_get_contents($memoria);
        fclose($memoria);

        return $contenido;
    }

    /**
     * Misma matriz como libro de Excel.
     *
     * @param  array<string, mixed>  $matriz
     */
    public function aExcel(array $matriz, int $anio, int $mes): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Completitud');

        $hoja->setCellValue('A1', 'Completitud del período');
        $hoja->setCellValue('A2', sprintf('Período %02d/%d · %d establecimientos', $mes, $anio, $matriz['resumen']['establecimientos']));
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $fila = 4;
        $columna = 3;

        $hoja->setCellValue('A'.$fila, 'Establecimiento');
        $hoja->setCellValue('B'.$fila, 'Código');

        foreach ($matriz['series'] as $serie) {
            $hoja->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columna).$fila, $serie->codigo);
            $columna++;
        }

        $hoja->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columna).$fila, 'Aprobadas');
        $hoja->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columna + 1).$fila, 'Exigidas');
        $hoja->getStyle('A'.$fila.':'. \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columna + 1).$fila)->getFont()->setBold(true);

        $colores = [
            self::SIN_ENVIO   => 'FFE2E3E5',
            self::SIN_ARCHIVO => 'FFF8F9FA',
            self::CON_ERRORES => 'FFF8D7DA',
            self::EN_PROCESO  => 'FFFFF3CD',
            self::APROBADO    => 'FFD4EDDA',
        ];

        foreach ($matriz['filas'] as $indice => $filaDatos) {
            $fila++;
            $hoja->setCellValue('A'.$fila, $filaDatos['establecimiento']->nombre);
            $hoja->setCellValue('B'.$fila, $filaDatos['establecimiento']->codigo);

            $columna = 3;

            foreach ($matriz['series'] as $serie) {
                $estado = $filaDatos['celdas'][$serie->id]['estado'] ?? 'no_aplica';
                $celda = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columna).$fila;

                if ($estado !== 'no_aplica') {
                    $hoja->setCellValue($celda, self::ETIQUETAS[$estado] ?? $estado);
                    $hoja->getStyle($celda)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($colores[$estado] ?? 'FFFFFFFF');
                }

                $columna++;
            }

            $hoja->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columna).$fila, $filaDatos['aprobadas']);
            $hoja->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columna + 1).$fila, $filaDatos['exigidas']);
        }

        foreach (['A' => 40, 'B' => 12] as $letra => $ancho) {
            $hoja->getColumnDimension($letra)->setWidth($ancho);
        }

        return $libro;
    }
}