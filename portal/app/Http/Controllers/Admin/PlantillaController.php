<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plantilla;
use App\Models\PlantillaHoja;
use App\Models\Serie;use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Gestión de plantillas (RF-012).
 *
 * Una plantilla es la versión esperada del Excel para un año, mes y serie. Las
 * hojas van por año y serie interna (no por plantilla mensual): se editan como
 * líneas `HOJA|RANGO` dentro del mismo formulario.
 *
 * `plantilla_cabecera` no se edita: son las etiquetas del formato oficial y se
 * cargan con el seeder de catálogo.
 */
class PlantillaController extends Controller
{
    public function index(): View
    {
        return view('admin.plantillas.index', [
            'plantillas' => Plantilla::with('serie')
                ->orderByDesc('anio')
                ->orderByDesc('mes')
                ->orderBy('serie_id')
                ->paginate(50),
        ]);
    }

    public function create(): View
    {
        return $this->formulario(new Plantilla(), route('admin.plantillas.store'), 'POST', 'Nueva plantilla');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request, null);

        $plantilla = Plantilla::create($this->atributos($datos));
        $this->sincronizarHojas($plantilla, $datos['hojas'] ?? '');

        return redirect()
            ->route('admin.plantillas.index')
            ->with('ok', 'Plantilla creada.');
    }

    public function edit(Plantilla $plantilla): View
    {
        return $this->formulario(
            $plantilla,
            route('admin.plantillas.update', $plantilla),
            'PUT',
            'Editar plantilla'
        );
    }

    public function update(Request $request, Plantilla $plantilla): RedirectResponse
    {
        $datos = $this->validar($request, $plantilla);

        $plantilla->update($this->atributos($datos));
        $this->sincronizarHojas($plantilla, $datos['hojas'] ?? '');

        return redirect()
            ->route('admin.plantillas.index')
            ->with('ok', 'Plantilla actualizada.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Plantilla $plantilla): array
    {
        return $request->validate([
            'anio'          => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes'           => ['required', 'integer', 'min:1', 'max:12'],
            'serie_id'      => ['required', 'integer', 'exists:series,id'],
            'serie_excel'   => ['required', 'string', 'max:60'],
            'serie_interna' => ['required', 'string', 'max:60'],
            'version'       => [
                'required', 'string', 'max:20',
                Rule::unique('plantillas', 'version')
                    ->where('anio', $request->integer('anio'))
                    ->where('mes', $request->integer('mes'))
                    ->where('serie_id', $request->integer('serie_id'))
                    ->ignore($plantilla?->getKey()),
            ],
            'hojas'         => ['nullable', 'string'],
        ], [
            'version.unique' => 'Ya existe esa versión para el mismo año, mes y serie.',
        ]);
    }

    /** @param array<string, mixed> $datos */
    private function atributos(array $datos): array
    {
        return [
            'anio'          => (int) $datos['anio'],
            'mes'           => (int) $datos['mes'],
            'serie_id'      => (int) $datos['serie_id'],
            'serie_excel'   => $datos['serie_excel'],
            'serie_interna' => $datos['serie_interna'],
            'version'       => $datos['version'],
        ];
    }

    /**
     * Reemplaza las hojas del par (año, serie interna).
     *
     * Formato: una hoja por línea, `NOMBRE|RANGO`. Sin rango, se deja nulo.
     */
    private function sincronizarHojas(Plantilla $plantilla, string $texto): void
    {
        PlantillaHoja::where('anio', $plantilla->anio)
            ->where('serie_interna', $plantilla->serie_interna)
            ->delete();

        foreach (preg_split('/\r\n|\r|\n/', $texto) ?: [] as $linea) {
            $linea = trim($linea);

            if ($linea === '') {
                continue;
            }

            [$hoja, $rango] = array_pad(array_map('trim', explode('|', $linea, 2)), 2, null);

            if ($hoja === '') {
                continue;
            }

            PlantillaHoja::create([
                'anio'          => $plantilla->anio,
                'serie_interna' => $plantilla->serie_interna,
                'hoja'          => $hoja,
                'rango'         => $rango !== '' ? $rango : null,
                'obligatoria'   => true,
            ]);
        }
    }

    private function formulario(Plantilla $plantilla, string $accion, string $metodo, string $titulo): View
    {
        $hojas = PlantillaHoja::where('anio', $plantilla->anio)
            ->where('serie_interna', $plantilla->serie_interna)
            ->orderBy('hoja')
            ->get()
            ->map(fn (PlantillaHoja $h) => $h->hoja.($h->rango ? '|'.$h->rango : ''))
            ->implode("\n");

        return view('admin.plantillas.form', [
            'titulo'    => $titulo,
            'plantilla' => $plantilla,
            'accion'    => $accion,
            'metodo'    => $metodo,
            'series'    => Serie::orderBy('codigo')->pluck('codigo', 'id')->all(),
            'hojas'     => old('hojas', $hojas),
        ]);
    }
}
