<?php

namespace App\Http\Controllers\Admin;

use App\Models\Periodo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PeriodoController extends CatalogoController
{
    protected string $modelo = Periodo::class;
    protected string $ruta = 'admin.periodos';
    protected string $titulo = 'Períodos';

    protected array $columnas = ['Año' => 'anio', 'Mes' => 'mes', 'Fecha límite' => 'fecha_limite', 'Estado' => 'estado'];

    protected array $campos = [
        ['name' => 'anio', 'label' => 'Año', 'type' => 'number', 'required' => true],
        ['name' => 'mes', 'label' => 'Mes', 'type' => 'number', 'required' => true],
        ['name' => 'fecha_limite', 'label' => 'Fecha límite', 'type' => 'date'],
        ['name' => 'estado', 'label' => 'Estado', 'type' => 'select'],
    ];

    protected function camposConOpciones(Model $registro): array
    {
        $campos = $this->campos;

        $campos[3]['options'] = [
            Periodo::ABIERTO    => 'Abierto',
            Periodo::EN_REVISION => 'En revisión',
            Periodo::CERRADO    => 'Cerrado',
        ];

        return $campos;
    }

    protected function reglas(?Model $registro): array
    {
        return [
            'anio'         => ['required', 'integer', 'min:2010', 'max:2100'],
            // El período es único por (año, mes). Sin esta regla, el duplicado
            // llegaba a la base y salía como error 500 en vez de validación.
            'mes' => [
                'required', 'integer', 'min:1', 'max:12',
                Rule::unique('periodos', 'mes')
                    ->where(fn ($query) => $query->where('anio', request('anio')))
                    ->ignore($registro?->getKey()),
            ],
            'fecha_limite' => ['nullable', 'date'],
            'estado'       => ['required', Rule::in([Periodo::ABIERTO, Periodo::EN_REVISION, Periodo::CERRADO])],
        ];
    }
}