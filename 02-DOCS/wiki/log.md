---
type: log
title: Bitácora de la wiki — Cargas
description: Registro append-only de lo que se agregó o cambió en 02-DOCS/wiki/.
tags: [log, wiki]
timestamp: 2026-11-06T14:30:00Z
topic: log
---

# Bitácora de la wiki

> Append-only. Una línea por cambio de conocimiento, con fecha y ruta.

| Fecha | Cambio | Ruta |
|---|---|---|
| 2026-11-06 | Harness rsc 4.0.5 instalado (OpenCode, flujo `branches`) | `.rsc.json`, `AGENTS.md`, `.opencode/` |
| 2026-11-06 | Constitución v1.0.0 ratificada con 28 principios; D-STACK queda abierta | `sdd/constitution.md` |
| 2026-11-06 | Registro de decisiones creado con C-001 a C-005 y D1–D14 abiertas | `sdd/decisions.md` |
| 2026-11-06 | Restricciones del servidor separadas de la decisión de stack | `stack/restricciones-servidor.md` |
| 2026-11-06 | D-STACK documentada con opciones A/B/C y criterios de cierre | `stack/decision-stack.md` |
| 2026-11-06 | Pipeline V0–V11 y paridad con *golden files* sintetizados | `dominio/validacion-rem.md` |
| 2026-11-06 | `REVIEW.md` ratificado: fallos graves, rutas a omitir, tope de nits y rutas que exigen persona | `REVIEW.md` |
| 2026-11-06 | Ratificación explícita del usuario a la constitución v1.0.0, sin enmiendas | `sdd/constitution.md` · `sdd/decisions.md` (C-006) |
| 2026-11-06 | Investigación de 5 opciones de stack con relojes de soporte verificados y evidencia HTTP del servidor | `stack/opciones-stack.md` |
| 2026-11-06 | D-STACK cerrada con la Opción A (Laravel 13 / PHP 8.3 / MySQL 8 / AdminLTE 4) | `stack/decision-stack.md` · `sdd/decisions.md` (C-007) |
| 2026-11-06 | Corrección de restricciones del servidor: PHP 7.4.3 y 2 MB dejan de ser «confirmados»; se documenta el diagnóstico sin SSH | `stack/restricciones-servidor.md` |
| 2026-11-06 | Entorno local documentado: XAMPP trae PHP 8.2.12 y MariaDB 10.4; se exige PHP 8.3 y MySQL 8 para paridad | `stack/entorno-local.md` |
| 2026-11-06 | Constitución enmendada a v2.0.0 (MAJOR): canon de stack, principios 1–3, 19 y 20, Definition of Done | `sdd/constitution.md` |
| 2026-11-06 | Decisión de MVP: L1 local con SQLite y archivos reales anonimizados desde L2 | `sdd/decisions.md` (C-008) |
| 2026-11-06 | **Diagnóstico directo**: PHP 7.4.3 confirmado en vhost principal; asunción de 8.3 era incorrecta | `stack/restricciones-servidor.md` |
| 2026-11-06 | **D-STACK reabierta** (C-009): C-007 invalidada; opciones viables A1/A2/B/C/D/E | `stack/decision-stack.md` · `sdd/decisions.md` |
| 2026-11-06 | Constitución enmendada a **v3.0.0 (MAJOR)**: elimina canon de stack, restaura principios 1–3 restrictivos | `sdd/constitution.md` |
| 2026-11-06 | Índice y decisiones actualizados a v3.0.0 / D-STACK reabierta | `index.md` · `sdd/decisions.md` |
| 2026-11-10 | **D-STACK cerrada definitivamente (C-010)**: Laravel 8.83 + PHP 7.4.3 + AdminLTE 3.2 canon | `stack/decision-stack.md` · `sdd/decisions.md` (C-010) |
| 2026-11-10 | Constitución enmendada a **v3.1.0 (MAJOR)**: D-STACK cerrada, canon Option B, Principio 1 habilita código | `sdd/constitution.md` |
| 2026-11-10 | Nomenclatura unificada: sistema legacy = "Cargas REM"; ejecutable = `ValidadorRem.exe` | `specs/AGENT.md` · `specs/SPECS.md` · `specs/TASK.md` |
| 2026-11-10 | Estrategia de ramas Git documentada: `main` protegida, `docs/constitucion-y-stack` base, `feature/*` por fase | `specs/AGENT.md` |
| 2026-11-10 | `specs/TASK.md` creado: 112 tareas atómicas F0–F6 con dependencias, verificación, DoD | `specs/TASK.md` |
| 2026-11-11 | `specs/AGENT.md` alineado al canon C-010 (Laravel 8.83 / PHP 7.4.3 / AdminLTE 3.2) y a la Constitución v3.1.0; cierra CONT-01 | `specs/AGENT.md` · `specs/PLAN.md` |
| 2026-11-11 | `specs/SPECS.md` alineado al canon C-010: stack, AdminLTE 3.2, decisiones cerradas; cierra CONT-02 | `specs/SPECS.md` · `specs/PLAN.md` |
| 2026-11-11 | `README.md` reescrito como puerta de entrada: mapa real del repo, canon C-010, estado 4/112 y próximos pasos | `README.md` |
| 2026-11-11 | Wiki: índice corregido al flujo real (sin `inbox/` ni `raw/`) y sección de `specs/` agregada | `02-DOCS/wiki/index.md` |
| 2026-11-11 | F0 completo en el papel: matriz de series, reglas de cierre e identidad/2FA redactados; andamiaje de *golden files* con `manifest.json` | `docs/validacion/` · `docs/auth/` · `tests/Parity/fixtures/` · `specs/TASK.md` |
| 2026-11-11 | Hoja de firma de una página para la severidad de los 32 `ENV-###` (24 bloqueantes, 8 advertencias) | `docs/validacion/hoja-firma-severidad.md` |
| 2026-11-11 | Harness: target `deepseek` agregado junto a `opencode`; 34 skills en `.dsh/skills/` | `.rsc.json` · `.dsh/` · `02-DOCS/wiki/harness/installation-plan.md` |
| 2026-11-11 | Entorno local verificado en la máquina: XAMPP 8.2.12 / PHP 8.2.12 / MariaDB 10.4.32, límites reales, diferencias contra producción y estrategia en dos fases (C-011) | `02-DOCS/wiki/stack/entorno-local.md` · `sdd/decisions.md` · `README.md` |
| 2026-11-11 | **Se parte de cero (C-012):** bases `cargasrem` y `cargasrem_test` respaldadas y eliminadas; catálogos a reimportar desde las Access del sistema | `docs/datos/fuentes-datos-mdb.md` · `sdd/decisions.md` · `stack/entorno-local.md` |
| 2026-11-11 | Inventario verificado de las bases Access: `Global.mdb` 185.526 prestaciones, 3.207 establecimientos (86 de Osorno), 393.053 filas de datos en 9 bases `DatosSERIE` | `docs/datos/fuentes-datos-mdb.md` |
| 2026-11-11 | **Fuente oficial de datos declarada (C-013):** `C:\Program Files (x86)\Minsal\Cargas Rem 2016`, solo lectura; las demás copias no son válidas | `docs/datos/fuentes-datos-mdb.md` · `sdd/decisions.md` |
| 2026-11-11 | Exportador `.mdb → CSV` construido y probado: 200.367 filas en 26 s desde la instalación oficial, con `manifest.json` y hashes | `01-TOOLS/rem-export/` |
| 2026-11-11 | **F1 iniciada:** Laravel 8.83.29 en `portal/`, platform PHP 7.4.3, base `portal_m7`, `sql_mode` estricto, `America/Santiago`, locale `es`. Responde 200 por Apache | `portal/` · `specs/TASK.md` |
| 2026-11-11 | Dependencias base instaladas: spatie/permission 5.11.1, Fortify 1.19.1, DataTables 9.21.2, Sanctum 2.15.1. AdminLTE 3.2.0 en `public/assets/adminlte/`. `/health` con 2 tests | `portal/` |
| 2026-11-11 | **Riesgo de dependencias documentado:** Laravel 8.83 tiene 5 avisos abiertos (1 crítico) y PhpSpreadsheet 1.29.12 tiene 13. Se registran D15 y D16 | `docs/seguridad/dependencias-eol.md` · `sdd/decisions.md` |
| 2026-11-11 | **D16 resuelto:** PhpSpreadsheet **1.30.8** no tiene avisos (el bloqueo era de `zipstream`). Regla: ruta generada por el servidor | `docs/seguridad/dependencias-eol.md` · `portal/composer.json` |
| 2026-11-11 | **D-STACK reabierta (C-014):** no se acepta el riesgo de Laravel 8. Constitución a **v3.2.0**, F1 congelada y solicitud de PHP 8.1+ a TI | `sdd/constitution.md` · `docs/infra/solicitud-php-81-ti.md` · `README.md` |
