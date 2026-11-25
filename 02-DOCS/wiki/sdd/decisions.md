---
type: decisions
title: Registro de decisiones — Cargas
description: Bitácora append-only de decisiones del proyecto. Cada entrada tiene fecha, opciones y motivo.
tags: [sdd, decisions, log]
timestamp: 2026-11-06T14:30:00Z
topic: sdd
---

# Registro de decisiones

> Append-only. No se borra ni se reescribe una entrada: se agrega la que la reemplaza y se marca
> la anterior como superada. Toda decisión significativa pasa por aquí (constitución, principio 26).

## Decisiones tomadas

| Fecha | ID | Decisión | Opciones consideradas | Motivo |
|---|---|---|---|---|
| 2026-11-06 | H-001 | Instalar el harness rsc 4.0.5 con target OpenCode y flujo `branches` | Instalar / no instalar; varios asistentes | El usuario pidió el harness; OpenCode es el asistente en uso y `main` debe quedar protegida |
| 2026-11-11 | H-002 | Agregar el target `deepseek` al harness, en paralelo a OpenCode | Solo OpenCode / agregar deepseek / migrar a deepseek / desinstalar rsc | El proyecto se trabaja también desde DeepSeek Harness y rsc 4.1.2 lo soporta de forma nativa. Aditivo: `.dsh/skills/` no reemplaza a `.opencode/` |
| 2026-11-11 | C-011 | **Desarrollo en dos fases**: MVP completo en local (XAMPP 8.2.12 / PHP 8.2.12 / MariaDB 10.4.32), y traslado al servidor Linux (PHP 7.4.3 / MySQL 8.0.42) solo cuando el flujo funcione de punta a punta | Construir directo contra el servidor / dos fases | Iterar en local es más rápido y sin riesgo; en el servidor cada prueba depende de FTP. Las diferencias PHP 8.2→7.4 y MariaDB→MySQL 8 se controlan con `platform.php`, PHPStan `phpVersion: 70400` y `sql_mode` estricto, y se validan en la fase 2. Ver `stack/entorno-local.md` |
| 2026-11-11 | C-012 | **Se parte de cero.** Base nueva `cargas_rem` con el esquema del módulo M7. Los catálogos se reimportan desde `Global.mdb` y las bases Access del sistema. Las bases heredadas `cargasrem` y `cargasrem_test` se respaldan y se eliminan | Reutilizar el esquema previo / reutilizar solo su catálogo / partir de cero | Las dos bases no las creó el equipo y no se reconocen como parte del proyecto. Su esquema no coincide con el del M7 (roles, estados, nombres) y su catálogo estaba desactualizado: 180.536 filas de `Diccionario` contra 185.526 reales. La fuente de verdad son los `.mdb` del sistema. Ver `docs/datos/fuentes-datos-mdb.md` |
| 2026-11-11 | C-013 | **Fuente oficial de datos:** `C:\Program Files (x86)\Minsal\Cargas Rem 2016`. Toda importación de catálogos e histórico sale de esa ruta, en solo lectura | Instalación activa / copia en `dyad-apps` / respaldos del escritorio | Confirmado por el usuario. Es la única copia que el sistema mantiene actualizada contra los web services del Minsal: 402,9 MB de `Global.mdb` contra 83,2 MB de la copia antigua. Las demás son respaldos y no son válidas como fuente |
| 2026-11-11 | C-014 | **D-STACK reabierta.** Se pide a TI PHP 8.2 —la versión que ya corre Observaciones— para subir a Laravel 12.68+, primera rama sin avisos. El código de `portal/` queda **provisional y congelado** hasta la respuesta | Mantener C-010 con riesgo aceptado / reabrir y exigir PHP 8.1+ / migrar a otro framework | `composer audit` sobre el proyecto real: Laravel 8.83.29 arrastra **5 avisos abiertos, uno crítico**, y no recibirá parches (EOL). Las mitigaciones reducen la exposición pero no eliminan el riesgo: el próximo aviso será igual de incurable. PHP 8.1 solo no corrige nada — los avisos son del framework — por eso el pedido es PHP 8.1+ **para** Laravel 10.50.4+. Detalle: `docs/seguridad/dependencias-eol.md` |
| 2026-11-11 | C-016 | **Nombre de la base: `cargas_rem`.** Minúsculas con guion bajo, y `cargas_rem_test` para la suite | `portal_m7` / `CargasREM` / `cargas_rem` | `portal_m7` no referenciaba al sistema. Se elige la forma en minúsculas porque el MySQL de producción corre en Linux con `lower_case_table_names=0` y `CargasREM` funcionaría en Windows y fallaría en el servidor. Queda consistente con `observaciones_rem`, del mismo servicio |
| 2026-11-11 | C-015 | **D-STACK cerrada (definitiva para el MVP):** Laravel **12.72** + PHP **8.2** + MySQL 8 + AdminLTE 3.2. `portal/` se rehace sobre Laravel 12 | Volver a Laravel 8 con mitigaciones / esperar a TI / **cerrar con Laravel 12** | El prototipo dio `composer audit` **sin avisos** con Laravel 12.72.0 y con las cuatro dependencias en versión moderna. Laravel 10 y 11 están afectados y quedan descartados. **Se acepta a sabiendas** que el despliegue no ocurre hasta que TI habilite PHP 8.2; desarrollo y puesta en marcha quedan desacoplados |
| 2026-11-06 | C-001 | Ratificar la constitución v1.0.0 con 28 principios | Constitución mínima / completa / sin constitución | Cierra la fase `constitution` y da una barra verificable antes de construir |
| 2026-11-06 | C-002 | **D-STACK queda abierta**: no se ratifica Laravel 8 / PHP 7.4 como canon | A: Laravel 8/PHP 7.4 · B: actualizar a PHP 8 + framework vigente · C: otro stack | El usuario declaró que aún no define el stack y pidió dejarlo pendiente |
| 2026-11-06 | C-003 | Barra de tests: obligatorios para cada regla V0–V11, transición y permiso; **sin umbral de cobertura** | TDD + 80% · tests obligatorios sin umbral · sin exigencia | El usuario eligió tests obligatorios sin umbral |
| 2026-11-06 | C-004 | Commits con `gitmoji + Conventional Commits` | Conventional · gitmoji + Conventional · libre | El usuario eligió gitmoji + Conventional. En OpenCode no hay hook: se verifica en `review` |
| 2026-11-06 | C-005 | Idioma: base de datos, estados y UI en español; clases, métodos y variables en inglés | BD/UI español + código inglés · todo español · todo inglés salvo UI | El usuario eligió la separación; el dominio REM ya está nombrado en español |
| 2026-11-06 | C-006 | El usuario ratificó explícitamente la constitución v1.0.0 sin cambios | Ratificar tal cual · enmendar algún principio | Cierre formal de la fase `constitution`; la versión queda vigente y solo cambia por enmienda |
| 2026-11-06 | C-007 | **D-STACK cerrada**: Laravel 13 + PHP 8.3 (objetivo 8.5) + MySQL 8 + AdminLTE 4 | A · B · C · D · E · Node/Next | Investigación de 5 opciones y evidencia de campo en `estadisticas.ssosorno.cl`. Se ratifica la idea original del usuario con versiones soportadas. Ver `stack/opciones-stack.md` |
| 2026-11-06 | C-008 | El MVP se construye primero en local con SQLite (L1: modelo y esqueleto); los archivos reales anonimizados entran en L2 | Todo en el servidor · L1 en local · solo archivos sintéticos | Prueba el modelo y el flujo antes de tocar producción. La paridad se valida en MySQL, no en SQLite |
| 2026-11-06 | C-009 | **D-STACK reabierta**: diagnóstico confirmó PHP 7.4.3 en el vhost principal; la asunción de 8.3 era incorrecta | Mantener C-007 · Reabrir D-STACK | El cierre anterior se basó en evidencia de otro vhost (`sist_observaciones` con PHP-FPM 8.2). El Apache principal sirve 7.4. Ver `stack/decision-stack.md` |
| 2026-11-10 | C-010 | **D-STACK cerrada (definitiva)**: Laravel 8.83 + PHP 7.4.3 + MySQL 8.0.42 + AdminLTE 3.2 (Bootstrap 4) | A1/A2 (Laravel 13/PHP 8.3) / B (Laravel 8/PHP 7.4) / C/D/E | TI confirmó que NO hay PHP-FPM 8.3 ni actualización de servidor; PHP 7.4.3 es la realidad del vhost principal. Se adopta Opción B como canon. Se enmienda Constitución a v3.1.0 (MAJOR). |
| 2026-11-10 | D-001 | **D1 - Fuente de identidad**: Cuentas locales | Cuentas locales / correo institucional / directorio del SS | Admin crea usuario + password en portal; recuperación por email. Simplifica onboarding y no depende de TI. |
| 2026-11-10 | D-004 | **D4 - Plazo mensual**: Día 10 configurable por período | Día 5 / 10 / 15 / variable | Día 10 configurable en `periodos.fecha_limite`; balance entre tiempo de carga y margen de revisión. |
| 2026-11-10 | D-005 | **D5 - Series obligatorias**: Histórico + override manual | Todas / por tipo de establecimiento / histórico | Baseline según histórico real; admin edita `establecimiento_serie` (obligatoria, vigencia_desde/hasta). |
| 2026-11-10 | D-006 | **D6 - Tratamiento SAX**: Fuera del MVP (fase futura) | Serie normal / especial / opcional en V11 | SAX es serie especial con datos propios; se excluye del MVP para no bloquear F1-F5. Se abordará en fase posterior. |
| 2026-11-10 | D-002 | **D2 - Unidad de aprobación**: Mixto (envío con veto por serie) | Por envío / por archivo / mixto | Se aprueba el envío completo; una serie con bloqueante veta la aprobación; revisor resuelve serie a serie. |
| 2026-11-10 | D-003 | **D3 - Corrección tras aprobado**: Versión automática nueva | Reapertura admin / versión automática / bloqueada | Encargado sube nueva versión; sistema crea versión automáticamente sin reapertura admin; trazabilidad completa. |
| 2026-11-10 | D-007 | **D7 - Puente CARGAS REM**: Opción A (descarga manual ZIP) | A / B / C | MVP: revisor descarga ZIP con archivos + manifiesto.json; carga manual en CARGAS REM. B/C en fases futuras. |
| 2026-11-10 | D-008 | **D8 - Notificaciones**: Solo portal (BD) | Portal + correo / solo portal / solo correo / asíncrono | Notificación en tabla `notifications` siempre; sin correo por simplicidad y ausencia de colas confirmadas. |
| 2026-11-10 | D-009 | **D9 - Retención**: 5 años | 1 / 3 / 5 años / indefinido | Cumple normativa salud (Ley 21.719); revisar con jurídica; backup anual programado. |
| 2026-11-10 | D-010 | **D10 - 2FA**: Revisores y admins | Nadie / admins / revisores+admins / todos | Roles con mayor privilegio (aprobar, reaperturar, usuarios) requieren 2FA; encargados solo password robusto. |

### MVP v2 (2026-11-13)

| Fecha | ID | Decisión | Opciones | Motivo |
|---|---|---|---|---|
| 2026-11-13 | C-017 | **Alcance MVP v2:** el portal termina en la aprobación de la estadística. Se retiran paquete ZIP, manifiesto, HMAC, confirmación de carga, 2FA, rol `consulta` y multi-servicio. Se conservan catálogos, validación V0–V11, completitud, auditoría, notificaciones, observaciones y reapertura | Mantener el alcance anterior / reducir al núcleo / reescribir el MVP | La reunión interna definió que el sistema hacía más de lo necesario. El valor del MVP es recibir, validar, controlar el plazo y aprobar. Los archivos quedan en el servidor para revisión posterior |
| 2026-11-13 | D-017 | **Plazo:** se marca «fuera de plazo» y se permite la carga mientras el período esté abierto. El cierre del período sí bloquea | Bloquear al vencer / marcar y permitir / solo mostrar la fecha / cierre manual sin marca | Un envío rechazado es un dato que no existe. El atraso es información para la jefatura y el revisor. Coincide con la recomendación de D4 |
| 2026-11-13 | D-018 | **Aprobación:** la estadística completa del envío. No hay aprobación por archivo suelto | Envío completo / por archivo / mixto | El revisor aprueba un período completo. Las series se observan por separado, pero la aprobación es del conjunto |
| 2026-11-13 | D-019 | **Sin base de datos fina:** el portal no persiste prestaciones ni resultados. Guarda archivos, hallazgos y metadatos. El `diccionario` queda como catálogo de validación | Guardar datos / solo archivos y hallazgos / eliminar el diccionario | La consolidación no es necesaria para el MVP. El `diccionario` sí es necesario para V6–V9 |
| 2026-11-13 | D-020 | **Sin paquete ni confirmación de carga:** se eliminan ZIP, manifiesto, HMAC, estado `cargado` y acción de confirmar carga | Mantener el puente / eliminarlo | Los archivos ya quedan en el servidor. No hay integración automática con CARGAS REM en el MVP |
| 2026-11-13 | D-021 | **Sin 2FA, rol `consulta` ni multi-servicio:** tres roles y un solo Servicio de Salud | Mantener / reducir | Menos superficie de acceso y menos configuración. El revisor ve todo el Servicio; el encargado ve lo asignado |
| 2026-11-13 | D-022 | **Servicio único:** Osorno, código MINSAL `23`. La importación filtra los establecimientos por ese código | Multi-servicio / servicio único | El portal sirve a la Unidad de Estadísticas de Osorno. El código 23 está confirmado en `SetServicio` |

## Decisiones abiertas

Tomadas de `MODULO_M7_PROFUNDIZADO.md` §18. Ninguna se cierra por inferencia: las cierra negocio o TI.

> **Actualización 2026-11-10:** **D-STACK cerrada (C-010)**. Opción B canon. **D1, D2, D3, D4, D5, D6, D7, D8, D9, D10 resueltas**. **D11** resuelta (quedarse en PHP 7.4 / Laravel 8). **D12, D13, D14** pendientes con TI.
> El estado vigente está en `stack/decision-stack.md`.
>
> **Actualización 2026-11-13 — MVP v2:** D-002 (aprobación mixta) queda superada por D-018; D-007 (puente ZIP) por D-020; D-010 (2FA) por D-021. D-008 se mantiene con la implementación actual: notificación en base de datos y correo. El alcance vigente es `specs/MVP_V2.md`.
>
> **Actualización 2026-11-14 — alineación con `MVP_V2.md` (versión 2):** C-018 redefine el alcance. Se retiran reapertura y anulación (D-023), el correo de notificación (D-024, revisa D-008), los snapshots e importador de catálogo (D-025), las excepciones V8 (D-026) y la exportación, el drill-down, la UI de auditoría y `rem:simular-mes` (C-018). Se restaura el catálogo de servicios (D-027, revisa D-021) y el plazo queda sin valor por defecto (D-028, revisa D-017). D4 vuelve a abierta.

| ID | Decisión | Opciones | Recomendación del diseño | Bloquea | Estado |
|---|---|---|---|---|---|
| **D1** | Fuente de identidad | Cuentas locales / correo institucional / directorio del SS | **Cuentas locales** (resuelta D-001) | Alta de encargados | ✅ **Resuelta** |
| **D2** | Unidad de aprobación | Por envío / por archivo / mixto | **Mixto: envío con veto por serie** (resuelta D-002) | Flujo de revisión | ✅ **Resuelta** |
| **D3** | Corrección tras aprobado | Bloqueada / reapertura admin / versión automática | **Versión automática nueva** (resuelta D-003) | Integridad de datos aprobados | ✅ **Resuelta** |
| **D4** | Plazo mensual | Día 5 / 10 / 15 / variable | **Día 10 configurable** (resuelta D-004) | Cierre y recordatorios | ✅ **Resuelta** |
| **D5** | Series obligatorias | Todas / por tipo de establecimiento / histórico | **Histórico + override manual** (resuelta D-005) | Completitud | ✅ **Resuelta** |
| **D6** | Tratamiento de SAX | Serie normal / especial | **Fuera del MVP** (resuelta D-006) | Faltantes falsos | ✅ **Resuelta (fase futura)** |
| **D7** | Puente con CARGAS REM | A / B / C | **Opción A: descarga manual ZIP** (resuelta D-007) | Alcance del MVP | ✅ **Resuelta (MVP=A)** |
| **D8** | Notificaciones | Solo portal / portal + correo | **Solo portal (BD)** (resuelta D-008) | Aviso a encargados | ✅ **Resuelta** |
| **D9** | Retención de archivos | 1 / 3 / 5 años / indefinido | **5 años** (resuelta D-009) | Legal y almacenamiento | ✅ **Resuelta** |
| **D10** | 2FA | Nadie / admins / revisores+admins / todos | **Revisores y admins** (resuelta D-010) | Seguridad de acceso | ✅ **Resuelta** |
| **D11** | PHP/Laravel | Quedarse / actualizar | **Quedarse en PHP 7.4 / Laravel 8** (canon C-010) | Resuelta | ✅ Resuelta |
| **D12** | Antivirus | Sin / ClamAV / servicio de TI | ClamAV si TI lo provee | Archivos maliciosos | Pendiente |
| **D13** | Respaldos | TI / manual / sin respaldo | `mysqldump` programado + copia de `storage/` | Continuidad | Pendiente |
| **D14** | Migración histórica | No / parcial / total | Proyecto aparte tras el MVP | Reportes históricos | Pendiente |
| **D15** | Riesgo de Laravel 8.83 en EOL | Aceptar con mitigaciones / exigir PHP 8.2 | — | ✅ **Resuelta (C-014): no se acepta.** Se reabre D-STACK y se pide PHP 8.1+ a TI | |
| **D16** | Lector de Excel | A: PhpSpreadsheet 1.30.8 con ruta del servidor · B: conversión externa · C: exigir PHP 8.1 | F2 | ✅ **Resuelta: 1.30.8.** Sin avisos abiertos y compatible con PHP 7.4.3 y 8.1+. Regla: el lector recibe una ruta generada por el servidor, nunca el nombre que subió el usuario | |

### Alineación v2.0 (2026-11-14)

| Fecha | ID | Decisión | Opciones | Motivo |
|---|---|---|---|---|
| 2026-11-14 | C-018 | **Alcance v2.0:** se adopta `specs/MVP_V2.md` del 14-11 como única fuente de verdad y se recorta la superficie a lo que ese documento incluye. Fuera: reabrir, anular, correo de notificación, snapshots e importador de catálogo, excepciones V8, exportación y drill-down de completitud, panel por rol, UI de auditoría con filtros, `rem:simular-mes`. Vuelve el catálogo de servicios | Mantener el alcance del 13-11 / adoptar el del 14-11 | El documento del 14-11 es la versión revisada del alcance: deja el MVP en recibir, validar, revisar y aprobar, sin operaciones de excepción ni infraestructura que no se usa en el corte |
| 2026-11-14 | D-023 | **Sin reapertura ni anulación:** la máquina de estados termina en `aprobado` y solo tiene las transiciones persona→`en_revision`, `observado`, `aprobado` | Mantener reabrir/anular / retirarlos | Toda operación de excepción agrega motivos, permisos y pantallas. v2.0 las deja para v2.1; los envíos ya aprobados no se corrigen en el corte |
| 2026-11-14 | D-024 | **Notificaciones solo en BD:** se retira el correo de avisos y se muestra una campana en la cabecera. `MAIL_*` se conserva por la recuperación de contraseña | Correo + BD / solo BD | Sin colas garantizadas el correo es una dependencia frágil; la campana se ve al entrar y no depende de un servidor saliente |
| 2026-11-14 | D-025 | **Catálogo sin snapshots ni importador:** las tablas de catálogo son las vivas; se cargan con `CatalogoSeeder` desde el mismo ZIP/CSV de `01-TOOLS/rem-export` descomprimido en `REM_CATALOGO_DIR` | Snapshots versionados / catálogo vivo sembrado | El snapshot por archivo agregaba una tabla y una pantalla que el MVP no usa. Se conserva el resultado de la validación (`validaciones`, `validado_en`) como evidencia; se pierde la reproducción exacta por versión de catálogo |
| 2026-11-14 | D-026 | **Sin excepciones (V8):** se retira la regla, el modelo y la tabla `excepciones` | Mantener V8 / retirarla | No hay datos de excepciones en el catálogo del corte; vuelve en v2.1 si D6 (SAX) la necesita |
| 2026-11-14 | D-027 | **Catálogo de servicios restaurado:** tabla `servicios`, CRUD (RF-007) y `servicio_id` en usuarios y establecimientos, con un registro activo (Osorno `23`). V4 compara el Servicio del usuario con el del establecimiento | Un solo Servicio fijo / catálogo restaurado | El documento del 14-11 conserva RF-007 y la separación revisor/Servicio. El catálogo mantiene el modelo coherente con el histórico y no obliga a hardcodear el `23` |
| 2026-11-14 | D-028 | **Plazo sin valor por defecto:** se conserva la marca «fuera de plazo» cuando el período declara `fecha_limite`; no se inventa un día 10 | Día 10 por defecto / sin defecto | D4 sigue abierta (§8 del documento). Fijar el día 10 en código cerraba la decisión por la puerta de atrás |
| 2026-11-14 | D-029 | **D4 resuelta — calendario de plazos:** un período se recibe hasta el fin del **quinto día hábil del mes siguiente** (23:59:59 de `America/Santiago`). No cuentan sábados, domingos ni feriados. Los feriados se mantienen por año en `feriados` (semilla con los 16 nacionales de 2026, Ley 19.973) y el administrador los gestiona. El cierre del período sigue siendo manual; una fecha manual por excepción (`fecha_limite_manual`) se respeta | Día 10 fijo / quinto día hábil con calendario / fecha por período sin cálculo | La Unidad de Estadísticas recibe hasta el quinto día hábil del mes siguiente. El cálculo con feriados evita adelantar plazos en Semana Santa, mayo y septiembre. La pantalla **Calendario de plazos** y `rem:calendario` muestran el año completo |
| 2026-11-14 | D-030 | **D1 resuelta — identidad:** cuentas locales que **crea el administrador**; el correo lo fija él y basta con que sea válido (sin dominio obligatorio). La contraseña la **restablece el administrador**; no hay recuperación por correo ni token. Cada usuario cambia la suya en **Mi cuenta** (`/perfil`). Fortify queda solo para login/logout. Esto rectifica la mención de D-024 sobre conservar el correo: el portal ya no envía correo | Local + reset por administrador / correo institucional / directorio SS | La Unidad no tiene directorio ni SMTP garantizado; el administrador ya da de alta las cuentas. Sin enlace por correo desaparece una dependencia y una vía de recuperación que nadie podía mantener. El costo es que toda clave olvidada pasa por el administrador |

> **Estado de D4 (2026-11-14):** vuelve a *abierta*. El documento vigente no fija
> día y el código no tiene plazo por defecto. Bloquea el cierre automático de
> período, no la recepción de envíos.
>
> **Cierre de D4 (2026-11-14, misma fecha):** la Unidad de Estadísticas fijó la
> regla — **quinto día hábil del mes siguiente**, con calendario de feriados de
> Chile — y queda implementada como D-029. Ver
> `docs/validacion/reglas-cierre-periodo.md`.
>
> **Cierre de D1 (2026-11-14):** la Unidad confirmó cuentas locales creadas por
> el administrador, correo válido sin dominio obligatorio y reset por el
> administrador (D-030). El portal ya no envía correo: se retiró la recuperación
> por enlace.

### Rediseño de UI (2026-11-16)

Aprueba D1 y desbloquea `02-DOCS/feature-rediseno-ui-clinova.md`. Enmienda la constitución a **v5.0.0**.

| Fecha | ID | Decisión | Opciones | Motivo |
|---|---|---|---|---|
| 2026-11-16 | C-019 | **La capa UI del canon pasa de AdminLTE 3.2 (Bootstrap 4) a Tailwind CSS v4 + componentes Blade propios**, compilados con Vite en local y subidos como `portal/public/build/`. Se retiran AdminLTE, Bootstrap y FontAwesome | Mantener AdminLTE 4 / migrar a Tailwind v4 / sistema propio desde cero | `portal/resources/css/app.css` ya importa `tailwindcss` y cada `npm run build` genera 43 KB de CSS que **ningún layout cargaba**. El portal mantenía tres juegos de assets (`adminlte/`, `adminlte.v3.backup/`, `adminlte4_extracted/`), FontAwesome solo para 26 iconos, y `yajra/laravel-datatables-oracle` con **0 referencias** en `portal/app`. Se conserva la restricción de no usar Node en el servidor: el build es un artefacto estático |
| 2026-11-16 | D-031 | **Iconos: Lucide** (SVG inyectado) en lugar de FontAwesome (fuente de iconos) | FontAwesome / Lucide / SVG a mano | 26 iconos distintos en 34 usos. Lucide los cubre todos, elimina un archivo de fuente y su declaración `@font-face` |
| 2026-11-16 | D-032 | **Gráficos: ApexCharts, diferido, acotado a 4 gráficos en 2 pantallas** (Panel y Completitud) | Sin gráficos / ApexCharts / motor propio | El chunk pesa 567 KB. Con `import()` solo se descarga en las páginas que declaran `[data-chart]`. Si crece, choca con el presupuesto p95 < 2 s (principio 25) |
| 2026-11-16 | D-033 | **Tablas híbridas:** búsqueda en cliente para los catálogos de administración (pocas filas); paginación del servidor para envíos y revisión | Todo cliente / todo servidor / híbrido | Los catálogos caben en una página y el filtro instantáneo evita un viaje al servidor. Envíos y revisión ya paginan en servidor y la bandeja se presupuesta con 100 filas (principio 25) |
| 2026-11-16 | D-034 | **Modo oscuro disponible**, con `prefers-color-scheme` como valor inicial y conmutador persistente | Solo claro / claro + oscuro automático / conmutador | El tema oscuro es gratis con tokens `oklch` en dos capas. Se toma del sistema y no obliga a nadie a elegir al entrar |
| 2026-11-16 | D-035 | **Sin presets de color ni selector de densidad** | Con / sin | Los presets de la plantilla de referencia reescriben solo 4 variables y dejan el acento descolgado: una paleta a medias es peor que una fija. El selector de densidad está **muerto en la plantilla original** (`--card-padding` se define 3 veces y ninguna regla lo consume) |
| 2026-11-16 | D-036 | **Sin paleta de comandos (⌘K)** | Con / sin | El menú tiene 13 entradas. Mantener un índice de rutas con palabras clave no se paga con ese tamaño |
| 2026-11-16 | D-037 | **El rediseño va en rama `feature/rediseno-ui-clinova`**, creada sobre el estado de `feature/adminlte4-bootstrap5` | Rama actual / rama nueva | Principio 15. `main` está cerrada para el agente |
| 2026-11-25 | D-038 | **La matriz de series exige todas las series activas al importar el catálogo**, y el administrador ajusta cuál se le pide a cada establecimiento desde la pantalla de matriz. La derivación desde el histórico de 12 meses (R-SER-01) no se implementa | Todas al importar (elegida) / derivar del histórico / exigir por tipo de establecimiento | Decisión del usuario: «importar los 86 establecimientos y aplicar las series para todos, después yo las podría editar». El portal no tiene el histórico de meses cargados que R-SER-01 necesita. Exigir todo arranca con ruido visible en la matriz, que se corrige hacia abajo; no exigir nada se corrige tarde, cuando el faltante aparece en la consolidación. Ver `02-DOCS/feature-matriz-series-vigencia.md` |
| 2026-11-25 | D-039 | **La entrega de la matriz de series va por pull request** (`#5`), no por merge local | Merge local / PR | `main` está cerrada para el agente por decisión de proyecto, y el repo ya entregó sus cuatro features anteriores por PR. El PR deja el cambio revisable en el forja aunque lo fusione la misma persona |
| 2026-11-25 | D-040 | **Se retira la rama `rsc/knowledge`**, conservando su contenido en la etiqueta `historico/rsc-knowledge-2026-11-10` | Fusionar / dejar la rama / etiquetar y borrar | Sus 6 commits (6 y 10 de noviembre) son escrituras automáticas de rsc con el canon **superado**: Constitución v3.1.0, Laravel 8.83 y PHP 7.4. Ningún archivo es exclusivo y `main` tiene la versión posterior de todos (v5.0.0). Fusionarla habría retrocedido 8 archivos de wiki 15 días. La etiqueta conserva los commits y el contenido: comprobado que `git show historico/…:sdd/constitution.md` sigue devolviendo v3.1.0 |

> **Estado de la FTD previa (2026-11-16):** `02-DOCS/feature-adminlte4-bootstrap5-migration.md` quedó **ejecutada en el árbol de trabajo pero sin commit**. AdminLTE 4.10.2 está instalado y las 18 vistas usan su marcado. Se cierra en la misma fecha y su contenido se presume en la base de este rediseño. Ver la nota de bloqueo en §3 de `feature-rediseno-ui-clinova.md`.



## Cómo se cierra una decisión

1. Se escribe la opción elegida y el motivo en esta tabla (nueva fila, no se edita la anterior).
2. Si cambia un principio, se enmienda la constitución y se sube la versión (MAJOR si agrega o
   invierte un principio).
3. Si cambia el diseño, se actualiza el documento fuente y se anota el enlace.

