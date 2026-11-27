---
type: dominio
title: Serie P semestral
description: Por qué la serie P se informa solo en los períodos de junio y diciembre, cómo se modela y qué lectores dependen de la regla.
tags: [dominio, series, rem, serie-p, semestral, matriz]
timestamp: 2026-11-26T12:00:00Z
topic: dominio
---

# Serie P semestral

> **Decisión:** D-045. **Requisito:** RF-045 (`specs/MVP_V3.md`).
> **Implementación:** `App\Dominio\MatrizSeries::aplicarVigencia()` y
> `series.meses_exigidos`.

## 1. La regla, en una frase

La serie P no es mensual: se informa **dos veces al año**, con los cortes
semestrales. La estadística de **junio** se entrega con el plazo de julio y la
de **diciembre** con el plazo de enero.

En los demás meses:

- **no se exige** (V11 no la reclama y la matriz de completitud no la muestra);
- **no cuenta como faltante** para aprobar un envío;
- **se acepta** si el establecimiento la sube igual. La regla es de exigencia,
  no de prohibición.

## 2. Cómo se modela

`series.meses_exigidos` es un JSON nullable:

| Valor | Significado |
|---|---|
| `null` | Se informa todos los meses (A, BS, BM, D, F) |
| `[6, 12]` | Se informa solo en los períodos 06 y 12 (P) |

Se descartaron dos alternativas:

- **Una tabla de calendario por serie.** La regla es del catálogo, no del
  período: «la P se informa en 6 y 12» no cambia de un año a otro.
- **Un arreglo vacío para «ningún mes».** Si una serie no se informa nunca, la
  respuesta correcta es desactivarla (`series.activo = false`), no dejarla
  exigida y sin meses.

## 3. Dónde se aplica (y por qué importa)

La regla vive en **un solo lugar**. Cuatro lectores dependen de ella y los
cuatro tienen que decir lo mismo:

| Lector | Qué hace con la regla |
|---|---|
| `ReglaV11Completitud` | No reclama la P fuera de su mes |
| `EstadoEnvioService::verificarGuardas` | No exige la P para aprobar |
| `EnvioController::show` | Lista solo las series exigidas de ese período |
| `CompletitudService` | No pinta la columna de la P fuera de su mes |

**El mes se toma del período**, no de la fecha en que corre la consulta. Un
envío de junio se juzga con junio aunque la pantalla se abra en noviembre. Es la
misma lección de R-SER-03: la fecha de la consulta no decide el contenido del
período.

> Este es el segundo caso del mismo fallo. El primero fue la ventana de
> vigencia, escrita en cuatro lugares que no coincidían
> (`02-DOCS/feature-matriz-series-vigencia.md`). La respuesta fue la misma:
> una sola clase de dominio y ningún lector con su propia copia.

## 4. Trazabilidad

| Elemento | Dónde |
|---|---|
| Migración | `portal/database/migrations/2026_11_26_100002_series_meses_exigidos.php` |
| Regla | `portal/app/Dominio/MatrizSeries.php` |
| Lectura del dato | `portal/app/Models/Serie.php` (`seInformaEn`, `periodicidadLegible`) |
| Tests | `portal/tests/Feature/SeriePSemestralTest.php`, `CalendarioAutomaticoTest.php` |
| Decisión | `02-DOCS/wiki/sdd/decisions.md` (D-045) |
