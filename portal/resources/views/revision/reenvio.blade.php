@extends('layouts.panel')

@section('titulo', 'Pedir nueva subida')

@section('contenido')
    <div class="mx-auto max-w-4xl space-y-6">

        <x-ui.page-header
            titulo="Pedir una nueva subida"
            subtitulo="Avisa al establecimiento de que debe volver a subir la estadística."
            :migas="[
                'Panel' => route('panel'),
                'Revisión' => route('revision.index'),
                'Nueva subida' => null,
            ]"
        />

        <x-ui.alert tono="info">
            La solicitud deja una observación por serie, que se resuelve sola cuando el encargado sube
            la corrección. Si el envío ya está aprobado, se reabre y queda en el historial con el motivo.
        </x-ui.alert>

        {{-- Filtro de período: el listado de envíos depende de él. --}}
        <x-ui.card class="p-5">
            <form method="GET" action="{{ route('revision.reenvio.form') }}" class="grid gap-4 sm:grid-cols-3 lg:items-end">
                <x-ui.field nombre="anio" etiqueta="Año">
                    <input type="number" name="anio" id="anio" value="{{ $anio }}" min="2000" max="2100" class="input tabular">
                </x-ui.field>

                <x-ui.field nombre="mes" etiqueta="Mes">
                    <select name="mes" id="mes" class="select">
                        @foreach (range(1, 12) as $numero)
                            <option value="{{ $numero }}" @selected($mes === $numero)>
                                {{ ucfirst(\Carbon\Carbon::create(null, $numero, 1)->translatedFormat('F')) }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                <button type="submit" class="btn btn-primary h-10">
                    <x-ui.icon name="search" class="size-4" />
                    Ver envíos del período
                </button>
            </form>
        </x-ui.card>

        @if ($envios->isEmpty())
            <x-ui.empty
                icono="inbox"
                titulo="No hay envíos en ese período"
                texto="Pruebe con otro período. Solo aparecen los envíos de los establecimientos que tiene asignados."
            />
        @else
            <form method="POST" action="{{ route('revision.reenvio') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="anio" value="{{ $anio }}">
                <input type="hidden" name="mes" value="{{ $mes }}">

                <x-ui.card class="p-5 space-y-4">
                    <h2 class="text-sm font-semibold">¿Qué debe volver a subir?</h2>

                    @error('alcance')
                        <p class="campo-error">{{ $message }}</p>
                    @enderror
                    @error('reenvio')
                        <p class="campo-error">{{ $message }}</p>
                    @enderror

                    {{-- Una serie --}}
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-border p-3 text-sm">
                        <input type="radio" name="alcance" value="serie" class="mt-0.5"
                               data-alcance @checked(old('alcance', 'serie') === 'serie')>
                        <span class="min-w-0">
                            <span class="block font-medium">Una serie de un envío</span>
                            <span class="block text-xs text-muted-foreground">
                                El archivo concreto que salió mal. Es el caso más fino.
                            </span>
                        </span>
                    </label>

                    <div class="grid gap-3 ps-6 sm:grid-cols-2" data-panel-alcance="serie">
                        <x-ui.field nombre="envio_id" etiqueta="Envío">
                            <select name="envio_id" id="envio_id" class="select">
                                @foreach ($envios as $envio)
                                    <option value="{{ $envio->id }}" @selected(old('envio_id') == $envio->id)>
                                        {{ $envio->establecimiento->codigo }} ·
                                        {{ sprintf('%02d/%d', $envio->mes, $envio->anio) }} ·
                                        {{ \App\Support\EstadoUi::etiqueta($envio->estado) }}
                                    </option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <x-ui.field nombre="serie_id" etiqueta="Serie">
                            <select name="serie_id" id="serie_id" class="select">
                                @foreach ($series as $serie)
                                    <option value="{{ $serie->id }}">{{ $serie->codigo }} — {{ $serie->nombre }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>

                    {{-- Todas las series de un envío --}}
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-border p-3 text-sm">
                        <input type="radio" name="alcance" value="envio" class="mt-0.5"
                               data-alcance @checked(old('alcance') === 'envio')>
                        <span class="min-w-0">
                            <span class="block font-medium">Todas las series de un envío</span>
                            <span class="block text-xs text-muted-foreground">
                                El período completo está mal y hay que rehacerlo.
                            </span>
                        </span>
                    </label>

                    <div class="ps-6" data-panel-alcance="envio">
                        <x-ui.field nombre="envio_id_envio" etiqueta="Envío a corregir">
                            <select name="envio_id_envio" id="envio_id_envio" class="select" data-espejo="envio_id">
                                @foreach ($envios as $envio)
                                    <option value="{{ $envio->id }}">
                                        {{ $envio->establecimiento->codigo }} ·
                                        {{ $envio->establecimiento->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>

                    {{-- Todos los asignados --}}
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-border p-3 text-sm">
                        <input type="radio" name="alcance" value="asignados" class="mt-0.5"
                               data-alcance @checked(old('alcance') === 'asignados')>
                        <span class="min-w-0">
                            <span class="block font-medium">Todos mis establecimientos en este período</span>
                            <span class="block text-xs text-muted-foreground">
                                {{ $envios->count() }} envío(s) en {{ sprintf('%02d/%d', $mes, $anio) }}.
                                Solo se pide a los que ya están en revisión, observados o aprobados.
                            </span>
                        </span>
                    </label>
                </x-ui.card>

                <x-ui.card class="p-5">
                    <x-ui.field nombre="motivo" etiqueta="¿Qué está mal?" :error="$errors->first('motivo')" requerido
                                ayuda="Mínimo 10 caracteres. El encargado lo lee tal cual.">
                        <textarea name="motivo" id="motivo" rows="4" maxlength="500" required class="textarea">{{ old('motivo') }}</textarea>
                    </x-ui.field>
                </x-ui.card>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <x-ui.icon name="rotate-ccw" class="size-4" />
                        Pedir nueva subida
                    </button>
                    <a href="{{ route('revision.index') }}" class="btn btn-ghost">Cancelar</a>
                </div>
            </form>
        @endif

    </div>
@endsection

@push('scripts')
    <script>
        // El selector de envío se repite en dos alcances; se espejan para que el
        // servidor reciba siempre `envio_id`.
        (function () {
            document.querySelectorAll('[data-espejo]').forEach(function (select) {
                select.addEventListener('change', function () {
                    const destino = document.getElementById(this.dataset.espejo);
                    if (destino) {
                        destino.value = this.value;
                    }
                });
            });

            // El panel visible sigue al alcance elegido.
            const paneles = document.querySelectorAll('[data-panel-alcance]');
            const radios = document.querySelectorAll('[data-alcance]');

            function refrescar() {
                const elegido = Array.from(radios).find((r) => r.checked)?.value;

                paneles.forEach(function (panel) {
                    panel.classList.toggle('hidden', panel.dataset.panelAlcance !== elegido);
                });
            }

            radios.forEach((r) => r.addEventListener('change', refrescar));
            refrescar();
        })();
    </script>
@endpush
