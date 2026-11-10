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

## Decisiones abiertas

Tomadas de `MODULO_M7_PROFUNDIZADO.md` §18. Ninguna se cierra por inferencia: las cierra negocio o TI.

> **Actualización 2026-11-10:** **D-STACK cerrada (C-010)**. Opción B canon. **D1, D4, D5, D6, D10** pendientes con negocio. **D11** resuelta (quedarse en PHP 7.4 / Laravel 8).
> El estado vigente está en `stack/decision-stack.md`.

| ID | Decisión | Opciones | Recomendación del diseño | Bloquea |
|---|---|---|---|---|
| **D1** | Fuente de identidad | Cuentas locales / correo institucional / directorio del SS | Correo institucional con respaldo local | Alta de encargados |
| **D2** | Unidad de aprobación | Por envío / por archivo | Por envío, con observaciones por archivo | Flujo de revisión |
| **D3** | Corrección tras aprobado | Bloqueada / reapertura admin / versión automática | Reapertura admin con motivo | Integridad de datos aprobados |
| **D4** | Plazo mensual | Día 5 / 10 / 15 / variable | Día 10 configurable por período | Cierre y recordatorios |
| **D5** | Series obligatorias | Todas / por tipo de establecimiento / histórico | Histórico + override manual | Completitud |
| **D6** | Tratamiento de SAX | Serie normal / especial | Serie normal con plantilla propia | Faltantes falsos |
| **D7** | Puente con CARGAS REM | A / B / C | A en MVP, B después | Alcance del MVP |
| **D8** | Notificaciones | Solo portal / portal + correo | Portal + correo | Aviso a encargados |
| **D9** | Retención de archivos | 1 / 3 / 5 años / indefinido | 5 años, revisar con jurídico | Legal y almacenamiento |
| **D10** | 2FA | Nadie / admins / revisores+admins / todos | Revisores y admins | Seguridad de acceso |
| **D11** | PHP/Laravel | Quedarse / actualizar | **Quedarse en PHP 7.4 / Laravel 8** (canon C-010) | Resuelta |
| **D12** | Antivirus | Sin / ClamAV / servicio de TI | ClamAV si TI lo provee | Archivos maliciosos |
| **D13** | Respaldos | TI / manual / sin respaldo | `mysqldump` programado + copia de `storage/` | Continuidad |
| **D14** | Migración histórica | No / parcial / total | Proyecto aparte tras el MVP | Reportes históricos |

## Cómo se cierra una decisión

1. Se escribe la opción elegida y el motivo en esta tabla (nueva fila, no se edita la anterior).
2. Si cambia un principio, se enmienda la constitución y se sube la versión (MAJOR si agrega o
   invierte un principio).
3. Si cambia el diseño, se actualiza el documento fuente y se anota el enlace.
