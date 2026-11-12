<?php

namespace App\Services;

use App\Exceptions\EnvioInvalidoException;
use App\Models\Envio;
use App\Models\EnvioArchivo;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;
use ZipArchive;

/**
 * Paquete de un envío aprobado (T-F5-01, T-F5-02, OP-11).
 *
 * Un ZIP con los archivos originales, el reporte de validación de cada serie y
 * un manifiesto que dice qué contiene y con qué catálogo se validó.
 *
 * El manifiesto es la parte importante: dentro de un año, el ZIP sin manifiesto
 * es un montón de Excel sin procedencia.
 */
class PaqueteService
{
    public const VERSION_MANIFIESTO = '1.0';

    public function __construct(private readonly ReporteService $reportes)
    {
    }

    /**
     * Genera el ZIP en un archivo temporal y devuelve su ruta.
     */
    public function generar(Envio $envio): string
    {
        if (! in_array($envio->estado, [Envio::APROBADO, Envio::CARGADO], true)) {
            throw EnvioInvalidoException::paqueteNoDisponible($envio->estado);
        }

        $envio->loadMissing(
            'establecimiento.servicio',
            'archivosVigentes.serie',
            'archivosVigentes.snapshot:id,origen,created_at',
            'archivos.validaciones',
            'revisor'
        );

        $temporal = tempnam(sys_get_temp_dir(), 'paquete_').'.zip';

        $zip = new ZipArchive();

        if ($zip->open($temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el ZIP del paquete.');
        }

        $detalleArchivos = [];

        foreach ($envio->archivosVigentes->sortBy(fn ($a) => $a->serie?->orden) as $archivo) {
            $extension = strtolower(pathinfo($archivo->ruta, PATHINFO_EXTENSION));
            $codigo = $archivo->serie?->codigo ?? (string) $archivo->serie_id;

            $rutaOriginal = Storage::disk($archivo->disco)->path($archivo->ruta);

            if (is_file($rutaOriginal)) {
                $zip->addFile($rutaOriginal, "archivos/{$codigo}.{$extension}");
            }

            // Reporte de la serie, generado al vuelo: el paquete lleva el
            // veredicto, no solo los datos.
            $zip->addFromString("validacion/{$codigo}.xlsx", $this->reporteDeSerie($archivo));

            $detalleArchivos[] = [
                'serie'       => $codigo,
                'archivo'     => "archivos/{$codigo}.{$extension}",
                'reporte'     => "validacion/{$codigo}.xlsx",
                'nombre_original' => $archivo->nombre_original,
                'sha256'      => $archivo->sha256,
                'tamano_bytes' => $archivo->tamano_bytes,
                'version'     => $archivo->version,
                'estado'      => $archivo->estado,
                'filas_validas' => $archivo->filas_validas,
                'bloqueantes' => $archivo->validaciones->where('severidad', 'bloqueante')->count(),
                'advertencias' => $archivo->validaciones->where('severidad', '!=', 'bloqueante')->count(),
                'plantilla'   => $archivo->plantilla_id,
            ];
        }

        $manifiesto = $this->manifiesto($envio, $detalleArchivos);

        $zip->addFromString(
            'manifiesto.json',
            json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $zip->close();

        return $temporal;
    }

    /**
     * Contenido del manifiesto, con su hash y su firma (T-F5-02).
     *
     * @param  list<array<string, mixed>>  $archivos
     * @return array<string, mixed>
     */
    public function manifiesto(Envio $envio, array $archivos): array
    {
        $manifiesto = [
            'version'        => self::VERSION_MANIFIESTO,
            'envio_id'       => $envio->id,
            'establecimiento' => [
                'codigo' => $envio->establecimiento->codigo,
                'nombre' => $envio->establecimiento->nombre,
            ],
            'servicio' => [
                'codigo' => $envio->establecimiento->servicio?->codigo,
                'nombre' => $envio->establecimiento->servicio?->nombre,
            ],
            'periodo' => [
                'anio' => (int) $envio->anio,
                'mes'  => (int) $envio->mes,
            ],
            'estado'  => $envio->estado,
            'aprobado_por'     => $envio->revisor?->name,
            'fecha_aprobacion' => $envio->fecha_aprobacion?->toIso8601String(),
            'fecha_carga'      => $envio->fecha_carga?->toIso8601String(),
            'snapshot_catalogo' => [
                // Cada archivo guarda con qué catálogo se validó; el del
                // primero representa al envío, que se validó de una vez.
                'id'           => $envio->archivosVigentes->first()?->snapshot_id,
                'origen'       => $envio->archivosVigentes->first()?->snapshot?->origen,
                'importado_en' => $envio->archivosVigentes->first()?->snapshot?->created_at?->toIso8601String(),
            ],
            'archivos'    => $archivos,
            'generado_en' => now()->toIso8601String(),
        ];

        $manifiesto['hash_paquete'] = $this->hashPaquete($archivos, $manifiesto);
        $manifiesto['hmac'] = $this->hmac($manifiesto['hash_paquete']);

        return $manifiesto;
    }

    /**
     * SHA-256 de los hashes de los archivos en orden más el manifiesto sin la
     * firma. Si alguien cambia un archivo o una línea del manifiesto, el hash
     * deja de coincidir.
     *
     * @param  list<array<string, mixed>>  $archivos
     * @param  array<string, mixed>  $manifiesto
     */
    public function hashPaquete(array $archivos, array $manifiesto): string
    {
        $hashes = implode('', array_map(fn (array $a) => (string) $a['sha256'], $archivos));

        unset($manifiesto['hash_paquete'], $manifiesto['hmac'], $manifiesto['generado_en']);

        return hash('sha256', $hashes.json_encode($manifiesto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * HMAC-SHA256 del hash del paquete.
     *
     * Sin clave configurada devuelve null: el paquete se entrega igual, y el
     * manifiesto dice que no está firmado. Fingir una firma sería peor.
     */
    public function hmac(string $hashPaquete): ?string
    {
        $clave = (string) config('rem.paquete.hmac_key');

        if ($clave === '') {
            return null;
        }

        return hash_hmac('sha256', $hashPaquete, $clave);
    }

    /** Verifica que un manifiesto no fue alterado. */
    public function verificar(array $manifiesto): bool
    {
        if (! isset($manifiesto['hash_paquete'], $manifiesto['archivos'])) {
            return false;
        }

        $esperado = $this->hashPaquete($manifiesto['archivos'], $manifiesto);

        if (! hash_equals($esperado, (string) $manifiesto['hash_paquete'])) {
            return false;
        }

        if (! empty($manifiesto['hmac'])) {
            $firma = $this->hmac($manifiesto['hash_paquete']);

            return $firma !== null && hash_equals($firma, (string) $manifiesto['hmac']);
        }

        return true;
    }

    /** Reporte de validación de una serie, como XLSX en memoria. */
    private function reporteDeSerie(EnvioArchivo $archivo): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Validación');

        $hoja->setCellValue('A1', 'Reporte de validación · serie '.($archivo->serie?->codigo ?? ''));
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $encabezados = ['Etapa', 'Código', 'Severidad', 'Hoja', 'Celda', 'Mensaje'];

        foreach ($encabezados as $i => $titulo) {
            $hoja->setCellValue([$i + 1, 3], $titulo);
        }

        $hoja->getStyle('A3:F3')->getFont()->setBold(true);

        $fila = 4;

        foreach ($archivo->validaciones->sortBy('etapa') as $hallazgo) {
            $hoja->setCellValue([1, $fila], $hallazgo->etapa);
            $hoja->setCellValue([2, $fila], $hallazgo->codigo);
            $hoja->setCellValue([3, $fila], $hallazgo->severidad);
            $hoja->setCellValue([4, $fila], $hallazgo->hoja);
            $hoja->setCellValue([5, $fila], $hallazgo->celda);
            $hoja->setCellValue([6, $fila], $hallazgo->mensaje);
            $fila++;
        }

        foreach (['A' => 8, 'B' => 12, 'C' => 14, 'D' => 10, 'E' => 10, 'F' => 80] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }

        $temporal = tempnam(sys_get_temp_dir(), 'rep_').'.xlsx';

        try {
            (new Xlsx($libro))->save($temporal);

            return (string) file_get_contents($temporal);
        } finally {
            @unlink($temporal);
            $libro->disconnectWorksheets();
        }
    }
}
