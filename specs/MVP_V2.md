# MVP_V2.md — Portal M7 (versión 2)

> **Documento superado.** La fuente de verdad vigente es [`MVP_V3.md`](MVP_V3.md) desde el
> 2026-11-26. Este archivo se conserva como historia del corte v2 (reparto de la revisión,
> aperturas de plazo, reapertura, Serie P semestral, calendario automático, panel y Validador
> viven en la v3).
>
> ---
>
> **Documento vivo (hasta el 2026-11-26).** Única fuente de verdad del proyecto.
> Reemplaza a `specs/_historico/AGENT.md`, `PLAN.md`, `SPECS.md` y `TASK.md`.
> Fecha: 2026-11-14
> Actualizado: 2026-11-20 — la capa UI pasa de AdminLTE 3.2 a Tailwind CSS v4 + componentes Blade propios (C-019, Constitución v5.0.0).

---

## 1. Qué es

Portal web para que los establecimientos de salud suban su REM mensual (Excel), el sistema lo valide automáticamente, y un revisor lo observe o apruebe con trazabilidad.

**Reemplaza:** el proceso actual de envío por correo + revisión manual sin trazabilidad.

**No reemplaza:** CARGAS REM (sigue siendo el sistema destino), ni el ValidadorRem (que se usa como referencia, no como dependencia en runtime).

---

## 2. Para quién

| Rol | Alcance | Qué hace |
|---|---|---|
| `encargado` | Sus establecimientos asignados | Sube archivos, corrige, envía a revisión |
| `revisor` | Establecimientos de su Servicio de Salud | Observa, aprueba |
| `admin` | Global | Usuarios, catálogos, períodos, auditoría |

**Sin rol `consulta`. Sin multi-servicio.**

El catálogo `servicios` se conserva con el registro único de Osorno (código MINSAL `23`). No hay selector ni pantalla para operar más de un Servicio: el sistema sirve a una sola Unidad de Estadísticas.

---

## 3. Stack

- Laravel 12.72 + PHP 8.2
- MySQL 8 en producción y en la suite de pruebas (`cargas_rem_test`)
- Tailwind CSS v4 + componentes Blade propios (C-019): iconos Lucide, tipografía Inter Variable self-hosted y ApexCharts diferido y acotado. Sin Node en producción; Vite compila en local y `portal/public/build/` se publica como artefacto estático.
- Fortify (auth)
- spatie/laravel-permission (roles)
- PhpSpreadsheet (lectura Excel)

---

## 4. Alcance v2.0

### Incluido

| Capacidad | Módulo |
|---|---|
| Login, logout, cuenta propia y restablecimiento de contraseña por el administrador | MOD-01 |
| 3 roles, permisos, CRUD usuarios, asignación a establecimientos | MOD-01 |
| CRUD servicios, establecimientos, series, períodos, plantillas, matriz establecimiento-serie, diccionario | MOD-02 |
| Calendario de plazos: quinto día hábil del mes siguiente, con feriados de Chile | MOD-02/MOD-11 |
| Crear envío, subir archivo, versionar, revalidar, descargar original y reporte | MOD-03 |
| Validación V0–V11 | MOD-04 |
| Máquina de estados: recibido → validado / con_errores → en_revision → observado / aprobado | MOD-05 |
| Bandeja del revisor, observaciones, aprobar | MOD-06 |
| Matriz de completitud con semáforo y totales | MOD-07 |
| Auditoría append-only (tabla + servicio + observers) | MOD-08 |
| Notificación en BD + campana en header | MOD-09 (recortado) |
| Cabeceras seguridad, CSRF, rate limit, health check, logs, validación real de archivo | MOD-11 |

El `diccionario` no tiene pantalla de edición: se carga con `CatalogoSeeder` y se usa en V6–V9.

### Fuera de v2.0

| Función | Módulo | Destino |
|---|---|---|
| Paquete ZIP, HMAC, confirmar carga | MOD-10 | v3 |
| Correo de notificación | MOD-09 | v2.1 |
| Snapshot histórico de catálogo | MOD-02 | v2.1 |
| Importador ZIP/CSV (reemplazado por CRUD + seeders) | MOD-02 | v2.1 |
| Excepciones (V8) | MOD-04 | v2.1 si aplica |
| Antivirus (ENV-032) | MOD-04 | no aplica |
| 2FA | MOD-01 | v2.1 |
| Rol consulta | MOD-01 | v2.1 |
| Multi-servicio (UI) | MOD-01 | v3 |
| Reabrir, anular | MOD-05 | v2.1 |
| Estado `cargado` | MOD-05 | v3 |
| Export CSV/Excel completitud | MOD-07 | v2.1 |
| Drill-down completitud | MOD-07 | v2.1 |
| Panel por rol (dashboard) | MOD-07 | v2.1 |
| UI admin auditoría con filtros | MOD-08 | v2.1 |
| Paridad con golden files | MOD-04 | cuando haya insumos |
| UAT formal | MOD-11 | v2.0 final |
| Simulación de mes (`rem:simular-mes`) | — | borrar |

---

## 5. Requisitos funcionales

### Autenticación
- RF-001 Login (throttle 5/1)
- RF-002 Logout
- RF-004 Restablecimiento de contraseña (lo hace el administrador; no hay recuperación por correo)

### Usuarios
- RF-013 CRUD usuarios (12+ chars, mayúscula, número)
- RF-014 Asignar establecimientos a encargado

### Catálogos
- RF-007 CRUD servicios
- RF-008 CRUD establecimientos
- RF-009 CRUD series
- RF-010 CRUD períodos
- RF-011 Matriz establecimiento-serie
- RF-012 Gestión de plantillas
- *(RF-005 y RF-006 importador/snapshot: fuera)*

### Envíos
- RF-017 Crear envío (único por establecimiento+año+mes)
- RF-018 Subir archivo (≤2MB, .xls/.xlsx/.xlsm)
- RF-019 Validación V0–V11
- RF-020 Reenvío (versionado)
- RF-021 Revalidar
- RF-022 Descargar original
- RF-023 Descargar reporte

### Estados
- RF-024 Máquina de estados
- RF-025 Enviar a revisión
- RF-026 Observar
- RF-027 Aprobar
- *(RF-028 reabrir, RF-029 anular, RF-030 confirmar carga: fuera)*

### Observaciones
- RF-031 Registrar observación
- RF-032 Ver observaciones

### Completitud
- RF-033 Matriz de completitud
- *(RF-035 export, RF-036 drill-down: fuera)*

### Auditoría
- RF-037 Auditoría append-only

### Notificaciones
- *(RF-038 correo: fuera; queda notificación en BD sin requisito formal)*

### Puente
- *(RF-039 paquete: fuera)*

### Plazo
- La fecha límite de un período es el **fin del quinto día hábil del mes siguiente** (a las 23:59:59 de `America/Santiago`).
- No cuentan sábados, domingos ni feriados. Los feriados se mantienen por año en la tabla `feriados`; el administrador los carga y corrige.
- El administrador puede fijar una fecha distinta por excepción; el sistema la respeta y no la recalcula.
- El envío copia la fecha del período al crearse. La marca «fuera de plazo» se calcula una sola vez, al llegar a revisión, y el envío se acepta mientras el período esté abierto.
- El administrador ve los doce plazos del año, con sus feriados, en la pantalla **Calendario de plazos**.

Detalle y reglas de cierre: [`02-DOCS/wiki/dominio/`](../02-DOCS/wiki/dominio/) y [`docs/validacion/reglas-cierre-periodo.md`](../docs/validacion/reglas-cierre-periodo.md).

---

## 6. Reglas de validación

Referencia completa: [`02-DOCS/wiki/dominio/validacion-rem.md`](../02-DOCS/wiki/dominio/validacion-rem.md).

Resumen:
- V0–V7, V9–V11 activas.
- V8 (excepciones) inactiva: en v2.0 no existe la tabla `excepciones`.
- ENV-032 (antivirus) no aplica.

---

## 7. Flujos

### FT-01 Subida y validación
Encargado crea envío → sube archivo → sistema valida → muestra hallazgos y resumen → botón "Enviar a revisión" aparece si 0 bloqueantes y V11 OK.

### FT-02 Revisión y aprobación
Revisor abre bandeja → filtra → abre detalle → observa (con texto obligatorio) o aprueba (con 0 bloqueantes y advertencias marcadas como revisadas).

---

## 8. Decisiones abiertas

| ID | Decisión | Bloquea |
|---|---|---|
| D5 | Series obligatorias (todas / por tipo / histórico+override) | V11 y completitud |
| D6 | Tratamiento SAX (serie normal / especial / fuera) | Plantillas y matriz |

**D1 (identidad) quedó resuelta el 2026-11-14 (D-030):** cuentas locales que
crea el administrador, con un correo válido cualquiera, y contraseña
restablecida por el administrador. No hay recuperación por correo; cada usuario
cambia la suya en **Mi cuenta**.

**D4 (plazo) quedó resuelta el 2026-11-14 (D-029):** quinto día hábil del mes
siguiente, con calendario de feriados de Chile. El cierre del período sigue
siendo manual y lo declara el administrador.

D12, D13, D14 y todo lo demás: fuera de v2.0.

---

## 9. Criterios de aceptación

Se conservan del histórico: CA-001, 002, 004, 005 (CRUD equivalente), 007–014, 017–024, 026–028, 032, 033, 034, 037.
Se eliminan: CA-003, 006 (importador/snapshot), 025 (reabrir), 029–031 (reabrir/anular/cargar), 035–036 (export/drill-down), 038–039 (correo/paquete). El perfil propio y el reset por el administrador quedan cubiertos por RF-004.

Además, del calendario de plazos (D-029):

- La fecha límite de cada período se calcula con el quinto día hábil del mes siguiente y el administrador la ve en **Calendario de plazos**.
- Un feriado cargado corre la fecha; una fecha manual se respeta y se distingue como tal.
- Un envío creado copia la fecha del período y se marca «fuera de plazo» una sola vez al llegar a revisión.

---

## 10. Fuera de este documento

- `specs/_historico/` — trazabilidad de v1. No se usa.
- `docs/operacion/` — notas operativas de v1. Algunas siguen aplicando (paridad, matriz de catálogo); revisar caso a caso.
