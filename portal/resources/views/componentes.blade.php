{{--
    Galería del sistema visual.

    No es una pantalla del portal: es el banco de pruebas donde se aprueba el
    vocabulario antes de aplicarlo a las 18 vistas. Solo se registra en local
    (ver routes/web.php) y se retira en la Fase 6.

    Aquí no hay componentes Blade todavía. Las clases están escritas a mano a
    propósito: si algo se ve mal, se corrige acá y recién después se empaqueta en
    `resources/views/components/ui/`.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Galería del sistema visual · {{ config('app.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230f766e'/%3E%3Cpath d='M8 16h4l2-5 3 10 2-5h5' stroke='white' stroke-width='2.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
    @include('partials.tema')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground">

<a href="#contenido" class="skip-link">Saltar al contenido</a>

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">

    {{-- Cabecera --}}
    <header class="flex flex-col gap-4 border-b border-border pb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Galería del sistema visual</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Tokens, estados y componentes del rediseño. Solo en local.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <div class="flex items-center gap-1 rounded-lg border border-border bg-card p-1">
                <button type="button" data-theme-set="light"
                        class="rounded-md px-2.5 py-1.5 text-xs font-medium text-muted-foreground aria-pressed:bg-accent aria-pressed:text-accent-foreground">
                    Claro
                </button>
                <button type="button" data-theme-set="dark"
                        class="rounded-md px-2.5 py-1.5 text-xs font-medium text-muted-foreground aria-pressed:bg-accent aria-pressed:text-accent-foreground">
                    Oscuro
                </button>
                <button type="button" data-theme-set="system"
                        class="rounded-md px-2.5 py-1.5 text-xs font-medium text-muted-foreground aria-pressed:bg-accent aria-pressed:text-accent-foreground">
                    Sistema
                </button>
            </div>

            <button type="button" data-theme-toggle
                    class="flex size-9 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground">
                <i data-lucide="sun" class="size-4 dark:hidden"></i>
                <i data-lucide="moon" class="hidden size-4 dark:block"></i>
            </button>
        </div>
    </header>

    <main id="contenido" tabindex="-1" class="mt-8 space-y-12">

        {{-- 1. Tipografía --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Tipografía</h2>
            <div class="mt-4 space-y-3 rounded-xl border border-border bg-card p-6">
                <p class="text-2xl font-bold tracking-tight">Título de página · 24/700</p>
                <p class="text-base font-semibold">Encabezado de tarjeta · 16/600</p>
                <p class="text-sm">Texto de cuerpo · 14/400. Se usa en tablas, formularios y descripciones.</p>
                <p class="text-sm text-muted-foreground">Texto secundario · 14/400 muted-foreground.</p>
                <p class="text-xs text-muted-foreground">Etiqueta de campo · 12/400 muted-foreground.</p>
                <p class="tabular text-sm">Cifras tabulares: 1.284 / 1.909 / 0.045</p>
            </div>
        </section>

        {{-- 2. Superficies --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Superficies y bordes</h2>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">card</p>
                    <p class="mt-1 text-sm font-medium">Superficie base</p>
                </div>
                <div class="rounded-xl border border-border bg-secondary p-4">
                    <p class="text-xs text-muted-foreground">secondary</p>
                    <p class="mt-1 text-sm font-medium">Franja alterna</p>
                </div>
                <div class="rounded-xl border border-border bg-muted p-4">
                    <p class="text-xs text-muted-foreground">muted</p>
                    <p class="mt-1 text-sm font-medium">Fondo apagado</p>
                </div>
                <div class="rounded-xl border border-border bg-accent p-4">
                    <p class="text-xs text-muted-foreground">accent</p>
                    <p class="mt-1 text-sm font-medium">Selección</p>
                </div>
            </div>
        </section>

        {{-- 3. Estados del dominio --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Estados del dominio</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Cada estado lleva texto, no solo color (constitución, principio 30). El relleno usa
                <code class="rounded bg-muted px-1 py-0.5 text-xs">--status-*</code> y el texto
                <code class="rounded bg-muted px-1 py-0.5 text-xs">--status-*-fg</code>, más oscuro para pasar AA.
            </p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    /*
                     * Mapa explícito, no interpolación. Tailwind lee el código
                     * fuente como texto: `bg-status-{{ $tono }}` nunca llega al
                     * CSS compilado y el elemento queda sin fondo. Cada fila
                     * trae sus clases completas (constitución, principio 29).
                     */
                    $estados = [
                        [
                            'etiqueta' => 'Aprobado',
                            'origen' => 'envio.aprobado / archivo.valido',
                            'badge' => 'border-status-ok/25 bg-status-ok/10 text-status-ok-fg',
                            'punto' => 'bg-status-ok',
                            'caja' => 'border-status-ok/25 bg-status-ok/10',
                            'texto' => 'text-status-ok-fg',
                        ],
                        [
                            'etiqueta' => 'Observado',
                            'origen' => 'envio.observado / en_revision',
                            'badge' => 'border-status-warn/25 bg-status-warn/10 text-status-warn-fg',
                            'punto' => 'bg-status-warn',
                            'caja' => 'border-status-warn/25 bg-status-warn/10',
                            'texto' => 'text-status-warn-fg',
                        ],
                        [
                            'etiqueta' => 'Con errores',
                            'origen' => 'envio.con_errores / bloqueante',
                            'badge' => 'border-status-danger/25 bg-status-danger/10 text-status-danger-fg',
                            'punto' => 'bg-status-danger',
                            'caja' => 'border-status-danger/25 bg-status-danger/10',
                            'texto' => 'text-status-danger-fg',
                        ],
                        [
                            'etiqueta' => 'Sin archivo',
                            'origen' => 'envio.recibido / sin_envio',
                            'badge' => 'border-status-idle/25 bg-status-idle/10 text-status-idle-fg',
                            'punto' => 'bg-status-idle',
                            'caja' => 'border-status-idle/25 bg-status-idle/10',
                            'texto' => 'text-status-idle-fg',
                        ],
                    ];
                @endphp

                @foreach ($estados as $estado)
                    <div class="rounded-xl border border-border bg-card p-4">
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium {{ $estado['badge'] }}">
                            <span class="size-1.5 rounded-full {{ $estado['punto'] }}"></span>
                            {{ $estado['etiqueta'] }}
                        </span>
                        <p class="mt-3 font-mono text-[11px] leading-relaxed text-muted-foreground">{{ $estado['origen'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-4">
                @foreach ($estados as $estado)
                    <div class="rounded-xl border p-4 {{ $estado['caja'] }}">
                        <p class="text-xs font-medium {{ $estado['texto'] }}">Relleno translúcido</p>
                        <p class="mt-1 text-lg font-bold tabular {{ $estado['texto'] }}">{{ $loop->iteration * 12 }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- 4. Botones --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Botones</h2>
            <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card p-6">
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90">
                    <i data-lucide="plus" class="size-4"></i> Acción principal
                </button>
                <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-secondary">
                    <i data-lucide="download" class="size-4"></i> Secundaria
                </button>
                <button type="button" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground">
                    Terciaria
                </button>
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground transition-colors hover:bg-destructive/90">
                    <i data-lucide="ban" class="size-4"></i> Destructiva
                </button>
                <button type="button" disabled class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground opacity-50">
                    Deshabilitada
                </button>
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-primary p-2 text-primary-foreground" aria-label="Agregar">
                    <i data-lucide="plus" class="size-4"></i>
                </button>
            </div>
        </section>

        {{-- 5. Formularios --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Formularios</h2>
            <div class="mt-4 grid gap-4 rounded-xl border border-border bg-card p-6 sm:grid-cols-2">
                <div>
                    <label for="g-est" class="mb-1.5 block text-xs font-medium text-muted-foreground">Establecimiento</label>
                    <select id="g-est" class="w-full rounded-lg border border-input bg-card px-3 py-2 text-sm">
                        <option>Hospital Base Osorno</option>
                        <option>Posta Rural Puaucho</option>
                    </select>
                    <p class="mt-1.5 text-xs text-muted-foreground">Ayuda bajo el campo.</p>
                </div>

                <div>
                    <label for="g-per" class="mb-1.5 block text-xs font-medium text-muted-foreground">Período</label>
                    <input id="g-per" type="text" value="07/2026"
                           class="w-full rounded-lg border border-input bg-card px-3 py-2 text-sm">
                </div>

                <div>
                    <label for="g-mal" class="mb-1.5 block text-xs font-medium text-muted-foreground">Campo con error</label>
                    <input id="g-mal" type="text" value="2026-13" aria-invalid="true" aria-describedby="g-mal-error"
                           class="w-full rounded-lg border border-status-danger bg-card px-3 py-2 text-sm text-status-danger-fg">
                    <p id="g-mal-error" class="mt-1.5 flex items-center gap-1 text-xs text-status-danger-fg">
                        <i data-lucide="circle-exclamation-point" class="size-3.5"></i>
                        El período no existe.
                    </p>
                </div>

                <div>
                    <label for="g-fec" class="mb-1.5 block text-xs font-medium text-muted-foreground">Fecha límite</label>
                    <input id="g-fec" type="date" value="2026-11-16"
                           class="w-full rounded-lg border border-input bg-card px-3 py-2 text-sm">
                </div>

                <div>
                    <label for="g-arch" class="mb-1.5 block text-xs font-medium text-muted-foreground">Archivo Excel</label>
                    <input id="g-arch" type="file" accept=".xls,.xlsx,.xlsm"
                           class="w-full rounded-lg border border-input bg-card text-sm file:me-3 file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium">
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked class="size-4 rounded border-input accent-primary">
                        Activo
                    </label>
                </div>
            </div>
        </section>

        {{-- 6. Tarjetas de indicador --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Indicadores</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    /* Clases completas, nunca construidas: ver la nota del bloque de estados. */
                    $kpis = [
                        [
                            'icono' => 'file-up',
                            'caja_icono' => 'bg-chart-1/10',
                            'color_icono' => 'text-chart-1',
                            'rotulo' => 'Envíos del período',
                            'valor' => '38',
                            'detalle' => '+6 vs. anterior',
                            'detalle_color' => 'text-status-ok-fg',
                        ],
                        [
                            'icono' => 'clipboard-check',
                            'caja_icono' => 'bg-chart-2/10',
                            'color_icono' => 'text-chart-2',
                            'rotulo' => 'Por revisar',
                            'valor' => '7',
                            'detalle' => 'más antiguo: 4 días',
                            'detalle_color' => 'text-status-warn-fg',
                        ],
                        [
                            'icono' => 'circle-check',
                            'caja_icono' => 'bg-chart-3/10',
                            'color_icono' => 'text-chart-3',
                            'rotulo' => 'Aprobados',
                            'valor' => '29',
                            'detalle' => '76 % del total',
                            'detalle_color' => 'text-status-ok-fg',
                        ],
                        [
                            'icono' => 'triangle-exclamation-point',
                            'caja_icono' => 'bg-chart-4/10',
                            'color_icono' => 'text-chart-4',
                            'rotulo' => 'Fuera de plazo',
                            'valor' => '3',
                            'detalle' => 'requieren aviso',
                            'detalle_color' => 'text-status-danger-fg',
                        ],
                    ];
                @endphp

                @foreach ($kpis as $kpi)
                    <div class="rounded-xl border border-border bg-card p-4">
                        <div class="flex items-start justify-between">
                            <span class="flex size-9 items-center justify-center rounded-lg {{ $kpi['caja_icono'] }}">
                                <i data-lucide="{{ $kpi['icono'] }}" class="size-4 {{ $kpi['color_icono'] }}"></i>
                            </span>
                            <span class="text-2xl font-bold tabular">{{ $kpi['valor'] }}</span>
                        </div>
                        <p class="mt-3 text-sm font-medium">{{ $kpi['rotulo'] }}</p>
                        <p class="mt-0.5 text-xs {{ $kpi['detalle_color'] }}">{{ $kpi['detalle'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 rounded-xl border border-border bg-card p-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium">Avance del período</span>
                    <span class="tabular text-muted-foreground">29 / 38 · 76 %</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-status-ok" style="width: 76%"></div>
                </div>
            </div>
        </section>

        {{-- 7. Tabla --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Tabla</h2>
            <div class="mt-4 overflow-hidden rounded-xl border border-border bg-card">
                <table class="w-full text-sm">
                    <caption class="sr-only">Ejemplo de tabla de envíos</caption>
                    <thead>
                        <tr class="border-b border-border text-left text-xs text-muted-foreground">
                            <th scope="col" class="px-4 py-3 font-medium">Establecimiento</th>
                            <th scope="col" class="px-4 py-3 font-medium">Período</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium sm:table-cell">Estado</th>
                            <th scope="col" class="px-4 py-3 text-right font-medium">Series</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr class="hover:bg-secondary/50">
                            <td class="px-4 py-2.5">
                                <span class="flex items-center gap-2.5">
                                    <span class="flex size-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">HB</span>
                                    <span>
                                        <span class="block font-medium">Hospital Base</span>
                                        <span class="block text-xs text-muted-foreground">123100</span>
                                    </span>
                                </span>
                            </td>
                            <td class="px-4 py-2.5 tabular text-muted-foreground">07/2026</td>
                            <td class="hidden px-4 py-2.5 sm:table-cell">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-status-ok/25 bg-status-ok/10 px-2 py-0.5 text-xs font-medium text-status-ok-fg">
                                    <span class="size-1.5 rounded-full bg-status-ok"></span> Aprobado
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right tabular">14</td>
                        </tr>
                        <tr class="hover:bg-secondary/50">
                            <td class="px-4 py-2.5">
                                <span class="flex items-center gap-2.5">
                                    <span class="flex size-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">PR</span>
                                    <span>
                                        <span class="block font-medium">Posta Rural Puaucho</span>
                                        <span class="block text-xs text-muted-foreground">123045</span>
                                    </span>
                                </span>
                            </td>
                            <td class="px-4 py-2.5 tabular text-muted-foreground">07/2026</td>
                            <td class="hidden px-4 py-2.5 sm:table-cell">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-status-warn/25 bg-status-warn/10 px-2 py-0.5 text-xs font-medium text-status-warn-fg">
                                    <span class="size-1.5 rounded-full bg-status-warn"></span> Observado
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right tabular">6</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 8. Pestañas --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Pestañas</h2>
            <div class="mt-4 rounded-xl border border-border bg-card" data-tabs>
                <div role="tablist" aria-label="Detalle del envío" class="flex gap-4 border-b border-border px-4">
                    @foreach (['resumen' => 'Resumen', 'series' => 'Series', 'hallazgos' => 'Hallazgos', 'historial' => 'Historial'] as $id => $rotulo)
                        <button type="button" role="tab"
                                data-tab="{{ $id }}"
                                id="tab-{{ $id }}"
                                aria-controls="panel-{{ $id }}"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                tabindex="{{ $loop->first ? '0' : '-1' }}"
                                class="-mb-px border-b-2 border-transparent py-3 text-sm font-medium text-muted-foreground aria-selected:border-primary aria-selected:text-foreground">
                            {{ $rotulo }}
                        </button>
                    @endforeach
                </div>

                @foreach (['resumen' => 'Total del período, estado y plazo.', 'series' => 'Una fila por serie exigida.', 'hallazgos' => 'Bloqueantes y advertencias de la validación.', 'historial' => 'Quién movió el envío y cuándo.'] as $id => $texto)
                    <div role="tabpanel" data-panel="{{ $id }}" id="panel-{{ $id }}" aria-labelledby="tab-{{ $id }}" tabindex="0"
                         class="p-4 text-sm text-muted-foreground" @if (! $loop->first) hidden @endif>
                        {{ $texto }}
                    </div>
                @endforeach
            </div>
        </section>

        {{-- 9. Historial --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Historial</h2>
            <div class="mt-4 rounded-xl border border-border bg-card p-6">
                <ol class="relative space-y-6">
                    @php
                        $hitos = [
                            ['punto' => 'bg-status-ok', 'titulo' => 'Aprobado', 'detalle' => 'Revisora · 09-11-2026 11:42'],
                            ['punto' => 'bg-status-warn', 'titulo' => 'Observado', 'detalle' => 'Revisora · 08-11-2026 16:10 · falta serie B'],
                            ['punto' => 'bg-status-idle', 'titulo' => 'Enviado a revisión', 'detalle' => 'Encargado · 08-11-2026 09:03'],
                        ];
                    @endphp
                    @foreach ($hitos as $hito)
                        <li class="relative ps-8">
                            <span class="absolute start-0 top-1 flex size-3 items-center justify-center rounded-full ring-4 ring-card {{ $hito['punto'] }}"></span>
                            @if (! $loop->last)
                                <span class="absolute start-[5px] top-5 h-[calc(100%+0.6rem)] w-px bg-border" aria-hidden="true"></span>
                            @endif
                            <p class="text-sm font-medium">{{ $hito['titulo'] }}</p>
                            <p class="text-xs text-muted-foreground">{{ $hito['detalle'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- 10. Vacío --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Estado vacío</h2>
            <div class="mt-4 flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card px-6 py-12 text-center">
                <span class="flex size-11 items-center justify-center rounded-full bg-muted">
                    <i data-lucide="inbox" class="size-5 text-muted-foreground"></i>
                </span>
                <p class="mt-4 text-sm font-medium">Todavía no hay envíos</p>
                <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                    Cree uno con el formulario de arriba. El período se elige de los que están abiertos.
                </p>
                <button type="button" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">
                    <i data-lucide="plus" class="size-4"></i> Crear envío
                </button>
            </div>
        </section>

        {{-- 11. Avisos --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Avisos</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" data-toast="Envío aprobado." data-toast-type="success"
                        class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium hover:bg-secondary">
                    Probar aviso de éxito
                </button>
                <button type="button" data-toast="El período está cerrado y no acepta cargas." data-toast-type="error"
                        class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium hover:bg-secondary">
                    Probar aviso de error
                </button>
            </div>
        </section>

        {{-- 12. Iconos --}}
        <section>
            <h2 class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Iconos (53)</h2>
            <div class="mt-4 grid grid-cols-6 gap-2 rounded-xl border border-border bg-card p-6 sm:grid-cols-10">
                @foreach (['activity','arrow-right','arrow-up-down','ban','bell','building','calendar','calendar-check','calendar-days','check','chevron-down','chevron-right','chevrons-left','circle','circle-check','circle-exclamation-point','circle-x','clipboard-check','clock','download','ellipsis','external-link','eye','file-spreadsheet','file-text','file-up','grid-3x3','hospital','hourglass','house','inbox','layout-dashboard','list','list-ordered','lock','log-out','mail','menu','minus','monitor','moon','panel-left-close','pencil','percent','plus','search','sun','table','triangle-exclamation-point','upload','user','users','x'] as $icono)
                    <span class="flex aspect-square items-center justify-center rounded-lg border border-border text-muted-foreground" title="{{ $icono }}">
                        <i data-lucide="{{ $icono }}" class="size-4"></i>
                    </span>
                @endforeach
            </div>
        </section>

    </main>

    <footer class="mt-12 border-t border-border pt-6 text-xs text-muted-foreground">
        Galería solo para desarrollo · se retira en la Fase 6 del rediseño.
    </footer>
</div>

</body>
</html>
