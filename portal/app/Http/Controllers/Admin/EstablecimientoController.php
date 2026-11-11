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
        ['name' => 'servicio_id', 'label' => 'Servicio de Salud', 'type' => 'select', 'required' => true],
        ['name' => 'tipo', 'label' => 'Tipo'],
        ['name' => 'activo', 'label' => 'Activo', 'type' => 'checkbox'],
    ];

    protected function camposConOpciones(Model $registro): array
    {
        $campos = $this->campos;
        $campos[2]['options'] = $this->opcionesServicios();

        return $campos;
    }

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo'      => ['required', 'string', 'max:20', Rule::unique('establecimientos', 'codigo')->ignore($registro?->getKey())],
            'nombre'      => ['required', 'string', 'max:200'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'tipo'        => ['nullable', 'string', 'max:50'],
            'activo'      => ['boolean'],
        ];
    }
}