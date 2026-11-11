<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD genérico de catálogos (T-F1-22).
 *
 * Las seis entidades del catálogo comparten la misma forma: listado, alta,
 * edición y validación. Lo único que cambia es el modelo, las columnas y las
 * reglas, así que se declara eso y no se repite el controlador seis veces.
 */
abstract class CatalogoController extends Controller
{
    /** @var class-string<Model> */
    protected string $modelo;

    /** Prefijo de ruta, p. ej. "admin.servicios". */
    protected string $ruta;

    protected string $titulo;

    /** @var array<string, string> etiqueta visible => atributo */
    protected array $columnas = [];

    /** @var list<array<string, mixed>> */
    protected array $campos = [];

    /** @return array<string, mixed> */
    abstract protected function reglas(?Model $registro): array;

    public function index(): View
    {
        $filas = $this->modelo::query()->orderBy('id')->paginate(25);

        return view('admin.index', [
            'titulo'   => $this->titulo,
            'ruta'     => $this->ruta,
            'columnas' => $this->columnas,
            'filas'    => $filas,
        ]);
    }

    public function create(): View
    {
        return $this->formulario(new $this->modelo(), route($this->ruta.'.store'), 'POST', 'Nuevo');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate($this->reglas(null));

        $this->modelo::create($datos);

        return redirect()
            ->route($this->ruta.'.index')
            ->with('ok', 'Registro creado.');
    }

    public function edit(string|int $id): View
    {
        $registro = $this->modelo::findOrFail($id);

        return $this->formulario($registro, route($this->ruta.'.update', $registro), 'PUT', 'Editar');
    }

    public function update(Request $request, string|int $id): RedirectResponse
    {
        $registro = $this->modelo::findOrFail($id);

        $datos = $request->validate($this->reglas($registro));

        $registro->update($datos);

        return redirect()
            ->route($this->ruta.'.index')
            ->with('ok', 'Registro actualizado.');
    }

    protected function formulario(Model $registro, string $accion, string $metodo, string $prefijo): View
    {
        return view('admin.form', [
            'titulo'   => $prefijo.' · '.$this->titulo,
            'ruta'     => $this->ruta,
            'campos'   => $this->camposConOpciones($registro),
            'registro' => $registro,
            'accion'   => $accion,
            'metodo'   => $metodo,
        ]);
    }

    /** @return list<array<string, mixed>> */
    protected function camposConOpciones(Model $registro): array
    {
        return $this->campos;
    }

    protected function opcionesServicios(): array
    {
        return \App\Models\Servicio::orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    protected function opcionesSeries(): array
    {
        return \App\Models\Serie::orderBy('codigo')->pluck('codigo', 'id')->all();
    }
}
