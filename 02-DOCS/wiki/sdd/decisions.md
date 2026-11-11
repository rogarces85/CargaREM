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
| 2026-11-11 | C-012 | **Se parte de cero.** Base nueva `portal_m7` con el esquema del módulo M7. Los catálogos se reimportan desde `Global.mdb` y las bases Access del sistema. Las bases heredadas `cargasrem` y `cargasrem_test` se respaldan y se eliminan | Reutilizar el esquema previo / reutilizar solo su catálogo / partir de cero | Las dos bases no las creó el equipo y no se reconocen como parte del proyecto. Su esquema no coincide con el del M7 (roles, estados, nombres) y su catálogo estaba desactualizado: 180.536 filas de `Diccionario` contra 185.526 reales. La fuente de verdad son los `.mdb` del sistema. Ver `docs/datos/fuentes-datos-mdb.md` |
| 2026-11-11 | C-013 | **Fuente oficial de datos:** `C:\Program Files (x86)\Minsal\Cargas Rem 2016`. Toda importación de catálogos e histórico sale de esa ruta, en solo lectura | Instalación activa / copia en `dyad-apps` / respaldos del escritorio | Confirmado por el usuario. Es la única copia que el sistema mantiene actualizada contra los web services del Minsal: 402,9 MB de `Global.mdb` contra 83,2 MB de la copia antigua. Las demás son respaldos y no son válidas como fuente |
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

## Decisiones abiertas

Tomadas de `MODULO_M7_PROFUNDIZADO.md` §18. Ninguna se cierra por inferencia: las cierra negocio o TI.

> **Actualización 2026-11-10:** **D-STACK cerrada (C-010)**. Opción B canon. **D1, D2, D3, D4, D5, D6, D7, D8, D9, D10 resueltas**. **D11** resuelta (quedarse en PHP 7.4 / Laravel 8). **D12, D13, D14** pendientes con TI.
> El estado vigente está en `stack/decision-stack.md`.

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

## Cómo se cierra una decisión

1. Se escribe la opción elegida y el motivo en esta tabla (nueva fila, no se edita la anterior).
2. Si cambia un principio, se enmienda la constitución y se sube la versión (MAJOR si agrega o
   invierte un principio).
3. Si cambia el diseño, se actualiza el documento fuente y se anota el enlace.
