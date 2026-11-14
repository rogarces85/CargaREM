<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class EstablecimientoController extends CatalogoController
{
    protected string $modelo = \App\Models\Establecimiento::class;
    protected string $ruta = 'admin.establecimientos';
    protected string $titulo = 'Establecimientos';

    protected array $columnas = ['Código' => 'codigo', 'Nombre' => 'nombre', 'Tipo' => 'tipo', 'Activo' => 'activo'];

    protected array $campos = [
        ['name' => 'codigo', 'label' => 'Código', 'required' => true],
        ['name' => 'nombre', 'label' => 'Nombre', 'required' => true],
        ['name' => 'tipo', 'label' => 'Tipo'],
        ['name' => 'activo', 'label' => 'Activo', 'type' => 'checkbox'],
    ];

    /** El Servicio se elige de los que existen: un establecimiento siempre le pertenece a uno. */
    protected function camposConOpciones(Model $registro): array
    {
        return [
            ...$this->campos,
            [
                'name'    => 'servicio_id',
                'label'   => 'Servicio de Salud',
                'type'    => 'select',
                'options' => \App\Models\Servicio::orderBy('codigo')->pluck('nombre', 'id')->all(),
            ],
        ];
    }

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo'      => ['required', 'string', 'max:20', Rule::unique('establecimientos', 'codigo')->ignore($registro?->getKey())],
            'nombre'      => ['required', 'string', 'max:200'],
            'tipo'        => ['nullable', 'string', 'max:50'],
            'servicio_id' => ['nullable', 'integer', 'exists:servicios,id'],
            'activo'      => ['boolean'],
        ];
    }
}