<?php

namespace App\Services;

use App\Exceptions\CatalogoInvalidoException;
use App\Models\CatalogoSnapshot;
use App\Support\CsvReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Importa un snapshot de catálogos desde los CSV que genera
 * 01-TOOLS/rem-export (T-F1-20) y activa uno solo a la vez (T-F1-21).
 *
 * El snapshot se crea INACTIVO: importar nunca cambia lo que el portal está
 * usando. Activarlo es un acto explícito y separado.
 */
class CatalogoService
{
    /** Archivos que un snapshot completo debe traer. */
    public const ARCHIVOS = [
        'series.csv',
        'servicios.csv',
        'establecimientos.csv',
        'plantillas.csv',
        'plantilla_hojas.csv',
        'diccionario.csv',
        'excepciones.csv',
        'excepciones_columnas.csv',
    ];

    private const COLUMNAS = [
        'series.csv'               => ['Serie', 'SerieExcel'],
        'servicios.csv'            => ['IdServicio', 'NServicio', 'IdRegion', 'Orden', 'ElimID'],
        'establecimientos.csv'     => ['IdEstablecimiento', 'NEstablecimiento', 'Direccion', 'Telefono', 'Fax', 'IdServicio', 'IdComuna', 'IdDependencia', 'IdTipo', 'ElimID', 'Propiedad'],
        'plantillas.csv'           => ['mes', 'ano', 'version', 'Serie', 'SerieExcel'],
        'plantilla_hojas.csv'      => ['Serie', 'Hoja', 'ano', 'mes', 'Rango'],
        'diccionario.csv'          => ['CodigoPrestacion', 'Mes', 'Year', 'TextoPrestacion', 'Hoja', 'Serie', 'Posicion', 'tipoDato', 'linea', 'Inicio', 'Fin'],
        'excepciones.csv'          => ['CodigoPrestacion', 'Mes', 'Year', 'Hoja', 'Serie', 'Posicion', 'tipoDato', 'colexcep'],
        'excepciones_columnas.csv' => ['codpres', 'col'],
    ];

    private const TAMANO_LOTE = 500;

    /** @var array<string, int> Mapa Serie interna => serie_id, para traducir Diccionario.Serie. */
    private array $seriesInternas = [];

    /**
     * Importa desde un directorio con los CSV ya extraídos.
     */
    public function importarDirectorio(string $directorio, ?int $usuarioId = null): CatalogoSnapshot
    {
        $directorio = rtrim($directorio, '/\\');

        foreach (self::ARCHIVOS as $archivo) {
            if (! is_readable($directorio.DIRECTORY_SEPARATOR.$archivo)) {
                throw CatalogoInvalidoException::archivoFaltante($archivo);
            }
        }

        $this->verificarManifiesto($directorio);

        return DB::transaction(function () use ($directorio, $usuarioId) {
            $series = $this->importarSeries($directorio);
            $servicios = $this->importarServicios($directorio);

            $this->importarEstablecimientos($directorio, $servicios);

            $snapshot = CatalogoSnapshot::create([
                'origen'      => basename($directorio),
                'activo'      => false,
                'importado_por' => $usuarioId,
            ]);

            $plantillas = $this->importarPlantillas($directorio, $series, $snapshot);
            $this->importarHojas($directorio, $series);

            $filasDiccionario = $this->importarDiccionario($directorio, $snapshot, $plantillas);
            $this->importarExcepciones($directorio, $snapshot, $plantillas);

            $snapshot->update([
                'filas_diccionario'      => $filasDiccionario,
                'filas_establecimientos' => DB::table('establecimientos')->count(),
            ]);

            return $snapshot;
        });
    }

    /**
     * Importa desde un ZIP con los CSV dentro.
     */
    public function importarZip(string $rutaZip, ?int $usuarioId = null): CatalogoSnapshot
    {
        $zip = new ZipArchive();

        if ($zip->open($rutaZip) !== true) {
            throw new CatalogoInvalidoException(
                'APP-CAT-007',
                'No se pudo abrir el archivo comprimido.',
                'Compruebe que sea un ZIP válido generado por el exportador.',
            );
        }

        $destino = Storage::disk('local')->path('catalogo_tmp/'.uniqid('snap_', true));

        if (! is_dir($destino)) {
            mkdir($destino, 0775, true);
        }

        try {
            $zip->extractTo($destino);
            $zip->close();

            // El ZIP puede traer una carpeta raíz.
            $contenido = glob($destino.'/*');
            $raiz = $destino;

            if (count($contenido) === 1 && is_dir($contenido[0])) {
                $raiz = $contenido[0];
            }

            return $this->importarDirectorio($raiz, $usuarioId);
        } finally {
            $this->borrarDirectorio($destino);
        }
    }

    /**
     * Deja un solo snapshot activo (T-F1-21).
     */
    public function activar(CatalogoSnapshot $snapshot): CatalogoSnapshot
    {
        return DB::transaction(function () use ($snapshot) {
            CatalogoSnapshot::query()->whereKeyNot($snapshot->getKey())->update(['activo' => false]);
            $snapshot->update(['activo' => true]);

            return $snapshot->refresh();
        });
    }

    public function activo(): ?CatalogoSnapshot
    {
        return CatalogoSnapshot::activo()->latest('id')->first();
    }

    // -----------------------------------------------------------------
    // Piezas de la importación
    // -----------------------------------------------------------------

    /** @return array<string, mixed> */
    private function importarSeries(string $directorio): array
    {
        $mapa = [];
        $vistos = [];

        foreach (CsvReader::leer($directorio.'/series.csv', self::COLUMNAS['series.csv']) as $linea => $fila) {
            $externo = $fila['SerieExcel'];

            if ($externo === '') {
                continue;
            }

            // series.csv es un mapa interno -> externo: "SERIE A1" y "SERIE A2"
            // apuntan ambos a "SERIE A". El duplicado válido es el par, no la
            // serie externa repetida.
            $par = $fila['Serie'].'|'.$externo;

            if (isset($vistos[$par])) {
                throw CatalogoInvalidoException::duplicado('series.csv', $par, $linea);
            }

            $vistos[$par] = true;
            $codigo = $this->codigoExterno($externo);

            $serie = \App\Models\Serie::firstOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $externo, 'activo' => true]
            );

            $mapa[$externo] = $serie->id;
            $mapa[$fila['Serie']] = $serie->id;
        }

        if ($mapa === []) {
            throw CatalogoInvalidoException::sinSeries();
        }

        return $mapa;
    }

    /** @return array<string, int> codigo de servicio => id */
    private function importarServicios(string $directorio): array
    {
        $mapa = [];
        $vistos = [];

        foreach (CsvReader::leer($directorio.'/servicios.csv', self::COLUMNAS['servicios.csv']) as $linea => $fila) {
            $codigo = $fila['IdServicio'];

            if (isset($vistos[$codigo])) {
                throw CatalogoInvalidoException::duplicado('servicios.csv', $codigo, $linea);
            }

            $vistos[$codigo] = true;

            $modelo = \App\Models\Servicio::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $fila['NServicio'], 'activo' => (int) ($fila['ElimID'] ?: 0) === 0]
            );

            $mapa[$codigo] = $modelo->id;
        }

        return $mapa;
    }

    /** @param array<string, int> $servicios */
    private function importarEstablecimientos(string $directorio, array $servicios): void
    {
        $vistos = [];

        foreach (CsvReader::leer($directorio.'/establecimientos.csv', self::COLUMNAS['establecimientos.csv']) as $linea => $fila) {
            $codigo = $fila['IdEstablecimiento'];

            if (isset($vistos[$codigo])) {
                throw CatalogoInvalidoException::duplicado('establecimientos.csv', $codigo, $linea);
            }

            $vistos[$codigo] = true;

            $servicioId = $servicios[$fila['IdServicio']] ?? null;

            if ($servicioId === null) {
                continue; // establecimiento de un servicio no incluido en el snapshot
            }

            \App\Models\Establecimiento::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre'         => $fila['NEstablecimiento'],
                    'servicio_id'    => $servicioId,
                    'id_comuna'      => $fila['IdComuna'] !== '' ? (int) $fila['IdComuna'] : null,
                    'id_dependencia' => $fila['IdDependencia'] !== '' ? (int) $fila['IdDependencia'] : null,
                    'tipo'           => $fila['IdTipo'] !== '' ? $fila['IdTipo'] : null,
                    'activo'         => (int) ($fila['ElimID'] ?: 0) === 0,
                ]
            );
        }
    }

    /**
     * @param  array<string, int>  $series
     * @return array<string, int> clave "anio|mes|serie_id" => plantilla_id
     */
    private function importarPlantillas(string $directorio, array $series, CatalogoSnapshot $snapshot): array
    {
        $mapa = [];
        $seriesInternas = [];

        foreach (CsvReader::leer($directorio.'/plantillas.csv', self::COLUMNAS['plantillas.csv']) as $fila) {
            $serieId = $series[$fila['SerieExcel']] ?? $series[$fila['Serie']] ?? null;

            if ($serieId === null) {
                continue;
            }

            // updateOrCreate y no firstOrCreate: una plantilla que ya existía
            // de una importación anterior se quedaba con `serie_interna` nula
            // y V5/V6 no encontraban sus hojas ni su diccionario.
            $plantilla = \App\Models\Plantilla::updateOrCreate(
                [
                    'anio'     => (int) $fila['ano'],
                    'mes'      => (int) $fila['mes'],
                    'serie_id' => $serieId,
                    'version'  => $fila['version'],
                ],
                [
                    'serie_excel'   => $fila['SerieExcel'],
                    'serie_interna' => $fila['Serie'],
                    'snapshot_id'   => $snapshot->id,
                ]
            );

            $mapa[$fila['ano'].'|'.$fila['mes'].'|'.$serieId] = $plantilla->id;
            $seriesInternas[$fila['Serie']] = $serieId;
        }

        // Se guarda el mapa interno para traducir Diccionario.Serie.
        $this->seriesInternas = $seriesInternas;

        return $mapa;
    }

    /**
     * Importa hojas y rangos desde Thojas: van por año y serie, no por
     * plantilla mensual (ver migración 2026_11_11_170003).
     *
     * @param  array<string, int>  $series  mapa Serie (interna o externa) => serie_id
     */
    private function importarHojas(string $directorio, array $series): void
    {
        foreach (CsvReader::leer($directorio.'/plantilla_hojas.csv', self::COLUMNAS['plantilla_hojas.csv']) as $fila) {
            if ($fila['Serie'] === '' || $fila['ano'] === '') {
                continue;
            }

            \App\Models\PlantillaHoja::firstOrCreate(
                [
                    'anio'          => (int) $fila['ano'],
                    'serie_interna' => $fila['Serie'],
                    'hoja'          => $fila['Hoja'],
                ],
                [
                    'rango'       => $fila['Rango'] !== '' ? $fila['Rango'] : null,
                    'obligatoria' => true,
                ]
            );
        }
    }
    private function importarDiccionario(string $directorio, CatalogoSnapshot $snapshot, array $plantillas): int
    {
        $lote = [];
        $total = 0;

        foreach (CsvReader::leer($directorio.'/diccionario.csv', self::COLUMNAS['diccionario.csv']) as $fila) {
            $serie = $fila['Serie'];

            if ($serie === '') {
                continue;
            }

            $lote[] = [
                'snapshot_id'       => $snapshot->id,
                'anio'              => (int) $fila['Year'],
                'mes'               => $fila['Mes'] !== '' ? (int) $fila['Mes'] : null,
                'serie'             => $serie,
                'hoja'              => $fila['Hoja'],
                'codigo_prestacion' => $fila['CodigoPrestacion'],
                'texto_prestacion'  => $fila['TextoPrestacion'] !== '' ? $fila['TextoPrestacion'] : null,
                'linea'             => (int) $fila['linea'],
                'inicio'            => $fila['Inicio'] !== '' ? $fila['Inicio'] : null,
                'fin'               => $fila['Fin'] !== '' ? $fila['Fin'] : null,
                'posicion'          => $fila['Posicion'] !== '' ? (int) $fila['Posicion'] : null,
                'tipo_dato'         => $fila['tipoDato'] !== '' ? $fila['tipoDato'] : null,
            ];

            if (count($lote) >= self::TAMANO_LOTE) {
                DB::table('diccionario')->insert($lote);
                $total += count($lote);
                $lote = [];
            }
        }

        if ($lote !== []) {
            DB::table('diccionario')->insert($lote);
            $total += count($lote);
        }

        return $total;
    }

    private function importarExcepciones(string $directorio, CatalogoSnapshot $snapshot, array $plantillas): void
    {
        $lote = [];

        foreach (CsvReader::leer($directorio.'/excepciones.csv', self::COLUMNAS['excepciones.csv']) as $fila) {
            $lote[] = [
                'snapshot_id'       => $snapshot->id,
                'anio'              => (int) $fila['Year'],
                'mes'               => $fila['Mes'] !== '' ? (int) $fila['Mes'] : null,
                'serie'             => $fila['Serie'] !== '' ? $fila['Serie'] : 'SIN SERIE',
                'hoja'              => $fila['Hoja'],
                'codigo_prestacion' => $fila['CodigoPrestacion'] !== '' ? $fila['CodigoPrestacion'] : null,
                'columna'           => $fila['colexcep'] !== '' ? $fila['colexcep'] : null,
                'payload'           => json_encode($fila, JSON_UNESCAPED_UNICODE),
            ];

            if (count($lote) >= self::TAMANO_LOTE) {
                DB::table('excepciones')->insert($lote);
                $lote = [];
            }
        }

        if ($lote !== []) {
            DB::table('excepciones')->insert($lote);
        }
    }

    // -----------------------------------------------------------------
    // Utilidades
    // -----------------------------------------------------------------

    /** "SERIE BS" => "BS" */
    private function codigoExterno(string $serieExcel): string
    {
        return trim(str_ireplace('SERIE', '', $serieExcel));
    }

    private function verificarManifiesto(string $directorio): void
    {
        $ruta = $directorio.'/manifest.json';

        if (! is_readable($ruta)) {
            return; // el manifiesto es opcional; si falta, no hay con qué comparar
        }

        $manifest = json_decode(file_get_contents($ruta), true);

        foreach ($manifest['tablas'] ?? [] as $tabla) {
            $archivo = $tabla['Archivo'] ?? null;

            if ($archivo === null || ! is_readable($directorio.'/'.$archivo)) {
                continue;
            }

            $esperado = (int) ($tabla['Filas'] ?? -1);

            if ($esperado < 0) {
                continue;
            }

            $encontrado = $this->contarFilasCsv($directorio.'/'.$archivo);

            if ($encontrado !== $esperado) {
                throw CatalogoInvalidoException::conteo($archivo, $esperado, $encontrado);
            }
        }
    }

    private function contarFilasCsv(string $ruta): int
    {
        $fh = fopen($ruta, 'rb');
        $filas = -1; // descuenta la cabecera

        while (fgetcsv($fh, 0, ',', '"', '\\') !== false) {
            $filas++;
        }

        fclose($fh);

        return $filas;
    }

    private function borrarDirectorio(string $directorio): void
    {
        if (! is_dir($directorio)) {
            return;
        }

        foreach (scandir($directorio) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $ruta = $directorio.'/'.$item;
            is_dir($ruta) ? $this->borrarDirectorio($ruta) : @unlink($ruta);
        }

        @rmdir($directorio);
    }
}