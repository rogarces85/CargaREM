<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SerieController extends CatalogoController
{
    protected string $modelo = \App\Models\Serie::class;
    protected string $ruta = 'admin.series';
    protected string $titulo = 'Series';

    protected array $columnas = ['Código' => 'codigo', 'Nombre' => 'nombre', 'Orden' => 'orden', 'Activo' => 'activo'];

    protected array $campos = [
        ['name' => 'codigo', 'label' => 'Código', 'required' => true],
        ['name' => 'nombre', 'label' => 'Nombre', 'required' => true],
        ['name' => 'orden', 'label' => 'Orden', 'type' => 'number'],
        ['name' => 'activo', 'label' => 'Activa', 'type' => 'checkbox'],
    ];

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo' => ['required', 'string', 'max:5', Rule::unique('series', 'codigo')->ignore($registro?->getKey())],
            'nombre' => ['required', 'string', 'max:100'],
            'orden'  => ['nullable', 'integer', 'min:0'],
            'activo' => ['boolean'],
        ];
    }
}