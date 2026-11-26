<?php

namespace App\Http\Controllers\Admin;

use App\Models\Feriado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Feriados del calendario (D-029).
 *
 * Se administran a mano porque la lista oficial cambia cada año y el portal no
 * depende de internet en runtime. La lista se siembra con los años verificados
 * (`rem:feriados:cargar`) y el administrador corrige o suma lo que falte.
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

    /**
     * Los feriados se ordenan por fecha, no por id.
     *
     * El CRUD genérico ordena por id, que para un catálogo es razonable pero
     * para un calendario no: al cargarse dos años seguidos, el más reciente
     * quedaba al final de la segunda página. Se listan del más próximo al más
     * antiguo, que es como se revisan.
     */
    public function index(): View
    {
        return view('admin.index', [
            'titulo'   => $this->titulo,
            'ruta'     => $this->ruta,
            'columnas' => $this->columnas,
            'filas'    => Feriado::query()->orderByDesc('fecha')->paginate(50),
        ]);
    }

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
