---
type: stack
title: D-STACK — decisión de stack del portal (cerrada C-010)
description: Decisión final: Laravel 8.83 + PHP 7.4.3 + MySQL 8 + AdminLTE 3.2. TI confirmó que no hay PHP-FPM 8.3 ni actualización de servidor.
tags: [stack, decision, cerrada, laravel, php, c-010]
timestamp: 2026-11-10T12:30:00Z
topic: stack
status: closed
---

# D-STACK — decisión de stack del portal

> **Estado: CERRADA el 2026-11-10 (C-010).**
> El diagnóstico del servidor confirmó **PHP 7.4.3** en el vhost principal (`mod_php`).
> TI confirmó que **no hay PHP-FPM 8.3** ni actualización de servidor prevista.
> Se adopta **Opción B** como canon: Laravel 8.83 + PHP 7.4.3 + MySQL 8 + AdminLTE 3.2.
> Ver registro en `02-DOCS/wiki/sdd/decisions.md` (C-010) y Constitución v3.1.0.

## 1. Historial

| Fecha | Hecho |
|---|---|
| 2026-11-06 19:00 | C-007 cierra D-STACK asumiendo PHP 8.3 (basado en `sist_observaciones`) |
| 2026-11-06 20:15 | Diagnóstico directo en `sist_envios` devuelve **PHP 7.4.3** |
| 2026-11-06 20:30 | **D-STACK reabierta** (C-009): la decisión anterior no se sostiene |
| 2026-11-10 12:00 | **D-STACK cerrada (C-010)**: TI confirma que no hay PHP-FPM 8.3 ni actualización; se adopta Opción B |

## 2. Canon confirmado (C-010)

| Capa | Tecnología | Versión | Notas |
|---|---|---|---|
| Lenguaje | PHP | 7.4.3 | EOL 28-nov-2022; mitigar con WAF/proxy |
| Framework | Laravel | 8.83 | Última compatible con PHP 7.4 |
| Base de datos | MySQL | 8.0.42 → 8.4 LTS | Objetivo 8.4 antes de abril 2026 |
| UI | AdminLTE | 3.2 | Bootstrap 4; assets precompilados |
| JavaScript | Vanilla | — | Sin Node en producción |
| Despliegue | FTP | — | Artefacto local completo |

## 3. Opciones evaluadas (histórico)

| # | Opción | Resultado |
|---|---|---|
| A1 | Actualizar servidor a Ubuntu 22.04/24.04 + PHP 8.3 | **Descartada**: TI no aprueba |
| A2 | PHP-FPM 8.3 para vhost `sist_envios` | **Descartada**: TI no aprueba |
| **B** | **Laravel 8.83 + PHP 7.4.3 + MySQL 8 + AdminLTE 3** | **SELECCIONADA** (canon) |
| C | Symfony 6.4 LTS + PHP 7.4 | Descartada: más config, menos "baterías" |
| D | CodeIgniter 4.6 + PHP 7.4 | Descartada: sin LTS, auth/RBAC a mano |
| E | Slim 4 + PHP plano + AdminLTE 4 | Descartada: construyes el framework |

## 4. Decisiones derivadas

| Decisión | Valor | Fuente |
|---|---|---|
| `composer.json` platform | `php: 7.4.3` | TEC-01 |
| PhpSpreadsheet | 1.29.x | TEC-04 |
| spatie/laravel-permission | 5.11 | TEC-07 |
| Laravel Fortify | 1.x (compat 8.x) | TEC-06 |
| Laravel Sanctum | 2.15 (puente B) | TEC-11 |

## 5. Principio 1 ya no bloquea

D-STACK **cerrada** → el Principio 1 de la Constitución (no introducir stack mientras D-STACK abierta) **ya no aplica**. Código de aplicación puede entrar en ramas `feature/*` desde `docs/constitucion-y-stack`.

## 6. Preguntas a TI (resueltas: todas NO)

| Pregunta | Respuesta | Impacto |
|---|---|---|
| ¿Actualizar Ubuntu 20.04 → 22.04/24.04? | **NO** | Descarta A1 |
| ¿Instalar PHP-FPM 8.3 para `sist_envios`? | **NO** | Descarta A2 |
| ¿Habilitar `opcache`, `pdo_sqlite`, `bcmath` en PHP 7.4? | Pendiente | Solo afecta SQLite local |
| ¿Acceso SSH para el usuario del proyecto? | **NO** | Despliegue FTP only |
| ¿Cron disponible? | **NO confirmado** | Sin workers/colas |
| ¿MySQL → 8.4 LTS? | Planificado | Objetivo antes abril 2026 |

---

**D-STACK cerrada. Stack canon confirmado. Código de aplicación autorizado en ramas feature.**