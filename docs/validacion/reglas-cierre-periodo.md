---
type: reglas
title: Reglas de cierre de período
description: Plazo mensual (quinto día hábil del mes siguiente), calendario de feriados, estados del período (abierto / en_revision / cerrado) y qué puede hacer cada rol.
tags: [validacion, periodo, cierre, plazos, d4, calendario]
timestamp: 2026-11-14T15:30:00Z
topic: validacion
status: vigente
source: 02-DOCS/wiki/sdd/decisions.md (D-029); specs/MVP_V2.md §5.2
---

# Reglas de cierre de período

> **Decisión que implementa:** **D-029** (hereda D4): plazo = **quinto día hábil
> del mes siguiente**, con calendario de feriados de Chile.
> **Para qué sirve:** define hasta cuándo se recibe un período, cuándo se marca
> «fuera de plazo» y quién cierra o corrige el período.

---

## 1. La decisión, en una frase

**Un período se recibe hasta el final del quinto día hábil del mes siguiente,
según el calendario chileno; el cierre del período sigue siendo manual y lo
declara el administrador.**

El cálculo es una regla, no una fecha escondida: usa sábados, domingos y la
tabla `feriados`. El administrador puede fijar una fecha distinta por excepción
en un período concreto, y esa decisión queda registrada.

---

## 2. Estados del período

| Estado | Qué significa | Quién puede subir |
|---|---|---|
| `abierto` | Plazo vigente. Carga normal. | Encargados (sus establecimientos) |
| `en_revision` | El plazo cerró para cargas nuevas. | Solo reenvíos de envíos `observado`. Un envío nuevo requiere al administrador. |
| `cerrado` | Nadie carga. El administrador lo corrige con motivo. | Nadie. |

`periodos.estado` guarda uno de esos tres valores. Valores y columnas en español
(Constitución, principio 11).

---

## 3. Reglas

| ID | Regla | Verificación |
|---|---|---|
| **R-CIE-01** | Todo período nuevo se crea en `abierto`. | Estado inicial por defecto. |
| **R-CIE-02** | La fecha límite se calcula sola: **fin del quinto día hábil del mes siguiente**, a las 23:59:59 de `America/Santiago`. No cuentan sábados, domingos ni feriados. | `CalendarioService::fechaLimite()`; se guarda como `DATE`. |
| **R-CIE-03** | El administrador puede fijar una fecha distinta por excepción (`fecha_limite_manual = true`). El sistema no la vuelve a calcular. | Columna `fecha_limite_manual`; el override se ve en el calendario. |
| **R-CIE-04** | **El sistema no cierra solo.** Alcanzada la fecha, el período sigue operativo y el envío se marca «fuera de plazo» al llegar a revisión; el paso a `en_revision` es una acción explícita del administrador. | Principio 3: el camino crítico no depende de cron. |
| **R-CIE-05** | En `en_revision` solo se aceptan reenvíos de envíos en `observado`. Un `recibido` nuevo exige habilitación del administrador. | Guarda de la transición `— → recibido`. |
| **R-CIE-06** | En `cerrado` no se acepta ninguna subida, de ningún rol. El administrador lo corrige con **motivo obligatorio** y queda auditado. | Permiso `periodos.gestionar`. |
| **R-CIE-07** | Todo cambio de período (fecha, estado) se audita: quién, cuándo y qué cambió. | `auditoria` (append-only, principio 21). |
| **R-CIE-08** | Los envíos ya `aprobado` **no cambian** cuando el período se cierra. Siguen consultables y descargables. | El cierre bloquea cargas nuevas, no invalida aprobaciones. |
| **R-CIE-09** | Un período `cerrado` **no impide** descargar el original, el reporte ni consultar la completitud. | Cierre de carga ≠ cierre de lectura. |
| **R-CIE-10** | La completitud se calcula con el estado **vigente** del período. | Evita que un cierre cambie cifras ya publicadas. |
| **R-CIE-11** | Los recordatorios de plazo son opcionales y **nunca** bloquean ni cambian un estado. | Si no hay cron, el sistema funciona igual. |
| **R-CIE-12** | Los feriados se cargan por año en la tabla `feriados` (nacionales, y regionales si aplican). Si un año no tiene feriados, el plazo se calcula solo con fines de semana y la pantalla de calendario lo advierte. | Pantalla **Calendario de plazos**; `php artisan rem:calendario <año>`. |

---

## 4. Estructura

Tabla `periodos`:

| Columna | Tipo | Nulo | Descripción |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | no | PK |
| `anio` | SMALLINT | no | Año del período informado |
| `mes` | TINYINT | no | Mes del período informado (1–12) |
| `fecha_limite` | DATE | sí | Fin del quinto día hábil del mes siguiente, o la fecha manual |
| `fecha_limite_manual` | BOOLEAN | no | `true` = fecha fijada por excepción |
| `estado` | VARCHAR(20) | no | `abierto` · `en_revision` · `cerrado` |

Restricción de unicidad: `(anio, mes)`.

Tabla `feriados`:

| Columna | Tipo | Nulo | Descripción |
|---|---|---|---|
| `fecha` | DATE | no | Única |
| `nombre` | VARCHAR(120) | no | Nombre del feriado |
| `ambito` | VARCHAR(20) | no | `nacional` · `regional` |
| `region` | VARCHAR(80) | sí | Solo para regionales |

Al crear el envío se copia `periodos.fecha_limite` a `envios.fecha_limite`: un
cambio posterior del período no reescribe envíos ya creados.

---

## 5. Qué ve el usuario

| Estado | Encargado | Revisor | Administrador |
|---|---|---|---|
| `abierto` | Puede subir y reenviar; ve si está fuera de plazo | Revisa y aprueba | Todo + calendario y feriados |
| `en_revision` | Solo corrige lo observado | Revisa y aprueba | Todo + habilita envíos nuevos |
| `cerrado` | Solo lectura | Solo lectura | Corrige con motivo |

La interfaz muestra siempre el período activo y su estado (Constitución,
principio 24). El administrador ve los doce plazos del año en
**Calendario de plazos**, con la lista de feriados usados y un botón para
crear o corregir los períodos del año.

---

## 6. Lo que debe confirmar negocio

| # | Pregunta | Por qué importa |
|---|---|---|
| 1 | ¿El plazo de 5 días hábiles rige para todos los establecimientos o hay excepciones por convenio? | Si varía, se usa el override manual por período. |
| 2 | ¿Quién declara el cierre: la Unidad de Estadísticas central o cada referente? | Define el alcance de `periodos.gestionar`. |
| 3 | ¿Un envío `observado` puede corregirse después del cierre? | Hoy la regla dice que sí (R-CIE-05). Si no, cambia el flujo de revisión. |
| 4 | ¿Se cargan feriados regionales de Los Lagos además de los nacionales? | La tabla los admite; hoy se siembran solo los nacionales. |

---

## 7. Trazabilidad

| Elemento | Origen |
|---|---|
| D-029 (D4 resuelta) | `02-DOCS/wiki/sdd/decisions.md` |
| Regla del plazo | `specs/MVP_V2.md` §5.2 |
| Feriados 2026 | `FeriadosSeeder` (Ley 19.973) |
| Cálculo | `app/Services/CalendarioService.php` |
