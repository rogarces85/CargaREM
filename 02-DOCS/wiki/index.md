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

## sdd/ — reglas y decisiones

| Artículo | Qué contiene |
|---|---|
| [`sdd/constitution.md`](sdd/constitution.md) | Constitución **v3.1.0**: D-STACK cerrada (C-010), canon Laravel 8.83 + PHP 7.4.3 + AdminLTE 3.2, 28 principios y Definition of Done |
| [`sdd/decisions.md`](sdd/decisions.md) | Registro append-only de decisiones, incluidas D-STACK cerrada (C-010) y D1/D4/D5/D6/D10 pendientes |

## stack/ — entorno y decisión **cerrada (C-010)**

| Artículo | Qué contiene |
|---|---|
| [`stack/opciones-stack.md`](stack/opciones-stack.md) | Investigación de 5 opciones (Opción A requería PHP 8.3, hoy inválida) |
| [`stack/decision-stack.md`](stack/decision-stack.md) | **D-STACK cerrada (C-010)**: canon Laravel 8.83 + PHP 7.4.3 + AdminLTE 3.2 |
| [`stack/restricciones-servidor.md`](stack/restricciones-servidor.md) | Evidencia confirmada: PHP 7.4.3, sin pdo_sqlite/opcache/bcmath, diagnóstico directo |
| [`stack/entorno-local.md`](stack/entorno-local.md) | Qué hay en XAMPP, requisito de PHP 8.3 y por qué SQLite no reemplaza a MySQL |

## dominio/ — reglas del negocio REM

| Artículo | Qué contiene |
|---|---|
| [`dominio/validacion-rem.md`](dominio/validacion-rem.md) | Pipeline V0–V11, severidad, códigos `ENV-###`, paridad con *golden files* y presupuesto de ejecución |

## harness/ — cómo trabaja el agente

| Artículo | Qué contiene |
|---|---|
| [`harness/installation-plan.md`](harness/installation-plan.md) | Plan de harness aceptado (rsc 4.0.5, target OpenCode) |
| [`harness/decisions.md`](harness/decisions.md) | Decisiones del harness |
| `harness/user-profile.md` | Perfil local (nivel técnico, tipo de proyecto). No se commitea |

## Cómo crece esta wiki

1. Lo que llega sin clasificar entra a `02-DOCS/inbox/`.
2. Se consolida como artículo con frontmatter (`type`, `title`, `description`, `tags`, `timestamp`, `topic`).
3. Toda página nueva se enlaza desde este índice; toda decisión relevante se agrega a `sdd/decisions.md`.
4. Las fuentes originales no se borran: quedan en `02-DOCS/raw/` o en su ruta canónica.
