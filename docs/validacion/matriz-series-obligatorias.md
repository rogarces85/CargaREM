---
type: matriz
title: Matriz de series obligatorias por establecimiento
description: Regla que decide qué series REM debe entregar cada establecimiento en cada período, derivada del histórico con override manual y vigencia. Implementa D-005 y D-006.
tags: [validacion, series, completitud, d5, d6, sax, env]
timestamp: 2026-11-11T15:00:00Z
topic: validacion
status: borrador_para_firma
source: sdd/decisions.md (D-005, D-006); MODULO_M7_PROFUNDIZADO.md §9.1; PLAN.md (DAT-06)
---

# Matriz de series obligatorias por establecimiento

> **Tarea:** T-F0-06 · **Estado:** borrador para firma por la Unidad de Estadísticas.
> **Decisiones que implementa:** **D-005** (histórico + *override* manual) y **D-006** (SAX fuera del MVP).
> **Para qué sirve:** sin esta matriz el portal no puede decir «falta la serie BS». Es la regla que alimenta la validación de completitud **V11** y el semáforo del tablero.

---

## 1. La decisión, en una frase

**Cada establecimiento debe entregar las series que entregó en los últimos 12 meses, ajustadas a mano cuando corresponda, y el ajuste tiene fecha de vigencia.**

Se descartaron dos alternativas: exigir todas las series a todos (genera faltantes falsos) y exigirlas por tipo de establecimiento (no hay en la base un tipo fiable para eso).

---

## 2. Las series del REM

| Código | Nombre | Vigente |
|---|---|---|
| `A` | *se importa del catálogo* | ✅ |
| `BS` | *se importa del catálogo* | ✅ |
| `BM` | *se importa del catálogo* | ✅ |
| `D` | *se importa del catálogo* | ✅ |
| `P` | *se importa del catálogo* | ✅ |
| `F` | *se importa del catálogo* | ✅ |
| `SAX` | Serie local (Anexo, Servicio de Salud Osorno) | 🟡 fuera del MVP (D-006) |

> El nombre no se fija en este documento: viene del catálogo importado de `Global.mdb` (tabla `series`). Si el catálogo cambia, cambia aquí sin tocar código.

---

## 3. Reglas

| ID | Regla | Verificación |
|---|---|---|
| **R-SER-01** | **Base histórica.** Una serie es obligatoria para un establecimiento si ese establecimiento tiene al menos un mes cargado con esa serie en los **12 meses** anteriores al período consultado. | Consulta sobre los datos históricos de CARGAS REM; se ejecuta en la importación de catálogo, no en cada subida. |
| **R-SER-02** | ***Override* manual.** El administrador puede marcar o desmarcar una serie para un establecimiento. Prevalece sobre R-SER-01. | Fila en `establecimiento_serie` con `obligatoria = 1` o `0`. |
| **R-SER-03** | **Vigencia.** Todo *override* lleva `vigente_desde` y, opcionalmente, `vigente_hasta`. La vigencia se evalúa contra el **último día del período** informado, no contra la fecha de la subida. | Un envío de septiembre se evalúa con la vigencia que regía el 30 de septiembre. |
| **R-SER-04** | **No se reinterpreta el pasado.** Cambiar la exigencia hoy no cambia la completitud de un período ya cerrado. | La evaluación usa la vigencia del período, no la actual. |
| **R-SER-05** | **Sin fila, no obliga.** Si un establecimiento no tiene ninguna fila para una serie, esa serie **no** se le exige. El silencio nunca produce faltante. | Un establecimiento nuevo no arrastra faltantes por series que nunca envió. |
| **R-SER-06** | **Serie desmarcada.** `obligatoria = 0` significa «se acepta el archivo, pero no cuenta para la completitud». | El archivo se valida igual; no bloquea el envío. |
| **R-SER-07** | **Serie inactiva.** Una serie con `series.activo = 0` no se espera de nadie y no se ofrece en la UI. | `SAX` entra así en el MVP. |

---

## 4. Estructura

Tabla `establecimiento_serie` (PLAN.md DAT-06):

| Columna | Tipo | Nulo | Descripción |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | no | PK |
| `establecimiento_id` | BIGINT UNSIGNED | no | FK a `establecimientos` |
| `serie_id` | BIGINT UNSIGNED | no | FK a `series` |
| `obligatoria` | TINYINT(1) | no | 1 = se exige · 0 = se acepta pero no cuenta |
| `vigente_desde` | DATE | sí | Desde qué fecha rige el override |
| `vigente_hasta` | DATE | sí | Hasta qué fecha; `NULL` = sin término |

Ejemplo:

```text
establecimiento_id=42  serie_id=2 (BS)  obligatoria=1  vigente_desde=2026-01-01  vigente_hasta=NULL
establecimiento_id=42  serie_id=6 (F)   obligatoria=0  vigente_desde=2026-01-01  vigente_hasta=2026-12-31
```

---

## 5. Efecto en la validación (V11)

Una serie obligatoria **sin archivo vigente** en el período produce la observación de completitud `ENV-###` correspondiente y deja el envío en `con_errores`. El detalle de severidad está en [`politica-severidad.md`](politica-severidad.md) y el catálogo en [`catalogo-errores-env.md`](catalogo-errores-env.md).

La completitud **no** se calcula por establecimiento aislado: un envío puede estar `validado` y el Servicio de Salud seguir incompleto porque otro establecimiento no ha subido nada. Son dos vistas distintas:

- **Envío completo:** tiene todas *sus* series obligatorias.
- **Servicio completo:** todos sus establecimientos tienen el envío completo.

---

## 6. Datos de prueba (L1)

El *seeder* `L1MinimumSeeder` (T-F1-30) carga **7 series, todas obligatorias para los 7 establecimientos**. Es dato de prueba para que el flujo funcione, **no** es la matriz real. La matriz real se carga con el primer *override* del administrador.

---

## 7. SAX — por qué queda fuera (D-006)

SAX tiene plantilla y datos propios, y hoy no está documentada (pendiente **P12** de `informe.md`). Incluirla en el MVP obliga a inventar su validación, que es exactamente lo que la Constitución prohíbe en el principio 10 (paridad con *golden files*).

Mientras esté fuera:

- Se registra en `series` con `activo = 0`.
- No se espera de ningún establecimiento.
- Si alguien sube un archivo SAX, se acepta como archivo **no obligatorio**: no bloquea, no suma a la completitud y se marca para revisión humana.

Documentarla y habilitarla es una fase posterior, con sus propios *golden files*.

---

## 8. Lo que debe confirmar negocio antes de firmar

| # | Pregunta | Por qué importa |
|---|---|---|
| 1 | ¿Algún tipo de establecimiento (posta, consultorio) no envía nunca alguna serie, aunque el histórico diga lo contrario? | Un *override* mal puesto genera faltantes falsos. |
| 2 | ¿La ventana de 12 meses es la correcta, o debería ser «el último mes cargado»? | Cambia cuántas series se exigen a un establecimiento que dejó de enviar. |
| 3 | En el primer período de operación **no hay histórico**. ¿Se exige todo, nada, o se importa una matriz inicial? | Define el arranque. Propuesta: matriz inicial cargada por el administrador antes del primer cierre. |

---

## 9. Trazabilidad

| Elemento | Origen |
|---|---|
| D-005 | `02-DOCS/wiki/sdd/decisions.md` |
| D-006 | `02-DOCS/wiki/sdd/decisions.md` |
| §9.1 Series requeridas | `backup_documentacion/MODULO_M7_PROFUNDIZADO.md` |
| DAT-06 | `specs/PLAN.md` |
| T-F0-06, T-F1-23, T-F2-22, T-F4-01 | `specs/TASK.md` |
