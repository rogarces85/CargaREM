---
type: index
title: Cargas — mapa de conocimiento
description: Índice de la wiki del proyecto. Cada fila apunta a la fuente; la wiki no duplica los documentos canónicos.
tags: [index, knowledge, cargas]
timestamp: 2026-11-06T14:30:00Z
topic: index
---

# Mapa de conocimiento — Cargas

> Esta wiki es la puerta de entrada. Los documentos de la raíz siguen siendo la fuente; aquí se
> enlazan y se sintetiza lo que un agente necesita antes de actuar.
> Convenciones de marcado (✅ confirmado · 🟡 propuesto · ❓ pendiente): `README.md`.

## Documentos canónicos (raíz del repositorio)

| Documento | Qué contiene |
|---|---|
| [`informe.md`](../../informe.md) | Informe técnico de ValidadorRem: módulos M1–M6, reglas de negocio, flujos, §2.10 del módulo de envío, pendientes P1–P13 |
| [`stack_modulo_envio.md`](../../stack_modulo_envio.md) | Restricciones del servidor y propuesta de stack para el portal (propuesta, no decisión) |
| [`backup_documentacion/MODULO_M7_PROFUNDIZADO.md`](../../backup_documentacion/MODULO_M7_PROFUNDIZADO.md) | Diseño funcional y técnico del portal M7: dominio, estados, pipeline, seguridad, pruebas |
| [`docs/FLUJO_DATOS_INTEGRAL_SISTEMA.md`](../../docs/FLUJO_DATOS_INTEGRAL_SISTEMA.md) | Flujo de datos de punta a punta |
| [`REVIEW.md`](../../REVIEW.md) | Qué mira el revisor y qué exige una persona antes de fusionar |

## specs/ — especificación operativa del MVP

| Documento | Qué contiene |
|---|---|
| [`AGENT.md`](../../specs/AGENT.md) | Contexto, alcance, actores y stack canon (C-010) |
| [`SPECS.md`](../../specs/SPECS.md) | Requisitos funcionales, reglas de negocio y criterios de aceptación |
| [`PLAN.md`](../../specs/PLAN.md) | Arquitectura, módulos técnicos MOD-01 a MOD-11, modelo de datos y flujos |
| [`TASK.md`](../../specs/TASK.md) | **112 tareas atómicas** en 7 fases, cada una con verificación y DoD |

## sdd/ — reglas y decisiones

| Artículo | Qué contiene |
|---|---|
| [`sdd/constitution.md`](sdd/constitution.md) | Constitución **v4.0.0**: **D-STACK cerrada (C-015)** con canon Laravel **12.72** + PHP **8.2**, 28 principios y Definition of Done. |
| [`sdd/decisions.md`](sdd/decisions.md) | Registro append-only de decisiones, incluidas D-STACK cerrada (C-010) y D1/D4/D5/D6/D10 pendientes |

## stack/ — entorno y decisión **reabierta (C-014)**

| Artículo | Qué contiene |
|---|---|
| [`stack/opciones-stack.md`](stack/opciones-stack.md) | Investigación de 5 opciones (Opción A requería PHP 8.3, hoy inválida) |
| [`stack/decision-stack.md`](stack/decision-stack.md) | **D-STACK cerrada (C-015)**: canon Laravel 12.72 + PHP 8.2 + AdminLTE 3.2. La página conserva el historial de C-010 y su reapertura |
| [`stack/restricciones-servidor.md`](stack/restricciones-servidor.md) | Evidencia confirmada: PHP 7.4.3, sin pdo_sqlite/opcache/bcmath, diagnóstico directo |
| [`stack/entorno-local.md`](stack/entorno-local.md) | **Entorno local verificado (2026-11-11):** XAMPP 8.2.12, PHP 8.2.12, MariaDB 10.4.32, límites reales, estrategia en dos fases y las diferencias que rompen en producción |
| [`docs/stack/prototipo-laravel-12.md`](../../docs/stack/prototipo-laravel-12.md) | **Prototipo de Laravel 12 sobre PHP 8.2:** cero avisos, dependencias modernas y el diff estructural contra Laravel 8. Es la prueba de que la opción C es viable |

## dominio/ — reglas del negocio REM

| Artículo | Qué contiene |
|---|---|
| [`dominio/validacion-rem.md`](dominio/validacion-rem.md) | Pipeline V0–V11, severidad, códigos `ENV-###`, paridad con *golden files* y presupuesto de ejecución |

## datos/ — fuentes maestras

| Artículo | Qué contiene |
|---|---|
| [`docs/datos/fuentes-datos-mdb.md`](../../docs/datos/fuentes-datos-mdb.md) | **Inventario verificado** de `Global.mdb` (402,9 MB), `Trans.mdb` y las 9 bases `DatosSERIE` con datos (393.053 filas), el mapeo a tablas del portal y el camino `.mdb → CSV → MySQL` |

## seguridad/ — riesgo de dependencias

| Artículo | Qué contiene |
|---|---|
| [`docs/seguridad/dependencias-eol.md`](../../docs/seguridad/dependencias-eol.md) | Cómo se pasó de Laravel 8.83 —5 avisos sin parche, uno crítico— a Laravel 12.72 sin ninguno: la cadena de decisiones y las prácticas que se conservaron |

## infra/ — lo que depende de TI

| Artículo | Qué contiene |
|---|---|
| [`docs/infra/solicitud-php-82-ti.md`](../../docs/infra/solicitud-php-82-ti.md) | **Solicitud para enviar a TI:** PHP 8.2 en el vhost, la misma versión de Observaciones, con la evidencia de los 5 avisos y el prototipo de Laravel 12 ya probado |

## harness/ — cómo trabaja el agente

| Artículo | Qué contiene |
|---|---|
| [`harness/installation-plan.md`](harness/installation-plan.md) | Plan de harness aceptado (rsc 4.0.5, target OpenCode) |
| [`harness/decisions.md`](harness/decisions.md) | Decisiones del harness |
| `harness/user-profile.md` | Perfil local (nivel técnico, tipo de proyecto). No se commitea |

## Cómo crece esta wiki

1. **Lo operativo va a [`specs/`](../../specs/), no a la wiki.** Especificación, plan y tareas son la fuente del trabajo.
2. **Lo nuevo de conocimiento entra aquí** como artículo con frontmatter (`type`, `title`, `description`, `tags`, `timestamp`, `topic`).
3. Toda página nueva se enlaza desde este índice.
4. Toda decisión relevante se agrega a [`sdd/decisions.md`](sdd/decisions.md). Es append-only: no se reescribe una entrada, se agrega la que la reemplaza.
5. Todo cambio de la wiki se anota con una línea en [`log.md`](log.md).
6. **Las fuentes originales no se copian aquí:** se enlazan desde su ruta canónica. La wiki sintetiza, no duplica (Constitución, principio 27).
| [`docs/operacion/como-probar.md`](../../docs/operacion/como-probar.md) | Puesta en marcha desde cero y cómo validar archivos REM reales |
| [`docs/operacion/paridad.md`](../../docs/operacion/paridad.md) | Cómo se mide la paridad contra el ValidadorRem y qué formato tiene el resultado esperado |
