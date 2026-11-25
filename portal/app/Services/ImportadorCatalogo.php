<?php

namespace App\Services;

use App\Exceptions\CatalogoInvalidoException;
use App\Models\Establecimiento;
use App\Models\Plantilla;
use App\Models\PlantillaHoja;
use App\Models\Serie;
use App\Models\Servicio;
use App\Support\CsvReader;
use Illuminate\Support\Facades\DB;

/**
 * Carga el catálogo de validación desde los CSV de `01-TOOLS/rem-export`.
 *
 * En v2.0 no hay snapshots ni importador en la interfaz: las tablas de
 * catálogo son las vivas y las carga `CatalogoSeeder`. El importador solo sabe
 * leer un directorio completo y reemplazarlo dentro de una transacción; si
 * algo falla, el catálogo anterior queda intacto.
 */
class ImportadorCatalogo
{
    /** Archivos que un catálogo completo debe traer. */
    public const ARCHIVOS = [
        'series.csv',
        'servicios.csv',
        'establecimientos.csv',
        'plantillas.csv',
        'plantilla_hojas.csv',
        'diccionario.csv',
    ];

    private const COLUMNAS = [
        'series.csv'           => ['Serie', 'SerieExcel'],
        'servicios.csv'        => ['IdServicio', 'NServicio', 'IdRegion', 'Orden', 'ElimID'],
        'establecimientos.csv' => ['IdEstablecimiento', 'NEstablecimiento', 'Direccion', 'Telefono', 'Fax', 'IdServicio', 'IdComuna', 'IdDependencia', 'IdTipo', 'ElimID', 'Propiedad'],
        'plantillas.csv'       => ['mes', 'ano', 'version', 'Serie', 'SerieExcel'],
        'plantilla_hojas.csv'  => ['Serie', 'Hoja', 'ano', 'mes', 'Rango'],
        'diccionario.csv'      => ['CodigoPrestacion', 'Mes', 'Year', 'TextoPrestacion', 'Hoja', 'Serie', 'Posicion', 'tipoDato', 'linea', 'Inicio', 'Fin'],
    ];

    private const TAMANO_LOTE = 500;

    /** ¿El directorio tiene todo lo necesario para cargar el catálogo? */
    public function disponible(string $directorio): bool
    {
        $directorio = rtrim($directorio, '/\\');

        if (! is_dir($directorio)) {
            return false;
        }

        foreach (self::ARCHIVOS as $archivo) {
            if (! is_readable($directorio.DIRECTORY_SEPARATOR.$archivo)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Carga el catálogo completo.
     *
     * @return array{series: int, establecimientos: int, plantillas: int, hojas: int, diccionario: int, matriz: int}
     */
    public function cargar(string $directorio): array
    {
        $directorio = rtrim($directorio, '/\\');

        foreach (self::ARCHIVOS as $archivo) {
            if (! is_readable($directorio.DIRECTORY_SEPARATOR.$archivo)) {
                throw CatalogoInvalidoException::archivoFaltante($archivo);
            }
        }

        $this->verificarManifiesto($directorio);

        return DB::transaction(function () use ($directorio) {
            $servicio = $this->servicioObjetivo($directorio);
            $series = $this->importarSeries($directorio);

            $establecimientos = $this->importarEstablecimientos($directorio, $servicio);

            // La matriz se arma al importar: sin filas, R-SER-05 («sin fila, no
            // obliga») dejaría a los establecimientos del catálogo sin ninguna
            // serie exigida. El administrador ajusta después cuál sí y cuál no.
            $matriz = $this->crearMatrizInicial($servicio);

            $plantillas = $this->importarPlantillas($directorio, $series);
            $hojas = $this->importarHojas($directorio);

            // El diccionario se reemplaza entero: es la única tabla que puede
            // quedar incoherente si se mezclan dos exportaciones.
            DB::table('diccionario')->delete();
            $diccionario = $this->importarDiccionario($directorio);

            return [
                'series'           => $series === [] ? 0 : Serie::count(),
                'establecimientos' => $establecimientos,
                'plantillas'       => $plantillas,
                'hojas'            => $hojas,
                'diccionario'      => $diccionario,
                'matriz'           => $matriz,
            ];
        });
    }

    /**
     * Exige todas las series activas a los establecimientos del Servicio.
     *
     * **Cuándo llena una matriz y cuándo no.** Solo cuando el establecimiento
     * **no tiene ninguna fila**. Una matriz vacía se considera «no
     * configurada», y se configura con el criterio por defecto: todas las
     * series activas.
     *
     * En cuanto el administrador edita la matriz, el establecimiento tiene al
     * menos una fila y la importación **no lo toca**. Si no fuera así, quitar
     * una serie desde la pantalla no serviría de nada: la siguiente
     * importación la volvería a poner y nadie podría confiar en el ajuste.
     *
     * El caso límite que esto acepta: si el administrador desmarca **todas** las
     * series de un establecimiento, su matriz queda vacía y la próxima
     * importación le devuelve el criterio por defecto. Se acepta a sabiendas
     * porque «no debe ninguna serie» y «nadie configuró esto» son la misma fila
     * vacía, y para el primer caso está `obligatoria = 0` en la matriz.
     *
     * `vigente_desde` queda en `null` (rige desde siempre) porque la regla es
     * «todos deben todas desde el primer período». Si más adelante una serie se
     * deja de exigir, el administrador pone su `vigente_hasta`.
     *
     * @return int pares creados (los establecimientos ya configurados no cuentan)
     */
    private function crearMatrizInicial(Servicio $servicio): int
    {
        $series = Serie::where('activo', true)->pluck('id');

        if ($series->isEmpty()) {
            return 0;
        }

        $establecimientos = Establecimiento::where('servicio_id', $servicio->id)->pluck('id');

        if ($establecimientos->isEmpty()) {
            return 0;
        }

        // Los que ya tienen alguna fila están configurados: no se tocan.
        $configurados = DB::table('establecimiento_serie')
            ->whereIn('establecimiento_id', $establecimientos)
            ->distinct()
            ->pluck('establecimiento_id')
            ->flip();

        $ahora = now();
        $nuevas = [];

        foreach ($establecimientos as $establecimientoId) {
            if ($configurados->has($establecimientoId)) {
                continue;
            }

            foreach ($series as $serieId) {
                $nuevas[] = [
                    'establecimiento_id' => $establecimientoId,
                    'serie_id'           => $serieId,
                    'obligatoria'        => true,
                    'vigente_desde'      => null,
                    'vigente_hasta'      => null,
                    'created_at'         => $ahora,
                    'updated_at'         => $ahora,
                ];
            }
        }

        foreach (array_chunk($nuevas, self::TAMANO_LOTE) as $lote) {
            DB::table('establecimiento_serie')->insert($lote);
        }

        return count($nuevas);
    }

    /** El Servicio del MVP, tomado del CSV y confirmado contra la configuración. */
    private function servicioObjetivo(string $directorio): Servicio
    {
        $codigo = (string) config('rem.servicio.codigo');

        foreach (CsvReader::leer($directorio.'/servicios.csv', self::COLUMNAS['servicios.csv']) as $fila) {
            if ((string) $fila['IdServicio'] !== $codigo) {
                continue;
            }

            return Servicio::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre' => $fila['NServicio'] !== '' ? $fila['NServicio'] : 'Servicio de Salud '.$codigo,
                    'region' => $fila['IdRegion'] !== '' ? $fila['IdRegion'] : null,
                    'activo' => (int) ($fila['ElimID'] ?: 0) === 0,
                ]
            );
        }

        throw CatalogoInvalidoException::servicioNoEncontrado($codigo);
    }

    /**
     * @return array<string, int> mapa serie interna y externa => serie_id
     */
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

            $serie = Serie::updateOrCreate(
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

    /** Solo los establecimientos del Servicio configurado (D-022). */
    private function importarEstablecimientos(string $directorio, Servicio $servicio): int
    {
        $vistos = [];

        foreach (CsvReader::leer($directorio.'/establecimientos.csv', self::COLUMNAS['establecimientos.csv']) as $linea => $fila) {
            if ((string) $fila['IdServicio'] !== (string) $servicio->codigo) {
                continue; // establecimiento de otro Servicio de Salud
            }

            $codigo = $fila['IdEstablecimiento'];

            if (isset($vistos[$codigo])) {
                throw CatalogoInvalidoException::duplicado('establecimientos.csv', $codigo, $linea);
            }

            $vistos[$codigo] = true;

            Establecimiento::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre'         => $fila['NEstablecimiento'],
                    'id_comuna'      => $fila['IdComuna'] !== '' ? (int) $fila['IdComuna'] : null,
                    'id_dependencia' => $fila['IdDependencia'] !== '' ? (int) $fila['IdDependencia'] : null,
                    'tipo'           => $fila['IdTipo'] !== '' ? $fila['IdTipo'] : null,
                    'activo'         => (int) ($fila['ElimID'] ?: 0) === 0,
                    'servicio_id'    => $servicio->id,
                ]
            );
        }

        return count($vistos);
    }

    /**
     * @param  array<string, int>  $series
     */
    private function importarPlantillas(string $directorio, array $series): int
    {
        $total = 0;

        foreach (CsvReader::leer($directorio.'/plantillas.csv', self::COLUMNAS['plantillas.csv']) as $fila) {
            $serieId = $series[$fila['SerieExcel']] ?? $series[$fila['Serie']] ?? null;

            if ($serieId === null) {
                continue;
            }

            // updateOrCreate y no firstOrCreate: una plantilla que ya existía
            // de una carga anterior se quedaba con `serie_interna` nula y
            // V5/V6 no encontraban sus hojas ni su diccionario.
            Plantilla::updateOrCreate(
                [
                    'anio'     => (int) $fila['ano'],
                    'mes'      => (int) $fila['mes'],
                    'serie_id' => $serieId,
                    'version'  => $fila['version'],
                ],
                [
                    'serie_excel'   => $fila['SerieExcel'],
                    'serie_interna' => $fila['Serie'],
                ]
            );

            $total++;
        }

        return $total;
    }

    /** Las hojas van por año y serie, no por plantilla mensual. */
    private function importarHojas(string $directorio): int
    {
        $total = 0;

        foreach (CsvReader::leer($directorio.'/plantilla_hojas.csv', self::COLUMNAS['plantilla_hojas.csv']) as $fila) {
            if ($fila['Serie'] === '' || $fila['ano'] === '') {
                continue;
            }

            PlantillaHoja::firstOrCreate(
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

            $total++;
        }

        return $total;
    }

    private function importarDiccionario(string $directorio): int
    {
        $lote = [];
        $total = 0;

        foreach (CsvReader::leer($directorio.'/diccionario.csv', self::COLUMNAS['diccionario.csv']) as $fila) {
            $serie = $fila['Serie'];

            if ($serie === '') {
                continue;
            }

            $lote[] = [
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
}
