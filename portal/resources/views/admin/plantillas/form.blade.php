@extends('layouts.panel')

@section('titulo', $titulo)

@section('contenido')
    <div class="mx-auto max-w-3xl space-y-6">

        <x-ui.page-header
            :titulo="$titulo"
            :migas="['Panel' => route('panel'), 'Plantillas' => route('admin.plantillas.index'), 'Editar' => null]"
        />

        <x-ui.alert tono="info">
            La plantilla define la versión esperada del Excel para el año, mes y serie.
            Las hojas se guardan por año y serie interna: una por línea, con el formato <code>HOJA|RANGO</code>.
        </x-ui.alert>

        <form method="POST" action="{{ $accion }}" class="space-y-6">
            @csrf
            @if ($metodo !== 'POST')
                @method($metodo)
            @endif

            <x-ui.card class="p-5">
                <div class="grid gap-4 sm:grid-cols-4">
                    <x-ui.field nombre="anio" etiqueta="Año" :error="$errors->first('anio')" requerido>
                        <input type="number" name="anio" id="anio" min="2000" max="2100" required
                               value="{{ old('anio', $plantilla->anio) }}" class="input tabular">
                    </x-ui.field>

                    <x-ui.field nombre="mes" etiqueta="Mes" :error="$errors->first('mes')" requerido>
                        <input type="number" name="mes" id="mes" min="1" max="12" required
                               value="{{ old('mes', $plantilla->mes) }}" class="input tabular">
                    </x-ui.field>

                    <x-ui.field nombre="serie_id" etiqueta="Serie" :error="$errors->first('serie_id')" requerido>
                        <select name="serie_id" id="serie_id" class="select" required>
                            @foreach ($series as $id => $codigo)
                                <option value="{{ $id }}" @selected((string) old('serie_id', $plantilla->serie_id) === (string) $id)>
                                    {{ $codigo }}
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field nombre="version" etiqueta="Versión" :error="$errors->first('version')" requerido>
                        <input type="text" name="version" id="version" maxlength="20" required
                               value="{{ old('version', $plantilla->version) }}" class="input">
                    </x-ui.field>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-ui.field nombre="serie_excel" etiqueta="Serie del Excel" :error="$errors->first('serie_excel')" requerido>
                        <input type="text" name="serie_excel" id="serie_excel" maxlength="60" required
                               value="{{ old('serie_excel', $plantilla->serie_excel) }}" class="input">
                    </x-ui.field>

                    <x-ui.field nombre="serie_interna" etiqueta="Serie interna" :error="$errors->first('serie_interna')" requerido>
                        <input type="text" name="serie_interna" id="serie_interna" maxlength="60" required
                               value="{{ old('serie_interna', $plantilla->serie_interna) }}" class="input">
                    </x-ui.field>
                </div>

                <div class="mt-4">
                    <x-ui.field nombre="hojas" etiqueta="Hojas obligatorias" :error="$errors->first('hojas')"
                                ayuda="Una hoja por línea, por ejemplo A01|C6:V40. Sin rango, se deja vacío.">
                        <textarea name="hojas" id="hojas" rows="6" class="textarea font-mono text-xs">{{ $hojas }}</textarea>
                    </x-ui.field>
                </div>
            </x-ui.card>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="check" class="size-4" />
                    Guardar
                </button>
                <a href="{{ route('admin.plantillas.index') }}" class="btn btn-ghost">Cancelar</a>
            </div>
        </form>

    </div>
@endsection
