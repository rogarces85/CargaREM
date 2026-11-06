---
type: stack
title: D-STACK — decisión de stack del portal (reabierta)
description: Registro de la decisión pendiente sobre el stack del portal M7. La confirmación de PHP 7.4.3 invalida el cierre anterior.
tags: [stack, decision, abierta, laravel, php, reabierta]
timestamp: 2026-11-06T20:30:00Z
topic: stack
status: open
---

# D-STACK — decisión de stack del portal

> **Estado: REABIERTA el 2026-11-06.**
> El diagnóstico del servidor confirmó **PHP 7.4.3** en el vhost principal (`mod_php`).
> El cierre anterior (C-007, Opción A) se basaba en la asunción incorrecta de PHP 8.3.
> Ver reversión en `02-DOCS/wiki/sdd/decisions.md` (C-009).
> Comparación actualizada: [`opciones-stack.md`](opciones-stack.md).

## 1. Por qué se reabre

| Fecha | Hecho |
|---|---|
| 2026-11-06 19:00 | C-007 cierra D-STACK asumiendo PHP 8.3 (basado en `sist_observaciones`) |
| 2026-11-06 20:15 | Diagnóstico directo en `sist_envios` devuelve **PHP 7.4.3** |
| 2026-11-06 20:30 | **D-STACK reabierta**: la decisión anterior no se sostiene |

La evidencia del sistema de Observaciones (PHP 8.2) corresponde a **otro vhost o PHP-FPM**, no al Apache principal que sirve este proyecto.

## 2. Canon anterior (ya no vigente)

| Capa | Decisión anterior | Estado |
|---|---|---|
| Lenguaje | PHP 8.3 mínimo; 8.5 objetivo | **Inválido** |
| Framework | Laravel 13.x | **Inválido** (requiere PHP 8.3+) |
| Base de datos | MySQL 8 | Se mantiene |
| UI | AdminLTE 4.x | Se mantiene |

## 3. Opciones honestas ahora

| # | Opción | Ventaja | Costo / riesgo | Viable hoy |
|---|---|---|---|---|
| **A1** | **Actualizar servidor** a Ubuntu 22.04/24.04 + PHP 8.3/8.4 | Laravel 13, soporte hasta 2029 | Requiere TI, ventana de mantenimiento, pruebas | ❓ Preguntar a TI |
| **A2** | **PHP-FPM 8.3** solo para este vhost | Laravel 13 sin tocar Apache principal | Requiere TI, configuración de proxy | ❓ Preguntar a TI |
| **B** | **Laravel 8.83 + PHP 7.4.3 + MySQL 8 + AdminLTE 3** | Corre **hoy** sin cambios de TI | 4 componentes EOL; CVEs 2026; Bootstrap 4 muerto; migración futura 6–10 sem | ✅ Sí |
| **C** | **Symfony 6.4 LTS + PHP 7.4 + MySQL 8 + AdminLTE 4** | LTS hasta nov-2027 (último con PHP 7.4) | Reescribe §11; más config; menos «baterías incluidas» | ✅ Sí |
| **D** | **CodeIgniter 4.6 + PHP 7.4 + MySQL 8 + AdminLTE 4** | Huella pequeña, curva baja | Sin LTS oficial; auth/RBAC a mano; 4.7+ exige PHP 8.2+ | ✅ Sí |
| **E** | **Slim 4 / PHP plano + AdminLTE 4** (como Observaciones) | Control total, corre en 7.4 | Construyes el framework: auth, RBAC, migraciones, tests | ✅ Sí |

**Fuera de concurso:** Laravel 13, Laravel 12, CodeIgniter 4.7, Symfony 7.4 — **exigen PHP 8.2+**.

## 4. Criterios para elegir (actualizados)

1. **¿TI permite actualizar el servidor o instalar PHP-FPM 8.3?** Si sí → A1/A2. Si no → B–E.
2. **Soporte de seguridad** durante la vida del portal (mínimo 3 años).
3. **Despliegue por FTP sin SSH**: ¿el stack produce artefacto subible?
4. **Lectura de Excel legado** (`.xls`, `.xlsx`, `.xlsm`) sin ejecutar macros.
5. **Roles, permisos, 2FA** disponibles sin servidor de aplicaciones.
6. **Conocimiento del equipo**: quién mantiene esto en 12 meses.
7. **Costo de migración futura** si se elige B, C, D o E.

## 5. Qué cierra D-STACK (ahora)

1. **TI responde** si se puede: (a) actualizar Ubuntu/PHP, (b) instalar PHP-FPM 8.3, (c) habilitar `opcache`/`pdo_sqlite`/`bcmath` en 7.4, (d) dar SSH.
2. **El responsable elige** con los criterios de arriba.
3. Se registra en `02-DOCS/wiki/sdd/decisions.md` con fecha, opciones y motivo.
4. Se enmienda la constitución: **MAJOR** (agrega/quita canon de stack).
5. Se actualiza `stack_modulo_envio.md` §2 y, si cambia el framework, §11 del diseño M7.

## 6. Mientras esté abierta

- No entra código de aplicación a `main` (constitución, principio 1).
- Sí se puede: documentar, diagramar, preparar *fixtures*, escribir el catálogo de errores, definir la máquina de estados y los contratos del puente.
- Los prototipos van en una rama descartable y no se fusionan.

## 7. Preguntas concretas para TI (bloquean la decisión)

| Pregunta | Si SÍ abre… |
|---|---|
| ¿Actualizar Ubuntu 20.04 → 22.04/24.04? | A1 (Laravel 13) |
| ¿Instalar PHP-FPM 8.3 para `sist_envios`? | A2 (Laravel 13) |
| ¿Habilitar `opcache`, `pdo_sqlite`, `bcmath` en PHP 7.4? | Laravel 10/11 en 7.4 (aún EOL) |
| ¿Acceso SSH para el usuario del proyecto? | Despliegue estándar con `artisan` |
| ¿Cron disponible? | Tareas programadas nativas |
| ¿MySQL → 8.4 LTS? | Fin de deuda de 8.0 EOL |

Con las respuestas a las tres primeras se define si A1/A2 son posibles. Si todas son NO, la elección es entre B, C, D, E.