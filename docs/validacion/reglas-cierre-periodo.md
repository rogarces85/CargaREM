---
type: reglas
title: Reglas de cierre de período
description: Plazo mensual configurable, estados del período (abierto / en_revision / cerrado) y qué puede hacer cada rol en cada estado. Implementa D-004.
tags: [validacion, periodo, cierre, plazos, d4]
timestamp: 2026-11-11T15:00:00Z
topic: validacion
status: borrador_para_firma
source: sdd/decisions.md (D-004); MODULO_M7_PROFUNDIZADO.md §9.3; PLAN.md (DAT-04); Constitución principio 3
---

# Reglas de cierre de período

> **Tarea:** T-F0-07 · **Estado:** borrador para firma por la Unidad de Estadísticas.
> **Decisión que implementa:** **D-004** — día 10 configurable por período.
> **Para qué sirve:** define cuándo se puede subir, cuándo el portal deja de aceptar archivos y quién puede reabrirlo.

---

## 1. La decisión, en una frase

**El período se cierra el día 10 del mes siguiente, pero esa fecha se configura por período y el cierre no es automático: lo declara el administrador.**

El día 10 es un valor por defecto, no una constante. El MINSAL mueve fechas, y una fecha escondida en el código obliga a un despliegue para cambiarla.

---

## 2. Estados del período

| Estado | Qué significa | Quién puede subir |
|---|---|---|
| `abierto` | Plazo vigente. Carga normal. | Encargados (sus establecimientos) |
| `en_revision` | El plazo cerró para cargas nuevas. | Solo reenvíos de envíos `observado`. Un envío nuevo requiere al administrador. |
| `cerrado` | Nadie carga. | Nadie. Solo el administrador reabre, con motivo. |

`periodos.estado` guarda uno de esos tres valores. Valores y columnas en español (Constitución, principio 11).

---

## 3. Reglas

| ID | Regla | Verificación |
|---|---|---|
| **R-CIE-01** | Todo período nuevo se crea en `abierto`. | Estado inicial por defecto en la migración. |
| **R-CIE-02** | `fecha_limite` se propone como **día 10 del mes siguiente** al período, a las 23:59:59 de `America/Santiago`. El administrador puede cambiarla por período. | Se guarda como `DATE`; la hora se deriva. |
| **R-CIE-03** | **El sistema no cierra solo.** Alcanzada `fecha_limite`, el período sigue operativo y se marca visualmente como vencido; el paso a `en_revision` es una acción explícita del administrador. | Principio 3: el camino crítico no depende de cron. |
| **R-CIE-04** | En `en_revision` solo se aceptan reenvíos de envíos en `observado`. Un `recibido` nuevo exige habilitación del administrador. | Guarda de la transición `— → recibido`. |
| **R-CIE-05** | En `cerrado` no se acepta ninguna subida, de ningún rol. El administrador reabre a `en_revision` con **motivo obligatorio**. | La reapertura queda en auditoría. |
| **R-CIE-06** | Cerrar o reabrir un período **solo** lo hace el administrador. El revisor no cambia el estado del período. | Permiso separado en la matriz RBAC. |
| **R-CIE-07** | Todo cambio de estado del período se audita: quién, cuándo, estado anterior, estado nuevo y motivo. | `auditoria` (append-only, principio 21). |
| **R-CIE-08** | Los envíos ya `aprobado` **no cambian** cuando el período se cierra. Siguen pendientes de carga. | El cierre bloquea cargas nuevas, no invalida aprobaciones. |
| **R-CIE-09** | Un período `cerrado` **no impide** descargar el paquete aprobado ni consultar el histórico. | Cierre de carga ≠ cierre de lectura. |
| **R-CIE-10** | La completitud se calcula con el estado **vigente** del período. Un período cerrado se muestra con el último cálculo, no con uno nuevo. | Evita que un cierre cambie cifras ya publicadas. |
| **R-CIE-11** | Los recordatorios de plazo son opcionales y **nunca** bloquean ni cambian un estado. | Si no hay cron, el sistema funciona igual. |

---

## 4. Estructura

Tabla `periodos` (PLAN.md DAT-04):

| Columna | Tipo | Nulo | Descripción |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | no | PK |
| `anio` | SMALLINT | no | Año del período informado |
| `mes` | TINYINT | no | Mes del período informado (1–12) |
| `fecha_limite` | DATE | sí | Día de cierre; `NULL` = usa el valor por defecto |
| `estado` | VARCHAR(20) | no | `abierto` · `en_revision` · `cerrado` |
| `created_at` / `updated_at` | TIMESTAMP | sí | — |

Restricción de unicidad: `(anio, mes)`.

---

## 5. Qué ve el usuario

| Estado | Encargado | Revisor | Administrador |
|---|---|---|---|
| `abierto` | Puede subir y reenviar | Revisa y aprueba | Todo |
| `en_revision` | Solo corrige lo observado | Revisa y aprueba | Todo + habilita envíos nuevos |
| `cerrado` | Solo lectura | Solo lectura | Reabre con motivo |

La interfaz muestra siempre el período activo y su estado (Constitución, principio 24). Si el plazo venció y el período sigue `abierto`, el encargado ve el aviso antes de subir, no después.

---

## 6. Lo que debe confirmar negocio antes de firmar

| # | Pregunta | Por qué importa |
|---|---|---|
| 1 | ¿El día 10 rige para todos los Servicios de Salud o cada uno tiene su fecha? | Si varía por servicio, `fecha_limite` pasa a depender del establecimiento. |
| 2 | ¿Quién declara el cierre: la Unidad de Estadísticas central o cada referente? | Define el alcance del permiso `cerrar_periodo`. |
| 3 | ¿Un envío `observado` puede corregirse después del cierre? | Hoy la regla dice que sí (R-CIE-04). Si no, cambia el flujo de revisión. |
| 4 | ¿Cuántos días de gracia hay entre el cierre y la carga en CARGAS REM? | No afecta al portal, pero sí a la expectativa del revisor. |

---

## 7. Trazabilidad

| Elemento | Origen |
|---|---|
| D-004 | `02-DOCS/wiki/sdd/decisions.md` |
| §9.3 Cierre del período | `backup_documentacion/MODULO_M7_PROFUNDIZADO.md` |
| DAT-04 | `specs/PLAN.md` |
| RF-003, RF-010 | `specs/SPECS.md` |
| T-F0-07, T-F1-22, T-F2-03 | `specs/TASK.md` |
