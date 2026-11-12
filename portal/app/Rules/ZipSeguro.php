<?php

namespace App\Rules;

use App\Models\EnvioArchivo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;
use ZipArchive;

/**
 * Rechaza archivos que esconden una bomba de descompresión (T-F6-03).
 *
 * Un .xlsx es un ZIP. Un ZIP de 2 MB puede expandirse a gigabytes si contiene
 * entradas muy comprimidas; abrirlo con PhpSpreadsheet consume la memoria del
 * proceso y tumba la validación de todos.
 *
 * Se comprueba el ratio y la cantidad de entradas antes de abrirlo. Un Excel
 * real tiene decenas de entradas y un ratio moderado.
 */
class ZipSeguro implements ValidationRule
{
    private const MAX_ENTRADAS = 1000;

    private const MAX_RATIO = 100;

    private const MAX_DESCOMPRIMIDO_MB = 200;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof \Illuminate\Http\UploadedFile) {
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        // Un .xls es OLE2, no ZIP: no aplica esta comprobación.
        if ($extension === 'xls') {
            return;
        }

        $zip = new ZipArchive();

        try {
            // Abrir sin extraer: solo se lee el índice.
            if ($zip->open($value->getRealPath()) !== true) {
                $fail('El archivo no es un Excel válido: no se pudo leer su estructura.');

                return;
            }

            if ($zip->numFiles > self::MAX_ENTRADAS) {
                $fail('El archivo tiene demasiadas partes internas ('.$zip->numFiles.'). ¿Es realmente un Excel?');

                return;
            }

            $comprimido = 0;
            $descomprimido = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    continue;
                }

                $comprimido += (int) $stat['comp_size'];
                $descomprimido += (int) $stat['size'];
            }

            $ratio = $comprimido > 0 ? $descomprimido / $comprimido : 0;

            if ($ratio > self::MAX_RATIO) {
                $fail(sprintf(
                    'El archivo se expande %.0f veces al abrirlo (máximo %d). Por seguridad se rechaza.',
                    $ratio,
                    self::MAX_RATIO
                ));

                return;
            }

            if ($descomprimido > self::MAX_DESCOMPRIMIDO_MB * 1024 * 1024) {
                $fail(sprintf(
                    'El contenido del archivo supera los %d MB descomprimido. Por seguridad se rechaza.',
                    self::MAX_DESCOMPRIMIDO_MB
                ));
            }
        } catch (Throwable $e) {
            report($e);
            $fail('No se pudo revisar la estructura del archivo.');
        } finally {
            $zip->close();
        }
    }
}
