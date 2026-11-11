<?php

namespace App\Services;

use App\Exceptions\ArchivoRechazadoException;
use App\Models\Envio;
use App\Models\Serie;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Almacenamiento privado de los Excel (T-F2-05).
 *
 * Tres reglas que no se negocian:
 * 1. El archivo vive fuera de `public/` (principio 19).
 * 2. El nombre lo genera el servidor: nunca se usa el que subió el usuario,
 *    que es la entrada clásica de un path traversal.
 * 3. El tamaño se valida contra la configuración, no contra php.ini: el
 *    servidor tiene 2 MB y el local 256 MB.
 */
class ArchivoService
{
    /** Firma de un archivo ZIP, que es lo que hay dentro de un .xlsx/.xlsm. */
    private const FIRMA_ZIP = "PK\x03\x04";

    /** Firma OLE2, el contenedor de un .xls clásico. */
    private const FIRMA_OLE2 = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    /**
     * Valida y guarda el archivo. Devuelve lo que hay que persistir.
     *
     * @return array{ruta: string, sha256: string, tamano_bytes: int, mime: string, extension: string}
     */
    public function guardar(UploadedFile $archivo, Envio $envio, Serie $serie, int $version): array
    {
        $this->validar($archivo);

        $extension = strtolower($archivo->getClientOriginalExtension());
        $contenido = file_get_contents($archivo->getRealPath());

        $directorio = sprintf(
            '%s/%d/%02d/%s/v%d',
            config('rem.archivos.ruta'),
            $envio->anio,
            $envio->mes,
            $envio->establecimiento->codigo,
            $version
        );

        // Nombre generado: el original solo se guarda como dato, nunca como ruta.
        $nombre = Str::uuid()->toString().'.'.$extension;

        $ruta = $archivo->storeAs($directorio, $nombre, config('rem.archivos.disco'));

        return [
            'ruta'         => $ruta,
            'sha256'       => hash('sha256', $contenido),
            'tamano_bytes' => (int) $archivo->getSize(),
            'mime'         => (string) $archivo->getMimeType(),
            'extension'    => $extension,
        ];
    }

    /**
     * Validación V0 previa al almacenamiento (ENV-001 a ENV-003).
     */
    public function validar(UploadedFile $archivo): void
    {
        $maxKb = (int) config('rem.archivos.max_kb');
        $kb = (int) ceil($archivo->getSize() / 1024);

        if ($kb > $maxKb) {
            throw ArchivoRechazadoException::demasiadoGrande($kb, $maxKb);
        }

        $permitidas = (array) config('rem.archivos.extensiones');
        $extension = strtolower($archivo->getClientOriginalExtension());

        if (! in_array($extension, $permitidas, true)) {
            throw ArchivoRechazadoException::extension($extension, $permitidas);
        }

        // No se confía en el navegador ni en la extensión: se mira la firma
        // real del contenido (principio 19).
        $firma = (string) file_get_contents($archivo->getRealPath(), false, null, 0, 8);

        $esZip = str_starts_with($firma, self::FIRMA_ZIP);
        $esOle2 = str_starts_with($firma, self::FIRMA_OLE2);

        if (! $esZip && ! $esOle2) {
            throw ArchivoRechazadoException::noEsExcel($archivo->getClientOriginalName());
        }
    }
}
