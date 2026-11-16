# FTD — Rediseño de UI del portal M7 con el sistema visual Clinova

> **Estado:** Propuesto — pendiente de aprobación
> **Rama objetivo:** `feature/rediseno-ui-clinova` (por crear)
> **Fecha:** 2026-11-16
> **Autor:** Equipo M7
> **Reemplaza a:** `02-DOCS/feature-adminlte4-bootstrap5-migration.md` (ejecutado: AdminLTE 4.10.2 ya está en `public/assets/adminlte/`)

---

## 1. Intención

Reemplazar la capa visual del portal M7 —hoy **AdminLTE 4.10.2 / Bootstrap 5 + FontAwesome**, servida como assets estáticos— por el sistema visual de la plantilla de referencia `demo.dashboardpack.com/hospital-html/` (**"Clinova"**): **Tailwind CSS v4 + tokens oklch semánticos + Lucide + componentes propios**, compilado con Vite.

**Por qué ahora.**

1. El portal ya tiene Tailwind v4 instalado y compilando, y **no lo usa**. `portal/resources/css/app.css` importa `tailwindcss` y `portal/public/build/assets/app-CEftQMAG.css` (43 KB) se genera en cada build, pero ningún layout lo referencia: los layouts cargan `asset('assets/adminlte/...')`. Hay una capa compilada desperdiciada en cada build.
2. La UI actual no tiene jerarquía: `info-box` (40 usos), `card` (66) y `table` (90) ocupan el 90 % de la interfaz sin un sistema de estados. Los estados del dominio (6 de envío, 6 de completitud) se pintan con clases hardcodeadas y hex literales.
3. La plantilla resuelve exactamente los tres problemas que tenemos: **estados con tokens semánticos**, **patrón de matriz de celdas** (que es nuestra pantalla de Completitud) y **detalle con pestañas + timeline** (que es `envios/show`, hoy 302 líneas en una sola columna).

**Lo que NO cambia.** Controladores, servicios, modelos, migraciones, reglas de validación, permisos, rutas y contrato de descargas. Cero.

---

## 2. Qué trae la plantilla (verificado, no supuesto)

31 páginas HTML estáticas descargadas y verificadas con `200`. Stack real, según su propio `docs.html` y confirmado en los bundles:

| Pieza | Detalle | Evidencia |
|---|---|---|
| Build | Vite 6, multi-página | `./assets/main-DwU2CWaz.js` + `main-DMKZ8uJS.css` |
| CSS | **Tailwind CSS v4.3.3** con tokens `oklch` en `:root` y `.dark` | `/*! tailwindcss v4.3.3 \| MIT License */` |
| JS | **Vanilla ES modules**, 65 KB, sin framework | entry de 65.477 B |
| Iconos | **Lucide** como SVG inyectado (88 iconos en el mapa `C={...}`) | `createIcons({icons:C})`, `data-lucide="activity"` |
| Gráficos | **ApexCharts** en chunk diferido de **567.014 B** | `import('./apexcharts.esm-D4HfopFb.js')` |
| Toasts | **Notyf** en chunk diferido de 7.652 B | `import('./notyf.es-C0j8MW-P.js')` |
| Tipografía | **Inter Variable** self-hosted, 7 subsets `woff2` | `@font-face{font-family:Inter Variable;...format("woff2-variations")` |
| Tablas | **motor propio** (`table[data-datatable]`): búsqueda, facetas, selector de columnas, paginación y export CSV escritos a mano | `document.querySelectorAll('table[data-datatable]').forEach(rn)` |

> Nota de precisión: `docs.html` afirma que las tablas son `simple-datatables`. **Es falso en este build**: 0 coincidencias de esa librería en el bundle. El motor es propio. Es mejor noticia: no arrastramos una dependencia más.

### 2.1 Tokens que vamos a adoptar literalmente

Neutro cálido en hue **90**, primario teal en hue **195**, y cuatro estados clínicos:

```css
:root{
  --primary:oklch(55% .09 195);        --primary-foreground:oklch(99% .005 90);
  --background:oklch(98.5% .004 90);   --foreground:oklch(21% .01 90);
  --card:oklch(100% 0 0);              --muted:oklch(96% .006 90);
  --muted-foreground:oklch(52% .012 90); --border:oklch(90% .006 90);
  --ring:oklch(55% .09 195);           --radius:.5rem;
  --status-stable:oklch(60% .12 155);      /* verde  */
  --status-observation:oklch(75% .13 80);  /* ámbar  */
  --status-critical:oklch(55% .2 25);      /* rojo   */
  --status-discharged:oklch(62% .008 90);  /* gris   */
  --chart-1:oklch(55% .09 195); --chart-2:oklch(55% .1 235);
  --chart-3:oklch(60% .11 155); --chart-4:oklch(75% .12 80); --chart-5:oklch(50% .03 260);
}
```

Los cuatro `--status-*` mapean **uno a uno** con nuestros estados del dominio. Ese es el argumento central del rediseño: hoy esos estados se escriben como `bg-success` / `bg-danger` / hex literales en `completitud/index.blade.php:8-13`.

### 2.2 Medidas del chrome

| Medida | Valor |
|---|---|
| Sidebar | `--sidebar-w: 260px`; colapsado a rail `4.5rem` (72 px) |
| Header | `h-16` = 64 px, `sticky top-0`, `backdrop-blur-xl` |
| Card | `rounded-xl` = `calc(--radius + 4px)` = 12 px |
| Escala base | `--spacing: .25rem` |
| Breakpoints | Tailwind por defecto (640 / 768 / 1024 / 1280 / 1536) |

---

## 3. Lista de lo que ocupamos, módulo por módulo

Esta es la lista pedida: qué pieza de la plantilla entra en qué pantalla nuestra.

### 3.1 Chrome y navegación

| Pieza Clinova | Dónde entra | Nuestro archivo |
|---|---|---|
| Sidebar con grupos colapsables (`data-group-toggle`) | menú Operación / Administración / Usuarios | `portal/resources/views/layouts/panel.blade.php:56-153` |
| Colapso a rail + overlay móvil + `aria-expanded` | toda la app | idem |
| Header sticky con campana, avatar y menú de cuenta | header actual con FontAwesome | `layouts/panel.blade.php:15-44` |
| Breadcrumb con `chevron-right` | reemplaza el `<h1>` suelto en 11 vistas | todas las vistas con `content-header` |
| Campana con dropdown, badge de no leídos y "marcar todas" | `partials/campana.blade.php` (ya tiene la lógica) | `portal/resources/views/partials/campana.blade.php` |
| Página de notificaciones con `divide-y` y contador dinámico | opcional: página dedicada de avisos | — |
| Login card centrada sin chrome | `auth/login.blade.php` | `portal/resources/views/auth/login.blade.php`, `layouts/auth.blade.php` |
| Páginas 404 / 500 centradas | hoy Laravel muestra su página por defecto | `portal/resources/views/errors/` (nuevo) |

### 3.2 Componentes de datos

| Pieza Clinova | Dónde entra | Nuestro archivo |
|---|---|---|
| **Card KPI con delta** (icono en `size-9` + cifra `text-2xl` + variación) | Panel; 4 KPIs de `envios/show`; 4 KPIs de `completitud` | `panel.blade.php`, `envios/show.blade.php:62-99`, `completitud/index.blade.php:26-63` |
| **Stat compacta** (icono + cifra + etiqueta) | donde no hay serie temporal (nuestro caso: no tenemos histórico) | idem |
| **Badge de estado con punto** (`bg-status-X/12` + `ring-status-X/25` + dot) | 6 estados de envío, 6 estados de completitud, "fuera de plazo", severidad de hallazgos | `envios/index:92-97`, `envios/show:29-32,169-174`, `revision/index:113-118` |
| **Tabla con toolbar**: búsqueda, facetas, selector de columnas, orden por clic, paginación, export CSV | catálogos admin (pocas filas), bandeja de revisión | `admin/index.blade.php`, `revision/index.blade.php` |
| **Tabla simple** con columnas que se ocultan por breakpoint (`hidden sm:table-cell`) | tablas de detalle | `envios/show.blade.php:144-190`, `admin/matriz/index.blade.php` |
| **Mapa de celdas en grid** (`grid-cols-6 … sm:grid-cols-10`, celda `aspect-square` con color de estado) | **matriz de Completitud** — es el mismo patrón, hoy una `<table>` de 6 colores con `style="background:#d4edda"` | `completitud/index.blade.php:99-158` |
| **Barra de progreso** (`h-2 rounded-full bg-muted` + relleno por estado) | avance por establecimiento; avance global del período | `completitud/index.blade.php` (columna Avance), `panel.blade.php` |
| **Timeline vertical** (`ol` + línea `w-px` + nodo `ring-4`) | **historial de transiciones** — hoy un `list-group` plano | `envios/show.blade.php:276-301` |
| **Tabs** (`data-tab` / `data-panel`, con teclado) | `envios/show`: Resumen · Series · Hallazgos · Historial. Hoy son 4 tarjetas apiladas en una página de 302 líneas | `envios/show.blade.php` |
| **Empty state** centrado con CTA | "Todavía no hay envíos", "No hay registros", "Sin movimientos" | 6 vistas |
| **Avatar con iniciales** | usuario en header; establecimiento en tablas (hoy código en negrita) | header, `envios/index`, `revision/index` |
| **Acordeón nativo** `<details>/<summary>` | hallazgos de validación agrupados por serie | `envios/show.blade.php:193-240` |

### 3.3 Formularios

| Pieza Clinova | Dónde entra | Nuestro archivo |
|---|---|---|
| Select, input, checkbox, date, file — con estado inválido | 5 catálogos admin + matriz + usuarios + perfil | `admin/form.blade.php`, `admin/usuarios/form.blade.php`, `admin/matriz/form.blade.php`, `perfil.blade.php` |
| Toggle de preferencias (`type="checkbox"` estilizado) | "activo" en usuarios | `admin/usuarios/form.blade.php` |
| **Wizard de 4 pasos** con `checkValidity()` por paso | "Nuevo envío" hoy es un `<form>` de 3 campos en una fila; el wizard es *opcional*, no necesario | `envios/index.blade.php:27-68` |
| **Tabs de Settings** (Profile / Preferences / Appearance) | `perfil.blade.php` (datos + contraseña) | `perfil.blade.php` |
| Input de archivo con `accept` y ayuda bajo el campo | subida de serie; subida de plantilla | `envios/show.blade.php:107-134`, `admin/plantillas/form.blade.php` |

### 3.4 Retroalimentación

| Pieza Clinova | Dónde entra | Nuestro archivo |
|---|---|---|
| **Toasts (Notyf)** | reemplaza los `alert alert-success` / `alert-danger` de sesión | `session('ok')` en 8 vistas; `$errors` en 6 |
| Banner de alerta con borde de estado | avisos persistentes: "fuera de plazo", "hay feriados sin cargar" (`admin/calendario/index.blade.php` ya tiene 2 `alert-warning`) | `admin/calendario/index.blade.php` |

### 3.5 Gráficos (solo donde aportan)

| Gráfico | Uso propuesto | Justificación |
|---|---|---|
| `area` / `bar` | envíos por período (últimos 12 meses) en el Panel | es el único dato histórico que ya tenemos (`updated_at`, `periodos`) |
| `radial` (gauge) | % de avance del período en curso | reemplaza el `info-box` "Avance 72 %" |
| `bar` horizontal | avance por establecimiento en Completitud | reemplaza la columna "Aprobadas / Exigidas" |
| `donut` | distribución de estados de las series | una sola mirada del semáforo global |

No proponemos gráficos en vista de detalle ni en admin.

### 3.6 Lo que la plantilla trae y **descartamos a propósito**

| Pieza | Por qué no |
|---|---|
| Chat (`messages.html`) | no existe módulo de mensajería en el MVP v2 |
| Presets de color (teal/blue/sage/slate) | solo reescriben 4 variables; un servicio público no necesita paleta conmutable. Además deja el acento teal descolgado (no toca `--accent`) |
| Selector de densidad | **está muerto en la plantilla**: `--card-padding` se define 3 veces y no lo consume ninguna regla. Adoptarlo sería copiar un defecto |
| Command palette ⌘K | nuestro menú tiene 13 entradas; el costo de mantener 24 rutas con keywords no se paga |
| Calendario de `appointments.html` | fechas fijas en el código (`{year:2026,month:6}`); no sirve para plazos reales |
| Panel de paciente, triage, farmacia, facturación, RRHH, agendamiento | dominios que el MVP v2 no cubre |
| `register` / `forgot-password` | Fortify gestiona nuestro ciclo de autenticación |
| Módulo OTP (`data-otp`) | implementado y sin usar en la plantilla; sin 2FA en el MVP |
| Footer ausente en la plantilla | nosotros **sí** tenemos footer (`layouts/panel.blade.php:167-170`) y lo conservamos |

### 3.7 Lo que la plantilla **no trae** y debemos construir nosotros

Esta es la deuda real del rediseño. No es poca:

| Falta | Dónde lo necesitamos | Solución |
|---|---|---|
| **Diálogo / modal** | Observar y Aprobar (`revision/index.blade.php:172-237`) — hoy Bootstrap Modal. La plantilla **no tiene ningún modal**: el único diálogo es el ⌘K | `<dialog>` nativo + `<x-ui.modal>`, con focus trap manual |
| **Paginación server-side** | 6 vistas con `->links()` | componente Blade sobre el paginador de Laravel, con estilo Tailwind |
| **Filtros server-side** | `revision/index`, `completitud/index`, `admin/calendario/index` | mantener `<form method="GET">`, restilizar |
| **Errores de campo** | `@error` en 4 formularios, hoy `is-invalid` / `invalid-feedback` | directiva propia `<x-ui.field>` con `aria-describedby` |
| **Estado de subida de archivo** | `envios/show` | texto de ayuda + validación, sin barra de progreso (subida por `POST` normal) |
| **Tema oscuro sin JS** | hoy no hay `@media (prefers-color-scheme)` en el CSS de la plantilla; solo `matchMedia` en JS | añadir el bloque `@media` en nuestro CSS; el `.dark` queda como override |
| **`aria-current` en el nav** | la plantilla no lo usa (0 coincidencias); nosotros también lo perdimos | en el componente de link activo |
| **`color-scheme`** | controles nativos (select, date, checkbox) quedan claros en modo oscuro | una línea en el CSS |
| **`scope="col"` en `<th>`** | ninguna tabla de la plantilla lo tiene | en `<x-ui.table>` |

---

## 4. Cambios de stack y su impacto en el canon

Este rediseño **no es cosmético**: cambia la capa UI del canon de stack.

| Canon vigente (C-015) | Canon propuesto |
|---|---|
| UI: **AdminLTE 3.2 (Bootstrap 4)** | UI: **Tailwind CSS v4 + componentes propios** |
| JS: Vanilla, sin Node en producción | **Igual** — Vite compila en local y `public/build/` es estático |
| Iconos: FontAwesome (fuente) | **Lucide** (SVG, 26 iconos mapeados, 34 usos) |

Consecuencias que hay que resolver **antes** de escribir código:

1. **Enmienda a la constitución (v4.2.0 → v5.0.0).** Principio 0 (tabla de stack) y §6 (UX). Sin enmienda, este cambio viola el principio 1 y no pasa `verify`.
2. **Registro de decisión C-019** en `02-DOCS/wiki/sdd/decisions.md` con opciones consideradas y motivo (principio 26).
3. **El canon escrito ya está desactualizado.** Dice AdminLTE 3.2, pero `public/assets/adminlte/css/adminlte.min.css` es **AdminLTE v4.10.2** (= Bootstrap 5). La migración de la FTD anterior se ejecutó y el documento sigue en "Propuesto". Hay que cerrarla formalmente para no tener dos planes vivos.
4. **Regla de build.** Nadie compila Tailwind en el servidor (no hay Node). Se construye en local y se sube `portal/public/build/` por FTP junto con el árbol. Esto ya es la práctica actual y cumple el principio de despliegue reproducible.
5. **Tailwind no genera clases dinámicas.** Hoy tenemos `bg-{{ $color }}` en `envios/show.blade.php:29` y `bg-{{ ... }}` en `envios/index.blade.php:92` y `revision/index.blade.php:113`, más hex literales en `completitud/index.blade.php:8-13`. El escáner de Tailwind no ve esas cadenas y **la clase no existirá en el CSS compilado**. Todo estado debe pasar a un **mapa explícito** (un `match()` que devuelva una clase completa) o a un componente con variantes declaradas. Esto es el error más probable del rediseño y hay que fijarlo en los cimientos, no dejarlo para el final.
6. **Costo de ApexCharts: 567 KB diferidos.** Se carga en cualquier página con `[data-chart]`. Con 4 gráficos en 2 pantallas es aceptable; si crece, pasa a ser un problema contra el presupuesto de p95 < 2 s de la constitución (principio 25).

---

## 5. Plan por fases

### 5.0 Estado del repositorio al arrancar — bloqueo de 0.4

Verificado el 2026-11-16 antes de tocar nada:

| Hecho | Consecuencia |
|---|---|
| `feature/adminlte4-bootstrap5` **no tiene commits propios**: apunta a `713b544`, el mismo que `main` | La rama existe como nombre, no como trabajo |
| La migración AdminLTE 4 está **solo en el árbol de trabajo**: 23 modificados, 251 eliminados, 64 sin rastrear | Ese trabajo no está en ninguna rama y no se puede recuperar desde git |
| `.gitignore` **no cubre** `adminlte4.zip`, `adminlte.v3.backup/`, `adminlte4_extracted/`, `shots/`, `.playwright-cli/` | Basura que se colaría en el commit |

**Por qué bloquea.** Si el rediseño se commitea sobre este árbol, la historia mezcla 251 borrados de assets con los cambios de UI: el PR es irrevisable, y el rollback del §10 (`git checkout -- views`) **borraría la migración AdminLTE 4**, que es trabajo real del equipo.

**Cómo se resuelve.** Commitear primero el estado actual en `feature/adminlte4-bootstrap5` —solo los archivos que el portal carga, no los respaldos ni los zip— y crear `feature/rediseno-ui-clinova` desde ahí. Es la única forma de que el rediseño tenga un `git diff` legible y un rollback verdadero.

Mientras no se resuelva, la Fase 0 avanza **solo en documentos**: no se crea la rama ni se descartan cambios.

### Fase 0 — Canon y rama (0 código de UI)
- [x] **0.1** Enmendar la constitución a v5.0.0: tabla de stack (§0) y §6 UX — **hecho 2026-11-16**
- [x] **0.2** Registrar decisión C-019 en `02-DOCS/wiki/sdd/decisions.md` — **hecho 2026-11-16** (C-019 + D-031…D-037)
- [x] **0.3** Cerrar `feature-adminlte4-bootstrap5-migration.md` como ejecutado — **hecho 2026-11-16**
- [ ] **0.4** **BLOQUEADO:** commitear la migración AdminLTE 4 en su rama y crear `feature/rediseno-ui-clinova` desde ahí (ver §5.0)

### Fase 1 — Cimientos: design system y build
- [ ] **1.1** Instalar dependencias: `lucide`, `notyf`, `apexcharts`, `@fontsource-variable/inter`
- [ ] **1.2** `portal/resources/css/app.css`: tokens oklch light + `.dark` + `@media (prefers-color-scheme)` + `color-scheme` + `--status-*` + mapeo a `@theme` + `focus-visible` + `prefers-reduced-motion` + `.skip-link`
- [ ] **1.3** `portal/resources/js/app.js` en módulos: `theme`, `sidebar`, `tabs`, `toast`, `icons`, `datatable`, `charts`
- [ ] **1.4** `@vite(['resources/css/app.css','resources/js/app.js'])` en ambos layouts, retirando `asset('assets/adminlte/...')`
- [ ] **1.5** Script inline anti-flash en `<head>` (tema antes del paint)
- [ ] **1.6** Verificar: `npm run build` produce CSS < 60 KB y sin 404 de fuentes

### Fase 2 — Componentes Blade (primitivas)
- [ ] **2.1** `card`, `stat`, `badge` (variantes de estado), `button`, `avatar`
- [ ] **2.2** `table` (con `scope="col"`, `divide-y`, columnas responsivas), `pagination`
- [ ] **2.3** `field` (label + input/select/checkbox/date/file + error + `aria-describedby`)
- [ ] **2.4** `tabs`, `breadcrumb`, `page-header`, `empty`, `timeline`, `progress`
- [ ] **2.5** `modal` sobre `<dialog>` nativo con focus trap y `Escape`
- [ ] **2.6** `alert` / toast de sesión
- [ ] **2.7** Galería local `/componentes` (solo `APP_ENV=local`) para revisar todas las variantes sin tocar producción

### Fase 3 — Shell
- [ ] **3.1** `layouts/panel.blade.php`: sidebar (grupos + rail + overlay + `aria-current`), header, skip-link, footer, campana
- [ ] **3.2** `layouts/auth.blade.php` + `auth/login.blade.php`
- [ ] **3.3** `partials/campana.blade.php` como dropdown accesible

### Fase 4 — Vistas, de mayor a menor impacto
| # | Vista | Alcance | Tamaño |
|---|---|---|---|
| 4.1 | `completitud/index` | matriz de celdas + 4 KPIs + leyenda + progreso | **L** — es la pantalla que más gana |
| 4.2 | `envios/show` | 4 KPIs + tabs (Resumen/Series/Hallazgos/Historial) + timeline + acordeón | **L** — 302 líneas hoy |
| 4.3 | `panel` | KPIs + 1–2 gráficos + accesos por rol | **M** |
| 4.4 | `envios/index` | form de alta + tabla + badges | **M** |
| 4.5 | `revision/index` | filtros + tabla + 2 modales | **M** |
| 4.6 | `admin/index` + `admin/form` | genéricos: cubren **5 catálogos** (servicios, establecimientos, series, períodos, feriados) | **M** — una vez, cinco pantallas |
| 4.7 | `admin/matriz/index` + `form` | tabla + grid de checkboxes + fechas | **M** |
| 4.8 | `admin/calendario/index` | filtro año + 3 tablas + avisos | **S/M** |
| 4.9 | `admin/plantillas/index` + `form` | tabla + form con archivo | **S** |
| 4.10 | `admin/usuarios/form` | form con select de rol y toggle activo | **S** |
| 4.11 | `perfil` | tabs datos/contraseña | **S** |
| 4.12 | `errors/404`, `errors/500` | nuevo | **S** |

Total: **18 archivos Blade** (2 layouts + 1 partial + 15 vistas).

### Fase 5 — Accesibilidad y verificación
- [ ] **5.1** WCAG 2.2 AA: `aria-current`, `scope="col"`, labels asociados, foco visible, contraste ≥ 4.5:1 en `--muted-foreground` sobre `--card`
- [ ] **5.2** Teclado: tabs, modal (focus trap + `Escape` + foco devuelto), sidebar móvil
- [ ] **5.3** Tres anchos: 1440 / 768 / 390 px
- [ ] **5.4** `./vendor/bin/phpunit` — suite completa en verde
- [ ] **5.5** Checklist de textos que los tests afirman (ver §6)

### Fase 6 — Retiro y limpieza
- [ ] **6.1** Borrar `portal/public/assets/adminlte/`, `adminlte.v3.backup/`, `adminlte4_extracted/`
- [ ] **6.2** Retirar `yajra/laravel-datatables-oracle` de `composer.json` (**0 referencias en `portal/app`**, verificado)
- [ ] **6.3** Actualizar `docs/manual/manual-usuario.md` con capturas de la nueva UI
- [ ] **6.4** Actualizar `02-DOCS/wiki/stack/decision-stack.md`

---

## 6. Riesgos y mitigaciones

| Riesgo | Prob. | Impacto | Mitigación |
|---|---|---|---|
| **Clases dinámicas de Tailwind no generadas** (`bg-{{ $estado }}`) | **Alta** | **Alto** — badges invisibles en producción | Mapa explícito en Fase 2.1; prohibir interpolación de clases en `REVIEW.md` |
| Sin enmienda constitucional, `verify` rechaza el cambio | Alta | Alto | Fase 0 antes de la Fase 1 |
| Tests que afirman textos de la UI actual | Media | Medio | 27 `assertSee` verificados: `'1 aviso(s) sin leer'`, `'sin archivo'`, `'quinto día hábil'`, `'Bandeja de revisión'`, `'Completitud del período'`, `'Mi cuenta'`. Conservar literal |
| ApexCharts 567 KB rompe el presupuesto p95 < 2 s | Media | Medio | Solo 2 pantallas; `defer`; medir con DevTools |
| Inter self-hosted añade peso de fuentes | Baja | Bajo | Solo subsets `latin` + `latin-ext` (2 `woff2`) |
| Regresión visual en la matriz de Completitud | Media | Alto | Comparar lado a lado contra la vista actual en los 3 anchos antes de retirar AdminLTE |
| El servidor queda sin UI si `public/build/` no se sube | Baja | **Crítico** | Checklist de despliegue: `npm run build` → subir `public/build/` es paso obligatorio |
| AdminLTE 4 sigue instalado y alguien re-referencia una clase | Baja | Bajo | Fase 6.1 borra los tres directorios |

---

## 7. Evidencia de aceptación (Definition of Done)

- [ ] `npm run build` sin errores; CSS compilado sin clases interpoladas
- [ ] `./vendor/bin/phpunit` — toda la suite en verde, sin cambios en los tests
- [ ] Las 18 vistas renderizan en 1440 / 768 / 390 px sin scroll horizontal no intencional
- [ ] Cero `404` de assets en consola; cero errores JS
- [ ] Los 6 estados de envío y los 6 de completitud se ven distintos entre sí y con texto, no solo con color
- [ ] Recorrido completo con teclado: login → panel → envío → subir → revisar → aprobar
- [ ] Contraste AA verificado en `--muted-foreground`, badges y sidebar
- [ ] `portal/public/assets/adminlte*` ya no existe y la app carga solo desde `public/build/`
- [ ] Constitución en v5.0.0 y decisión C-019 registrada

---

## 8. Decisiones pendientes (cola de decisión)

| # | Decisión | Recomendación |
|---|---|---|
| **D1** | ¿Adoptamos Tailwind v4 como canon de UI y retiramos Bootstrap/AdminLTE? | **Sí.** Es la condición sin la cual nada de esto es posible |
| **D2** | ¿Lucide en vez de FontAwesome? | **Sí.** 26 iconos mapeados, 34 usos, y quita un archivo de fuente |
| **D3** | ¿Gráficos ApexCharts en Panel y Completitud? | **Sí, limitado a 4 gráficos en 2 pantallas** |
| **D4** | Tablas: ¿buscador y facetas en cliente, o paginación del servidor? | **Híbrido:** cliente para catálogos admin (pocas filas), servidor para envíos y revisión |
| **D5** | ¿Modo oscuro para los usuarios? | **Sí**, con `prefers-color-scheme` como valor inicial y conmutador persistente |
| **D6** | ¿Presets de color y selector de densidad? | **No.** La densidad está muerta en la plantilla y los presets recolorean a medias |
| **D7** | ¿Command palette ⌘K? | **No en v1.** Reevaluar si el menú pasa de 20 entradas |
| **D8** | ¿Rama nueva o la actual? | **Nueva:** `feature/rediseno-ui-clinova` (principio 15) |

---

## 9. Próximo paso

Aprobado D1 y la rama, el orden es: **Fase 0 → Fase 1 → Fase 2**, y ahí parar para una revisión visual de la galería `/componentes` antes de tocar las 15 vistas. Rediseñar 18 plantillas sin haber validado el sistema de componentes es cómo se pierde el trabajo.

---

## 10. Rollback

```bash
git checkout feature/mvp-v2-reestructuracion
# los assets AdminLTE se retiran recién en la Fase 6
git checkout -- portal/resources/css portal/resources/js portal/resources/views
pnpm run build    # o npm run build: regenera public/build/ con la UI anterior
./vendor/bin/phpunit
```

Como la Fase 6 borra assets, **el punto de no retorno es 6.1**. Todo lo anterior es reversible con `git`.

---

*FTD creado siguiendo el patrón de cambios simples: intención → alcance → checklist → evidencia → próximo paso.*
