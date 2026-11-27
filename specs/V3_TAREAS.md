# V3_TAREAS.md — checklist de la versión 3

> Rama `feature/mejoras-m7-v3`, creada desde `main` (`c50c63e`) el 2026-11-26.
> Especificación: [`MVP_V3.md`](MVP_V3.md). Plan: el documento aprobado en la sesión
> del 26-11 (10 fases).
> **Estado: las diez fases completas, suite en verde y `public/build/` recompilado.**

## Fase 0 — Documento

| # | Tarea | Hecho | Evidencia |
|---|---|---|---|
| 0.1 | `specs/MVP_V3.md` como fuente vigente | ✅ | Archivo creado; 12 secciones |
| 0.2 | `MVP_V2.md` marcado como superado | ✅ | Cabecera del archivo |
| 0.3 | Decisiones C-020 y D-041 a D-050 | ✅ | `02-DOCS/wiki/sdd/decisions.md` |
| 0.4 | Constitución v6.0.0 con §10 | ✅ | `02-DOCS/wiki/sdd/constitution.md` |

## Fase 1 — RBAC y asignación

| # | Tarea | Hecho | Evidencia |
|---|---|---|---|
| 1.1 | 16 → 20 permisos en el seeder y en el fixture | ✅ | `RolePermissionSeeder::MATRIZ`, `tests/Parity/fixtures/rbac-matrix.json` v3.0 |
| 1.2 | `App\Dominio\Alcance` como única regla de alcance | ✅ | Clase nueva; la usan envíos, revisión, completitud, panel y aperturas |
| 1.3 | `Envio::scopeForUser` por asignación, sin caer a «todo» | ✅ | `app/Models/Envio.php` |
| 1.4 | Pantalla `admin/asignaciones` y reparto en el formulario de usuario | ✅ | Controlador + dos vistas |
| 1.5 | Aviso al administrador cuando un usuario queda sin establecimientos | ✅ | `Avisar::sinAsignacion` |
| 1.6 | Índices de alcance | ✅ | Migración `..._100001_indices_alcance_panel` |
| 1.7 | Tests de aislamiento | ✅ | `AsignacionRevisoresTest`: 12 tests |

## Fase 2 — Avisos de subida

| # | Tarea | Hecho | Evidencia |
|---|---|---|---|
| 2.1 | Eventos `subida_serie` y `subida_completa` con distintivo | ✅ | `EnvioNotificacion`, `Notificador::subida` |
| 2.2 | Destinatarios: revisores asignados; sin revisor, el administrador | ✅ | `Notificador::revisores` |
| 2.3 | Fusión de subidas seguidas en una ventana de 10 minutos | ✅ | `Notificador::avisoDeSubidaAbierto` |
| 2.4 | Insignia «Serie suelta» / «Envío completo» en la campana y el historial | ✅ | `partials/campana.blade.php`, `notificaciones/index.blade.php` |
| 2.5 | Tests | ✅ | `NotificacionSubidaTest`: 7 tests |

## Fase 3 — Reapertura y nueva subida

| # | Tarea | Hecho | Evidencia |
|---|---|---|---|
| 3.1 | `EstadoEnvioService::reabrir` con motivo y auditoría | ✅ | Método nuevo; `PERMITIDAS` sin cambios |
| 3.2 | `envios.reabrir` para Equipo REM y administrador | ✅ | Seeder + fixture + `AuthTest` |
| 3.3 | Pantalla de reapertura en el detalle del envío | ✅ | `envios/show.blade.php` |
| 3.4 | `SolicitudReenvioService` con tres alcances | ✅ | Clase nueva; pantalla `revision/reenvio.blade.php` |
| 3.5 | Tests | ✅ | `ReaperturaTest`: 13 tests |

## Fase 4 — Aperturas de fuera de plazo

| # | Tarea | Hecho | Evidencia |
|---|---|---|---|
| 4.1 | Tabla `aperturas` y `envios.apertura_id` | ✅ | Migración `..._100003_aperturas_fuera_plazo` |
| 4.2 | `AperturaService` con solicitar, otorgar, resolver, anular y vigencia | ✅ | Clase nueva |
| 4.3 | Integración en `EnvioService` (crear y subir) | ✅ | `verificarCarga` |
| 4.4 | Pantalla única con las dos caras y su policy | ✅ | `aperturas/index.blade.php`, `AperturaPolicy` |
| 4.5 | Avisos y auditoría | ✅ | `apertura.solicitada`, `apertura.aprobada`, `apertura.rechazada`, `apertura.abierta`, `apertura.anulada` |
| 4.6 | Comando `rem:aperturas:cerrar` y tarea programada | ✅ | `routes/console.php` |
| 4.7 | Tests | ✅ | `AperturaFueraPlazoTest`: 12 tests |

## Fase 5 — Serie P y calendario

| # | Tarea | Hecho | Evidencia |
|---|---|---|---|
| 5.1 | `series.meses_exigidos` y regla en `MatrizSeries` | ✅ | Migración `..._100002` + `aplicarVigencia` |
| 5.2 | Los cuatro lectores dicen lo mismo | ✅ | `SeriePSemestralTest` (V11, pantalla, aprobación, completitud) |
| 5.3 | `App\Support\Plazos` como única generación de un año | ✅ | Usado por el controlador y los comandos |
| 5.4 | `rem:periodos:generar`, `rem:calendario --verificar`, `rem:feriados:cargar` | ✅ | Comandos nuevos y ampliados |
| 5.5 | Feriados 2027 con el 17-09 de la Ley 20.983 | ✅ | `FeriadosSeeder::FERIADOS` |
| 5.6 | Tareas programadas sin dependencia | ✅ | `routes/console.php`, con el portal funcionando sin cron |
| 5.7 | Tests | ✅ | `SeriePSemestralTest` (7) y `CalendarioAutomaticoTest` (11) |

## Fase 6 — Módulo Envíos

| # | Tarea | Hecho | Evidencia |
|---|---|---|---|
| 6.1 | Filtros por año, mes, comuna, establecimiento, estado y plazo | ✅ | `EnvioController::index` |
| 6.2 | Orden por período, comuna, establecimiento y estado | ✅ | `EnvioController::ordenar` |
| 6.3 | Años y comunas tomados de los datos; paginación con filtros | ✅ | `aniosDisponibles`, `comunasDisponibles`, `withQueryString` |
| 6.4 | Tests | ✅ | `EnviosFiltrosTest`: 13 tests |

## Fase 7 — Panel

| # | Tarea | Hecho | Evidencia |
|---|---|---|---|
| 7.1 | `PanelService` por rol, con el alcance aplicado | ✅ | Clase nueva |
| 7.2 | `PanelController` (deja de ser un closure) | ✅ | `routes/web.php` |
| 7.3 | Panel con tarjetas, gráficos y tabla de respaldo | ✅ | `panel.blade.php` |
| 7.4 | `charts.js` con ApexCharts diferido y tema | ✅ | `resources/js/ui/charts.js`; chunk aparte de 992 KB |
| 7.5 | Tests | ✅ | `PanelTest`: 8 tests |

## Fase 8 — Validador

| # | Tarea | Hecho | Evidencia |
|---|---|---|---|
| 8.1 | Contrato del JSON documentado y versionado | ✅ | `config/validador/reglas.json` |
| 8.2 | Motor con seis tipos y rechazo de los desconocidos | ✅ | `App\Validador\*` |
| 8.3 | Tabla `validador_ejecuciones` y registro de uso | ✅ | Migración `..._100004` |
| 8.4 | Pantallas de revisión y de estadísticas | ✅ | `validador/` (tres vistas) |
| 8.5 | Aislamiento demostrado | ✅ | `test_el_validador_no_toca_ninguna_tabla_del_flujo` |
| 8.6 | Reacción al JSON real de la Unidad | ⏳ | El motor y el contrato están listos; las reglas concretas se cargan cuando llegue el archivo |
| 8.7 | Tests | ✅ | `ModuloValidadorTest`: 12 tests |

## Fase 9 — Cierre documental

| # | Tarea | Hecho | Evidencia |
|---|---|---|---|
| 9.1 | Wiki: índice, bitácora y artículos de dominio | ✅ | `02-DOCS/wiki/` |
| 9.2 | Reglas de cierre de período con aperturas | ✅ | `docs/validacion/reglas-cierre-periodo.md` |
| 9.3 | Manuales por rol con el Equipo REM | ✅ | `docs/manual/` |
| 9.4 | `REVIEW.md` con los fallos graves nuevos | ✅ | `REVIEW.md` |
| 9.5 | Estado del proyecto y números | ✅ | `docs/entrega/estado-del-proyecto.md` |
| 9.6 | Suite completa y build | ✅ | 416 tests, `public/build/` regenerado |

## Números

| Métrica | v2 | v3 |
|---|---|---|
| Tests | 285 | **416** |
| Permisos | 16 | **20** |
| Roles | 3 | 3 (uno cambia de nombre visible) |
| Migraciones | 20 | **24** |
| Vistas | 43 | **51** |
| Comandos `rem:*` | 8 | **11** |

## Lo que queda pendiente

| Tema | Por qué |
|---|---|
| Reglas concretas del Validador | Depende del `.json` de la Unidad; el contrato y el motor están listos |
| Nombres y correos exactos de los cuatro integrantes | Los carga el administrador; el seeder trae los provisionales |
| UAT con personas reales | Guion actualizado en `docs/uat/guion-uat.md`; falta ejecutarlo |
| Revisión con el panel en un servidor con muchos años | El presupuesto está medido con un año; conviene repetirlo con tres |
