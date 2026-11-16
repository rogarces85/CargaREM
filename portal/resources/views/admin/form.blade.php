@extends('layouts.panel')

@section('titulo', $titulo)

@section('contenido')
    <div class="mx-auto max-w-3xl space-y-6">

        <x-ui.page-header
            :titulo="$titulo"
            :migas="['Panel' => route('panel'), $ruta => route($ruta . '.index'), 'Editar' => null]"
        />

        <form method="POST" action="{{ $accion }}" class="space-y-6">
            @csrf
            @if ($metodo !== 'POST')
                @method($metodo)
            @endif

            <x-ui.card class="p-5">
                <div class="space-y-4">
                    @foreach ($campos as $campo)
                        @php
                            $nombre = $campo['name'];
                            $tipo = $campo['type'] ?? 'text';
                            $valor = old($nombre, $registro?->{$nombre});
                            $error = $errors->first($nombre);
                        @endphp

                        @if ($tipo === 'checkbox')
                            <div>
                                <input type="hidden" name="{{ $nombre }}" value="0">
                                <label for="{{ $nombre }}" class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input type="checkbox" name="{{ $nombre }}" id="{{ $nombre }}" value="1"
                                           class="check"
                                           @checked(old($nombre, $registro?->{$nombre} ?? true))>
                                    {{ $campo['label'] }}
                                </label>

                                @if ($error)
                                    <p class="campo-error">
                                        <x-ui.icon name="circle-exclamation-point" class="mt-px size-3.5 shrink-0" />
                                        <span>{{ $error }}</span>
                                    </p>
                                @endif
                            </div>
                        @else
                            <x-ui.field :nombre="$nombre" :etiqueta="$campo['label']" :error="$error"
                                        :requerido="! empty($campo['required'])">
                                @if ($tipo === 'select')
                                    <select name="{{ $nombre }}" id="{{ $nombre }}" class="select"
                                            @if (! empty($campo['required'])) required @endif
                                            @if ($error) aria-invalid="true" aria-describedby="{{ $nombre }}-error" @endif>
                                        @foreach ($campo['options'] as $opcionValor => $opcionTexto)
                                            <option value="{{ $opcionValor }}" @selected((string) $valor === (string) $opcionValor)>
                                                {{ $opcionTexto }}
                                            </option>
                                        @endforeach
                                    </select>
                                @elseif ($tipo === 'textarea')
                                    <textarea name="{{ $nombre }}" id="{{ $nombre }}" rows="4" class="textarea"
                                              @if (! empty($campo['required'])) required @endif
                                              @if ($error) aria-invalid="true" aria-describedby="{{ $nombre }}-error" @endif>{{ $valor }}</textarea>
                                @else
                                    <input type="{{ $tipo }}" name="{{ $nombre }}" id="{{ $nombre }}"
                                           value="{{ $tipo === 'password' ? '' : $valor }}"
                                           class="input @if ($tipo === 'number') tabular @endif"
                                           @if (! empty($campo['required'])) required @endif
                                           @if ($error) aria-invalid="true" aria-describedby="{{ $nombre }}-error" @endif
                                           @if (! empty($campo['placeholder'])) placeholder="{{ $campo['placeholder'] }}" @endif>
                                @endif
                            </x-ui.field>
                        @endif
                    @endforeach
                </div>
            </x-ui.card>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="check" class="size-4" />
                    Guardar
                </button>
                <a href="{{ route($ruta . '.index') }}" class="btn btn-ghost">Cancelar</a>
            </div>
        </form>

    </div>
@endsection
