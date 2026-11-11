<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ServicioController extends CatalogoController
{
    protected string $modelo = \App\Models\Servicio::class;
    protected string $ruta = 'admin.servicios';
    protected string $titulo = 'Servicios de Salud';

    protected array $columnas = ['Código' => 'codigo', 'Nombre' => 'nombre', 'Región' => 'region', 'Activo' => 'activo'];

    protected array $campos = [
        ['name' => 'codigo', 'label' => 'Código', 'required' => true],
        ['name' => 'nombre', 'label' => 'Nombre', 'required' => true],
        ['name' => 'region', 'label' => 'Región'],
        ['name' => 'activo', 'label' => 'Activo', 'type' => 'checkbox'],
    ];

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo' => ['required', 'string', 'max:10', Rule::unique('servicios', 'codigo')->ignore($registro?->getKey())],
            'nombre' => ['required', 'string', 'max:150'],
            'region' => ['nullable', 'string', 'max:80'],
            'activo' => ['boolean'],
        ];
    }
}