<?php

namespace App\Http\Requests;

use App\Models\Envio;
use App\Rules\ZipSeguro;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Subida de un archivo de serie (T-F2-04).
 *
 * Aquí solo se comprueba la forma —tipo, tamaño, serie existente—. Las reglas
 * de negocio (V0 a V11) corren después, en el validador, porque necesitan abrir
 * el archivo y no deben decidirse en la capa HTTP.
 */
class SubirArchivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $envio = $this->route('envio');

        return $envio instanceof Envio && $this->user()->can('update', $envio);
    }

    public function rules(): array
    {
        $maxKb = (int) config('rem.archivos.max_kb');

        return [
            'serie_id' => ['required', 'integer', 'exists:series,id'],
            'archivo'  => ['required', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', (array) config('rem.archivos.extensiones'))],
        ];
    }

    public function messages(): array
    {
        $maxKb = (int) config('rem.archivos.max_kb');
        $extensiones = implode(', ', (array) config('rem.archivos.extensiones'));

        return [
            'archivo.max'   => "El archivo supera el máximo permitido de {$maxKb} KB.",
            'archivo.mimes' => "El archivo debe ser {$extensiones}.",
        ];
    }
}
