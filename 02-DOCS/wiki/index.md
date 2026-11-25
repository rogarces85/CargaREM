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

## Qué documento manda

Cuando dos documentos se contradicen, gana el primero de esta lista. Es la regla de desempate, no un orden de lectura.

| # | Documento | Manda sobre |
|---|---|---|
| 1 | [`sdd/constitution.md`](sdd/constitution.md) — v5.0.0 | Todo. Stack, calidad, convenciones, UI |
| 2 | [`specs/MVP_V2.md`](../../specs/MVP_V2.md) | Alcance, requisitos, criterios de aceptación del portal |
| 3 | [`sdd/decisions.md`](sdd/decisions.md) | El registro de por qué se decidió cada cosa |
| 4 | `portal/` | Lo que el sistema hace de verdad. Si el código contradice estos documentos, el código está mal |
| 5 | `informe.md` y `backup_documentacion/MODULO_M*.md` | Cómo funciona el sistema de escritorio Cargas REM |
| 6 | `docs/` y `02-DOCS/wiki/` | Guías, manuales y síntesis. Nunca mandan: enlazan |

**Documentos superados.** Se conservan por trazabilidad y **no se citan como fuente**: `stack/decision-stack.md` (C-010), `stack/opciones-stack.md` (investigación de C-009), `stack/restricciones-servidor.md` (diagnóstico previo a C-015), `stack_modulo_envio.md` (propuesta previa a C-015) y `docs/stack/prototipo-laravel-12.md` (prototipo ya promovido a C-015). Cada uno abre con su estado.

---

## Documentos canónicos (raíz del repositorio)

| Documento | Qué contiene |
|---|---|
| [`informe.md`](../../informe.md) | Informe técnico de ValidadorRem: módulos M1–M6, reglas de negocio, flujos, §2.10 del módulo de envío, pendientes P1–P13 |
| [`stack_modulo_envio.md`](../../stack_modulo_envio.md) | **Superado (C-015).** Restricciones del servidor y propuesta de stack previa al cierre de D-STACK. Se conserva como historial |
| [`backup_documentacion/MODULO_M7_PROFUNDIZADO.md`](../../backup_documentacion/MODULO_M7_PROFUNDIZADO.md) | Diseño funcional y técnico del portal M7: dominio, estados, pipeline, seguridad, pruebas |
| [`docs/FLUJO_DATOS_INTEGRAL_SISTEMA.md`](../../docs/FLUJO_DATOS_INTEGRAL_SISTEMA.md) | Flujo de datos de punta a punta |
| [`REVIEW.md`](../../REVIEW.md) | Qué mira el revisor y qué exige una persona antes de fusionar |

## specs/ — especificación operativa del MVP

| Documento | Qué contiene |
|---|---|
| [`MVP_V2.md`](../../specs/MVP_V2.md) | **Alcance vigente v2.0 (14-11-2026):** actores, requisitos, reglas, estados, permisos y criterios de aceptación |
| [`_historico/`](../../specs/_historico/) | Especificaciones anteriores (v1 y el corte del 13-11). Trazabilidad, no fuente |

## sdd/ — reglas y decisiones

| Artículo | Qué contiene |
|---|---|
| [`sdd/constitution.md`](sdd/constitution.md) | Constitución **v5.0.0**: canon Laravel **12.72** + PHP **8.2** + **Tailwind CSS v4** (C-019), 31 principios y Definition of Done. |
| [`sdd/decisions.md`](sdd/decisions.md) | Registro append-only de decisiones: D-STACK (C-015), rediseño UI (C-019), alcance v2 (C-017), alineación v2.0 (C-018) y D5/D6 abiertas |

## stack/ — entorno y decisión **cerrada (C-015)**

| Artículo | Qué contiene |
|---|---|
| [`stack/opciones-stack.md`](stack/opciones-stack.md) | Investigación de 5 opciones (Opción A requería PHP 8.3, hoy inválida). **Superado por C-015** |
| [`stack/decision-stack.md`](stack/decision-stack.md) | **Superado.** Conserva la evidencia de C-010 (Laravel 8.83 / PHP 7.4.3 / AdminLTE 3.2) y su reapertura. El canon vigente está en la Constitución §0 |
| [`stack/restricciones-servidor.md`](stack/restricciones-servidor.md) | **Evidencia previa a C-015:** PHP 7.4.3 en el vhost principal, sin pdo_sqlite/opcache/bcmath. Se conserva porque explica por qué se pidió PHP 8.2 |
| [`stack/entorno-local.md`](stack/entorno-local.md) | **Entorno local verificado (2026-11-11):** XAMPP 8.2.12, PHP 8.2.12, MariaDB 10.4.32, límites reales, estrategia en dos fases y las diferencias que rompen en producción |
| [`docs/stack/prototipo-laravel-12.md`](../../docs/stack/prototipo-laravel-12.md) | **Prototipo ya promovido:** cero avisos, dependencias modernas y el diff estructural contra Laravel 8. Es la prueba que sostuvo C-015 |

## dominio/ — reglas del negocio REM

| Artículo | Qué contiene |
|---|---|
| [`dominio/validacion-rem.md`](dominio/validacion-rem.md) | Pipeline V0–V11, severidad, códigos `ENV-###`, paridad con *golden files* y presupuesto de ejecución |
| [`../../docs/validacion/inconsistencias-documentacion-codigo.md`](../../docs/validacion/inconsistencias-documentacion-codigo.md) | **Auditoría:** qué afirmaban los documentos y el código no cumplía. B y C corregidos el 25-11; A (derivar del histórico) sigue abierta |

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
| [`harness/installation-plan.md`](harness/installation-plan.md) | Plan de harness aceptado (rsc 4.0.5, target OpenCode; hoy corre 4.1.9 con targets `deepseek` y `opencode`) |
| [`harness/decisions.md`](harness/decisions.md) | Decisiones del harness |
| `harness/user-profile.md` | Perfil local (nivel técnico, tipo de proyecto). No se commitea |

## ftd/ — trabajo en curso

| Documento | Qué contiene |
|---|---|
| [`../feature-cierre-documentacion.md`](../feature-cierre-documentacion.md) | **Cerrada:** qué se corrigió para que los documentos coincidan con el código (2026-11-24) |
| [`../feature-matriz-series-vigencia.md`](../feature-matriz-series-vigencia.md) | **Cerrada:** la matriz de series en un solo lugar, con la ventana de vigencia real (2026-11-25) |

## Cómo crece esta wiki

1. **Lo operativo va a [`specs/`](../../specs/), no a la wiki.** Especificación, plan y tareas son la fuente del trabajo.
2. **Lo nuevo de conocimiento entra aquí** como artículo con frontmatter (`type`, `title`, `description`, `tags`, `timestamp`, `topic`).
3. Toda página nueva se enlaza desde este índice.
4. Toda decisión relevante se agrega a [`sdd/decisions.md`](sdd/decisions.md). Es append-only: no se reescribe una entrada, se agrega la que la reemplaza.
5. Todo cambio de la wiki se anota con una línea en [`log.md`](log.md).
6. **Las fuentes originales no se copian aquí:** se enlazan desde su ruta canónica. La wiki sintetiza, no duplica (Constitución, principio 27).

## docs/ — guías operativas

| Documento | Qué contiene |
|---|---|
| [`docs/reestructuracion/matriz-funcionalidades.md`](../../docs/reestructuracion/matriz-funcionalidades.md) | Matriz funcional del alcance v2.0: qué queda, qué se adapta y qué se retira |
| [`docs/operacion/como-probar.md`](../../docs/operacion/como-probar.md) | Puesta en marcha desde cero y cómo validar archivos REM reales |
| [`docs/operacion/paridad.md`](../../docs/operacion/paridad.md) | Cómo se mide la paridad contra el ValidadorRem y qué formato tiene el resultado esperado |
| [`docs/uat/guion-uat.md`](../../docs/uat/guion-uat.md) | Escenarios de aceptación para validar con usuarios reales |
| [`docs/uat/resultado-2026-11-14.md`](../../docs/uat/resultado-2026-11-14.md) | Recorrido técnico encargado → revisor → admin con el catálogo sembrado, con incidencias y pendientes |
| [`docs/manual/README.md`](../../docs/manual/README.md) | **Puerta de entrada del manual:** qué leer según el rol |
| [`docs/manual/00-como-funciona-el-sistema.md`](../../docs/manual/00-como-funciona-el-sistema.md) | Mapa de módulos, ciclo de vida del envío y un mes completo con los tres roles |
| [`docs/manual/guia-encargado-establecimiento.md`](../../docs/manual/guia-encargado-establecimiento.md) | Guía de uso del rol encargado |
| [`docs/manual/guia-revisor-servicio-salud.md`](../../docs/manual/guia-revisor-servicio-salud.md) | Guía de uso del rol revisor |
| [`docs/manual/guia-administrador.md`](../../docs/manual/guia-administrador.md) | Guía de uso del rol administrador |
| [`docs/manual/analisis-simplificacion.md`](../../docs/manual/analisis-simplificacion.md) | Dónde el flujo se traba hoy, con archivo:línea y qué se puede simplificar |
| [`docs/manual/manual-usuario.md`](../../docs/manual/manual-usuario.md) | Manual general del portal (anterior a las guías por rol) |
| [`docs/manual/manual-operacion.md`](../../docs/manual/manual-operacion.md) | Despliegue, respaldos, registros y problemas comunes |
| [`docs/decisiones/pendientes-de-firma.md`](../../docs/decisiones/pendientes-de-firma.md) | Decisiones abiertas con recomendación: D5 series y D6 SAX. D1 (identidad) y D4 (plazo) quedaron resueltas el 14-11 |
| [`docs/entrega/estado-del-proyecto.md`](../../docs/entrega/estado-del-proyecto.md) | Resumen de entrega: qué está hecho, qué falta, riesgos vivos y números del sistema |
| [`docs/operacion/matriz-paridad-catalogo.md`](../../docs/operacion/matriz-paridad-catalogo.md) | Contraste de las reglas implementadas contra el catálogo documentado, con las desviaciones justificadas |
