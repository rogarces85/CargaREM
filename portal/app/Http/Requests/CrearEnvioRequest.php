<?php

namespace App\Http\Requests;

use App\Models\Envio;
use App\Models\Establecimiento;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de un envío (T-F2-04).
 *
 * La autorización pregunta por el establecimiento concreto: el permiso dice
 * que puede crear envíos, la policy dice en cuáles.
 */
class CrearEnvioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $establecimiento = Establecimiento::find($this->input('establecimiento_id'));

        if ($establecimiento === null) {
            return false;
        }

        return $this->user()->can('create', [Envio::class, $establecimiento]);
    }

    public function rules(): array
    {
        return [
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'periodo'            => ['required', 'regex:/^\d{4}-(?:[1-9]|1[0-2])$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'periodo.regex' => 'Elija un período válido.',
        ];
    }

    /** @return array{0: int, 1: int} año y mes */
    public function periodo(): array
    {
        return array_map('intval', explode('-', (string) $this->input('periodo')));
    }
}
