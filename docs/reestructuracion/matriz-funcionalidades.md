# Matriz de funcionalidades — MVP v2.0

> **Fecha:** 2026-11-14
> **Rama:** `feature/mvp-v2-reestructuracion`
> **Especificación vigente:** [`specs/MVP_V2.md`](../../specs/MVP_V2.md)
> **Decisión:** C-018 en [`02-DOCS/wiki/sdd/decisions.md`](../../02-DOCS/wiki/sdd/decisions.md)

Esta matriz es la revisión funcional del corte v2.0. Cada fila tiene evidencia
en el repositorio y una decisión cerrada. Reemplaza la matriz del 13-11, que
quedó archivada en [`docs/_historico/`](../_historico/).

---

## 1. Cómo leerla

- **Queda:** se conserva sin cambios de alcance.
- **Adaptar:** se conserva, pero cambia por el documento del 14-11.
- **Eliminar:** sale del sistema y se borran todos sus indicios activos.
- **Confirmado fuera:** no existe como función y se documenta que no se
  construirá.

---

## 2. Matriz

| # | Funcionalidad | Evidencia | Decisión | Acción |
|---|---|---|---|---|
| 1 | Login, logout, cuenta propia y reset por el administrador | `portal/config/fortify.php`, `PerfilController`, `Admin\UsuarioController` | Adaptar | Sin recuperación por correo: el administrador crea la cuenta, fija el correo y restablece la contraseña (D-030). |
| 2 | Catálogo `servicios` y CRUD (RF-007) | `servicios`, `portal/app/Http/Controllers/Admin/ServicioController.php`, `admin/servicios` | Queda | Restaurado con un registro activo (Osorno `23`); el revisor se acota por Servicio. |
| 3 | 2FA TOTP y passkeys | `config/fortify.php` features | Eliminar | Fuera desde C-017. |
| 4 | Rol `consulta` y multi-servicio | `RolePermissionSeeder`, `Envio::scopeForUser` | Eliminar | Tres roles; un Servicio. |
| 5 | Catálogos por snapshot e importador ZIP/CSV | `catalogo_snapshots`, `CatalogoService`, `admin/snapshots`, `catalogos:importar` | Eliminar | Tablas y pantallas fuera; el catálogo se siembra con `CatalogoSeeder` desde `REM_CATALOGO_DIR`. |
| 6 | Diccionario de validación | `diccionario`, `ImportadorCatalogo` | Queda | Solo como dato de V6–V9; sin pantalla de edición. |
| 7 | Plantillas, hojas y cabecera (RF-012) | `plantillas`, `plantilla_hojas`, `Admin\PlantillaController` | Adaptar | CRUD de plantillas con hojas; `plantilla_cabecera` queda de solo lectura (seeder). |
| 8 | Excepciones / V8 | `excepciones`, `ReglaV8Excepciones` | Eliminar | Fuera; V8 inactiva en v2.0. |
| 9 | Envío mensual y versionado | `EnvioService`, `envio_archivos` | Queda | Sin `snapshot_id` por archivo. |
| 10 | Validación V0–V11 | `app/Validacion/` | Adaptar | Cadena sin V8; V4 compara Servicio o asignación. |
| 11 | Reporte de validación | `ReporteService` | Queda | Sin cambios. |
| 12 | Estados y transiciones | `EstadoEnvioService` | Adaptar | `aprobado` es final; fuera reabrir, anular, `cargado`. |
| 13 | Revisión y observaciones | `RevisionController`, `ObservacionService` | Queda | Sin botones de reabrir/anular. |
| 14 | Aprobación | `EstadoEnvioService::aprobar` | Queda | Guardas completas; marca los archivos vigentes. |
| 15 | Plazo | `CalendarioService`, `periodos.fecha_limite`, `feriados` | Adaptar | Quinto día hábil del mes siguiente con feriados de Chile (D-029); override manual por excepción; la marca se calcula al llegar a revisión. |
| 16 | Completitud | `CompletitudService`, `completitud/index` | Adaptar | Semáforo, totales y atraso; sin exportación ni drill-down. |
| 17 | Auditoría append-only | `AuditoriaService`, observers, listeners de acceso | Adaptar | Sin pantalla de consulta; ahora audita también el CRUD de usuarios y catálogos. |
| 18 | Notificaciones | `Notificador`, `EnvioNotificacion`, campana | Adaptar | Solo base de datos + campana en la cabecera; sin correo. |
| 19 | Paquete ZIP y confirmación de carga | — | Eliminar | Fuera desde C-017. |
| 20 | Panel por rol (dashboard) | `resources/views/panel.blade.php` | Fuera | El panel es un índice de navegación; sin tablero por rol. |
| 21 | UI de auditoría con filtros | `admin/auditoria` | Eliminar | Fuera de v2.0. |
| 22 | Simulación de mes | `rem:simular-mes` | Eliminar | Comando borrado. |
| 23 | Calendario de plazos y feriados | `CalendarioService`, `feriados`, `admin/calendario`, `admin/feriados`, `rem:calendario` | Nuevo (D-029) | Quinto día hábil del mes siguiente con feriados de Chile; override manual; aviso si falta el año. |

---

## 3. Consecuencias

- El catálogo son las tablas vivas: `servicios`, `establecimientos`, `series`,
  `periodos`, `establecimiento_serie`, `plantillas`, `plantilla_hojas`,
  `plantilla_cabecera` y `diccionario`.
- No hay `catalogo_snapshots`, `excepciones` ni `snapshot_id` en archivos.
- `aprobado` es el estado final del envío y de sus archivos.
- La bitácora se consulta en base de datos; no hay pantalla.
- La campana de la cabecera es el único aviso al usuario.

## 4. Pendientes de verificación

- [x] `php artisan migrate:fresh --seed` en la base de pruebas.
- [x] Suite completa en verde (257 tests).
- [ ] Barrido de referencias activas en README y manuales.
- [x] Recorrido encargado → revisor con el catálogo sembrado ([resultado](../uat/resultado-2026-11-14.md)); falta la UAT con personas.
