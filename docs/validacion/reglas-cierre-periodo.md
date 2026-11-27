---
type: reglas
title: Reglas de cierre de período y aperturas de plazo
description: Plazo mensual (quinto día hábil del mes siguiente), calendario de feriados, estados del período (abierto / en_revision / cerrado), aperturas de fuera de plazo y qué puede hacer cada rol.
tags: [validacion, periodo, cierre, plazos, d4, calendario, aperturas]
timestamp: 2026-11-26T12:00:00Z
topic: validacion
status: vigente
source: 02-DOCS/wiki/sdd/decisions.md (D-029, D-044 y D-046); specs/MVP_V3.md §8
---

# Reglas de cierre de período y aperturas de plazo

> **Decisiones que implementa:** **D-029** (hereda D4): plazo = **quinto día hábil
> del mes siguiente**, con calendario de feriados de Chile; **D-046**: el
> calendario se genera solo y se verifica; **D-044**: las excepciones son
> aperturas con motivo y vencimiento.
> **Para qué sirve:** define hasta cuándo se recibe un período, cuándo se marca
> «fuera de plazo», quién cierra o corrige el período y cómo se autoriza una
> carga tardía.

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

### Generación automática (D-046)

| ID | Regla | Verificación |
|---|---|---|
| **R-CIE-13** | El período que falta **se crea al pedirlo**, con su fecha calculada: el alta de un envío no depende de que el administrador haya creado el período. | `EnvioService::periodoDe()`; `test_crea_el_periodo_que_falta_con_su_fecha_calculada` |
| **R-CIE-14** | Los doce períodos de un año se generan con `php artisan rem:periodos:generar` o desde el calendario del administrador. La operación es idempotente y **no toca estados**: crear no es cerrar. | `App\Support\Plazos::generar()`; `CalendarioAutomaticoTest` |
| **R-CIE-15** | `php artisan rem:calendario --verificar` compara lo guardado con lo calculado y devuelve código ≠ 0 si hay diferencias. Una fecha manual no cuenta como diferencia. | `Plazos::diferencias()`; test del comando |
| **R-CIE-16** | Los feriados se siembran por año desde una lista verificada (2026 y 2027) con `php artisan rem:feriados:cargar`. Un año nuevo se agrega al seeder o por la pantalla de Feriados. | `FeriadosSeeder::FERIADOS` |
| **R-CIE-17** | Las tareas programadas (`routes/console.php`) mantienen el calendario al día **si hay cron**. Sin cron el portal funciona igual, porque la generación es perezosa. | Constitución, principio 3 |

### Aperturas de fuera de plazo (D-044)

| ID | Regla | Verificación |
|---|---|---|
| **R-CIE-18** | Un período `cerrado` o `en_revision` solo acepta cargas de un establecimiento con **apertura vigente**. La apertura no cambia el estado del período. | `AperturaService::permite()`; `AperturaFueraPlazoTest` |
| **R-CIE-19** | Solo el **administrador** resuelve una solicitud, abre por su cuenta o anula una apertura. El encargado solo pide. | `AperturaPolicy`; test de «solo el administrador resuelve» |
| **R-CIE-20** | El motivo de la solicitud (≥ 15 caracteres) y el de la resolución son obligatorios; todo queda auditado. | `apertura.solicitada`, `apertura.aprobada`, `apertura.rechazada`, `apertura.abierta`, `apertura.anulada` |
| **R-CIE-21** | La ventana vale hasta su `vigente_hasta`. Vencida, el bloqueo vuelve sin intervención; `rem:aperturas:cerrar` solo deja el estado explícito. | `Apertura::estaVigente()` |
| **R-CIE-22** | Una apertura para «todos» vale para los establecimientos activos del Servicio, no solo para el que pidió. | `AperturaService::vigentePara()` |
| **R-CIE-23** | El envío cargado con una apertura guarda su `apertura_id`. La marca `fuera_de_plazo` **se conserva**: es un hecho, no una sanción. | `envios.apertura_id`; `Envio::conApertura()` |

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
| 1 | ¿El plazo de 5 días hábiles rige para todos los establecimientos o hay excepciones por convenio? | ✅ **Resuelto (v3):** toda excepción pasa por una apertura de plazo con motivo (D-044). |
| 2 | ¿Quién declara el cierre: la Unidad de Estadísticas central o cada referente? | Define el alcance de `periodos.gestionar`. |
| 3 | ¿Un envío `observado` puede corregirse después del cierre? | Hoy la regla dice que sí (R-CIE-05) y la corrección de un observado no necesita apertura. |
| 4 | ¿Se cargan feriados regionales de Los Lagos además de los nacionales? | La tabla los admite; hoy se siembran solo los nacionales (16 en 2026, 17 en 2027). |
| 5 | ¿Cuánto dura una ventana de apertura típica? | El administrador la fija; el sistema no impone un máximo. |

---

## 7. Trazabilidad

| Elemento | Origen |
|---|---|
| D-029 (D4 resuelta) | `02-DOCS/wiki/sdd/decisions.md` |
| D-044 (aperturas) y D-046 (generación) | `02-DOCS/wiki/sdd/decisions.md` |
| Regla del plazo | `specs/MVP_V3.md` §8 |
| Feriados 2026 y 2027 | `FeriadosSeeder` (Ley 19.973, 19.668 y 20.983), contrastados con [feriados.cl](https://www.feriados.cl/) |
| Cálculo | `app/Services/CalendarioService.php` |
| Generación | `app/Support/Plazos.php` |
| Aperturas | `app/Services/AperturaService.php` |
| Tests | `CalendarioAutomaticoTest`, `PlazoTest`, `AperturaFueraPlazoTest`, `AdminCalendarioTest` |
