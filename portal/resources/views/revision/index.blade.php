@extends('layouts.panel')

@section('titulo', 'Revisión')

@section('contenido')
    <div class="mx-auto max-w-6xl space-y-6">

        <x-ui.page-header
            titulo="Bandeja de revisión"
            subtitulo="Envíos de los establecimientos del Servicio de Salud. Los más antiguos primero."
            :migas="['Panel' => route('panel'), 'Revisión' => null]"
        />

        {{-- Filtros --}}
        <x-ui.card class="p-5">
            <form method="GET" action="{{ route('revision.index') }}"
                  class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                <x-ui.field nombre="estado" etiqueta="Estado">
                    <select name="estado" id="estado" class="select">
                        @foreach ($estados as $valor => $texto)
                            <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === (string) $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field nombre="establecimiento_id" etiqueta="Establecimiento" class="lg:col-span-2">
                    <select name="establecimiento_id" id="establecimiento_id" class="select">
                        <option value="">Todos</option>
                        @foreach ($establecimientos as $establecimiento)
                            <option value="{{ $establecimiento->id }}" @selected(($filtros['establecimiento_id'] ?? '') == $establecimiento->id)>
                                {{ $establecimiento->codigo }} — {{ $establecimiento->nombre }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field nombre="anio" etiqueta="Año">
                    <input type="number" name="anio" id="anio" value="{{ $filtros['anio'] ?? '' }}"
                           min="2000" max="2100" placeholder="Todos" class="input tabular">
                </x-ui.field>

                <x-ui.field nombre="dias" etiqueta="Antigüedad">
                    <select name="dias" id="dias" class="select">
                        <option value="">Cualquiera</option>
                        @foreach ([3, 7, 15, 30] as $dias)
                            <option value="{{ $dias }}" @selected(($filtros['dias'] ?? '') == $dias)>Más de {{ $dias }} días</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field nombre="fuera_de_plazo" etiqueta="Plazo">
                    <select name="fuera_de_plazo" id="fuera_de_plazo" class="select">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filtros['fuera_de_plazo'] ?? '') == '1')>Fuera de plazo</option>
                    </select>
                </x-ui.field>

                <div class="flex gap-2 lg:col-span-6">
                    <button type="submit" class="btn btn-primary">
                        <x-ui.icon name="search" class="size-4" />
                        Filtrar
                    </button>
                    <a href="{{ route('revision.index') }}" class="btn btn-ghost">Limpiar</a>
                </div>
            </form>
        </x-ui.card>

        @if ($envios->isEmpty())
            <x-ui.empty
                icono="clipboard-check"
                titulo="No hay envíos con ese filtro"
                texto="Pruebe con otro estado, otro establecimiento o quite los filtros."
            />
        @else
            <x-ui.table>
                <caption class="sr-only">Envíos pendientes de revisión</caption>
                <thead>
                    <tr>
                        <th scope="col">Establecimiento</th>
                        <th scope="col">Período</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="hidden text-center sm:table-cell">Series</th>
                        <th scope="col" class="text-center">Observaciones</th>
                        <th scope="col" class="hidden md:table-cell">Esperando desde</th>
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($envios as $envio)
                        <tr>
                            <td>
                                <span class="flex items-center gap-2.5">
                                    <x-ui.avatar :texto="$envio->establecimiento->nombre" tamano="sm" />
                                    <span class="min-w-0">
                                        <span class="block font-medium">{{ $envio->establecimiento->codigo }}</span>
                                        <span class="block truncate text-xs text-muted-foreground">{{ $envio->establecimiento->nombre }}</span>
                                    </span>
                                </span>
                            </td>

                            <td class="tabular">{{ sprintf('%02d/%d', $envio->mes, $envio->anio) }}</td>

                            <td>
                                <span class="flex flex-wrap items-center gap-1">
                                    <x-ui.estado :valor="$envio->estado" />
                                    @if ($envio->estaFueraDePlazo())
                                        <x-ui.badge tono="danger" :punto="false">Fuera de plazo</x-ui.badge>
                                    @endif
                                </span>
                            </td>

                            <td class="hidden text-center tabular sm:table-cell">{{ $envio->archivosVigentes->count() }}</td>

                            <td class="text-center">
                                @if ($envio->abiertas > 0)
                                    <x-ui.badge tono="warn">{{ $envio->abiertas }}</x-ui.badge>
                                @else
                                    <span class="text-muted-foreground">—</span>
                                @endif
                            </td>

                            <td class="hidden whitespace-nowrap text-xs text-muted-foreground md:table-cell">
                                {{ $envio->fecha_envio?->diffForHumans() ?? '—' }}
                            </td>

                            <td class="text-end">
                                <span class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                    <a href="{{ route('envios.show', $envio) }}" class="btn btn-secondary btn-sm">Ver</a>

                                    @can('observar', $envio)
                                        <button
                                            type="button"
                                            class="btn btn-warning btn-sm"
                                            data-modal-open="modalObservar"
                                            data-url="{{ route('revision.observar', $envio) }}"
                                            data-referencia="{{ $envio->establecimiento->codigo }} · {{ sprintf('%02d/%d', $envio->mes, $envio->anio) }}"
                                            data-series="{{ $envio->archivosVigentes->map(fn ($a) => ['id' => $a->serie_id, 'codigo' => $a->serie?->codigo])->toJson() }}"
                                        >
                                            Observar
                                        </button>
                                    @endcan

                                    @can('aprobar', $envio)
                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm"
                                            data-modal-open="modalAprobar"
                                            data-url="{{ route('revision.aprobar', $envio) }}"
                                            data-referencia="{{ $envio->establecimiento->codigo }} · {{ sprintf('%02d/%d', $envio->mes, $envio->anio) }}"
                                            data-abiertas="{{ $envio->abiertas }}"
                                        >
                                            Aprobar
                                        </button>
                                    @endcan
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <div>{{ $envios->links() }}</div>
        @endif
    </div>

    {{-- ===================== Observar ===================== --}}
    <x-ui.modal id="modalObservar" titulo="Observar envío">
        <form id="formObservar" method="POST" action="" class="space-y-4">
            @csrf

            <p class="text-sm text-muted-foreground">
                Envío <strong class="text-foreground" id="referenciaObservar"></strong>
            </p>

            <x-ui.field nombre="serie_id" etiqueta="¿Sobre qué serie?"
                        ayuda="Si elige una serie, la observación se resuelve sola cuando el encargado suba la corrección.">
                <select name="serie_id" id="serieObservar" class="select">
                    <option value="">Todo el envío</option>
                </select>
            </x-ui.field>

            <x-ui.field nombre="texto" etiqueta="¿Qué debe corregir el establecimiento?"
                        ayuda="La observación vuelve al establecimiento y queda en la bitácora." requerido>
                <textarea name="texto" id="texto" rows="4" maxlength="2000" required class="textarea"></textarea>
            </x-ui.field>
        </form>

        <x-slot:pie>
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="submit" form="formObservar" class="btn btn-warning">
                <x-ui.icon name="circle-exclamation-point" class="size-4" />
                Observar
            </button>
        </x-slot:pie>
    </x-ui.modal>

    {{-- ===================== Aprobar ===================== --}}
    <x-ui.modal id="modalAprobar" titulo="Aprobar envío">
        <form id="formAprobar" method="POST" action="" class="space-y-4">
            @csrf

            <p class="text-sm text-muted-foreground">
                Envío <strong class="text-foreground" id="referenciaAprobar"></strong>
            </p>

            <x-ui.alert tono="warn" id="avisoObservaciones" class="hidden">
                Este envío tiene observaciones sin resolver. No se puede aprobar hasta resolverlas.
            </x-ui.alert>

            <x-ui.field nombre="comentario" etiqueta="Comentario (opcional)">
                <input type="text" name="comentario" id="comentario" maxlength="500" class="input">
            </x-ui.field>
        </form>

        <x-slot:pie>
            <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
            <button type="submit" form="formAprobar" id="botonAprobar" class="btn btn-primary">
                <x-ui.icon name="circle-check" class="size-4" />
                Aprobar
            </button>
        </x-slot:pie>
    </x-ui.modal>
@endsection

@push('scripts')
    <script>
        // Preparan el diálogo antes de abrirlo: la acción del formulario y el
        // detalle dependen del envío de la fila, que el servidor ya renderizó.
        //
        // La URL llega desde `route()`, no se arma con un literal: el portal se
        // sirve en un subdirectorio y un '/revision/...' escrito a mano apunta a
        // la raíz del dominio, donde no hay nada.
        document.querySelectorAll('[data-modal-open="modalObservar"]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                document.getElementById('formObservar').action = this.dataset.url;
                document.getElementById('referenciaObservar').textContent = this.dataset.referencia;
                document.getElementById('texto').value = '';

                const select = document.getElementById('serieObservar');
                select.length = 1;

                JSON.parse(this.dataset.series || '[]').forEach(function (serie) {
                    const opcion = document.createElement('option');
                    opcion.value = serie.id;
                    opcion.textContent = serie.codigo;
                    select.appendChild(opcion);
                });
            });
        });

        document.querySelectorAll('[data-modal-open="modalAprobar"]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                const abiertas = parseInt(this.dataset.abiertas || '0', 10);

                document.getElementById('formAprobar').action = this.dataset.url;
                document.getElementById('referenciaAprobar').textContent = this.dataset.referencia;

                document.getElementById('avisoObservaciones').classList.toggle('hidden', abiertas === 0);
                document.getElementById('botonAprobar').disabled = abiertas > 0;
            });
        });
    </script>
@endpush
