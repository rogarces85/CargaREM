<?php

namespace App\Http\Controllers\Admin;

use App\Models\Feriado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Feriados del calendario (D-029).
 *
 * Se administran a mano porque la lista oficial cambia cada año y el portal no
 * depende de internet en runtime.
 */
class FeriadoController extends CatalogoController
{
    protected string $modelo = Feriado::class;
    protected string $ruta = 'admin.feriados';
    protected string $titulo = 'Feriados';

    protected array $columnas = [
        'Fecha'   => 'fecha',
        'Nombre'  => 'nombre',
        'Ámbito'  => 'ambito',
        'Región'  => 'region',
    ];

    protected array $campos = [
        ['name' => 'fecha', 'label' => 'Fecha', 'type' => 'date', 'required' => true],
        ['name' => 'nombre', 'label' => 'Nombre', 'required' => true],
        ['name' => 'ambito', 'label' => 'Ámbito', 'type' => 'select'],
        ['name' => 'region', 'label' => 'Región (opcional)'],
    ];

    protected function camposConOpciones(Model $registro): array
    {
        $campos = $this->campos;

        $campos[2]['options'] = [
            Feriado::NACIONAL => 'Nacional',
            Feriado::REGIONAL => 'Regional',
        ];

        return $campos;
    }

    protected function reglas(?Model $registro): array
    {
        return [
            'fecha'  => ['required', 'date', Rule::unique('feriados', 'fecha')->ignore($registro?->getKey())],
            'nombre' => ['required', 'string', 'max:120'],
            'ambito' => ['required', Rule::in([Feriado::NACIONAL, Feriado::REGIONAL])],
            'region' => ['nullable', 'string', 'max:80'],
        ];
    }
}
