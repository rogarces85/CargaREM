---
type: auditoria
title: Contradicciones entre la documentación y el código
description: Lo que los documentos afirman y el código no cumple. Verificado con archivo y línea el 2026-11-24; B y C corregidos el 2026-11-25, A sigue abierto.
tags: [auditoria, documentacion, contradiccion, matriz, series, vigencia]
timestamp: 2026-11-25T10:35:00Z
topic: validacion
status: parcialmente_resuelto
---

# Contradicciones entre la documentación y el código

> **Qué es esto.** Al cerrar la documentación técnica se comparó cada afirmación de los documentos con
> el código que debería cumplirla. Aparecieron tres afirmaciones que **no se cumplían**.
>
> **Estado al 2026-11-25.** Las contradicciones **B** (ventana de vigencia) y **C** (tests ausentes)
> están **corregidas** en la rama `feature/matriz-series-vigencia`. La **A** (derivar del histórico)
> sigue abierta, con una decisión tomada para una parte: al importar se exige todo.
>
> **Regla aplicada.** La documentación no se rebajó al código para hacerla «coincidir». Se registra la
> diferencia y se avisa en el documento que la afirma.
>
> **Verificado originalmente el 2026-11-24** sobre `main` (`0964138`) con 261 tests en verde. Que la
> suite pasara no contradecía nada de esto: eran afirmaciones sin test.

---

## 1. La matriz de series no se deriva del histórico — **PARCIAL**

| | |
|---|---|
| **Lo que dice el documento** | [`matriz-series-obligatorias.md`](matriz-series-obligatorias.md) §1 y R-SER-01: «Cada establecimiento debe entregar las series que entregó en los últimos **12 meses**, ajustadas a mano cuando corresponda». La fuente del frontmatter es «D-005 (histórico + override manual)». |
| **Lo que hacía el código** | Nadie derivaba nada del histórico. `ImportadorCatalogo` **no escribía** `establecimiento_serie`: `importarEstablecimientos` creaba el establecimiento y terminaba. Las dos únicas escrituras eran `L1MinimumSeeder::run` —las seis series activas para los 7 establecimientos de prueba— y el formulario `Admin\MatrizController::update`. |
| **Consecuencia que tenía** | Los **86 establecimientos del Servicio Osorno** del catálogo real se importaban **sin ninguna fila** de matriz. Por R-SER-05 («sin fila, no obliga») no se les exigía nada: V11 devolvía solo una **advertencia** y el envío podía avanzar sin completitud verificada. |
| **Qué se corrigió** | `ImportadorCatalogo::crearMatrizInicial()` exige **todas las series activas** a cada establecimiento, al importar. Decisión del usuario (2026-11-25): «importar los 86 y aplicar las series para todos, después las edito». |
| **Qué sigue abierto** | La derivación **desde el histórico de 12 meses** no existe. El criterio es «todo el mundo debe todo» hasta que el administrador ajuste. Si se quiere R-SER-01 de verdad, hace falta el histórico de meses cargados, que el portal no tiene. |

## 2. La ventana de vigencia se aplica en dos lugares de cuatro — **CORREGIDO**

`establecimiento_serie` tiene `vigente_desde` y `vigente_hasta`. Había cuatro consultas que decidían qué
series se exigían, y solo dos filtraban por vigencia:

| Lugar | Antes | Ahora |
|---|---|---|
| `Validacion/Reglas/ReglaV11Completitud.php` | Sí, contra `now()` | Sí, contra el cierre del período |
| `Services/CompletitudService.php` | Sí, contra `now()` | Sí, contra el cierre del período |
| `Services/EstadoEnvioService.php` | **No** | Sí, contra el cierre del período |
| `Http/Controllers/EnvioController.php` | **No** | Sí, contra el cierre del período |

**El fallo que causaba.** Una serie cuya vigencia terminó dejaba de exigirse en V11 y desaparecía de la
matriz, pero **seguía bloqueando la aprobación**: `verificarGuardas()` la contaba como faltante y lanzaba
`aprobacionIncompleta`. El revisor veía un envío imposible de aprobar y una matriz que decía que estaba
completo. Un bloqueo sin salida: el encargado tampoco podía subir esa serie si ya no se le pedía en la
pantalla.

**Segunda desviación, corregida también.** R-SER-03 exige evaluar la vigencia «contra el **último día
del período** informado, no contra la fecha de la subida». Las dos consultas que filtraban usaban
`now()`: un período cerrado se evaluaba con la vigencia de hoy, que es lo que R-SER-04 prohíbe.

**Cómo se corrigió.** La condición vive en un solo lugar, `App\Dominio\MatrizSeries`, y las cuatro
consultas la usan. El ancla es `MatrizSeries::cierreDelPeriodo()`, el último día del mes del período.

## 3. Las reglas de vigencia no tenían test — **CORREGIDO**

`grep vigente_hasta` sobre `portal/tests/` daba **0 coincidencias**. `MatrizTest` comprobaba que
`vigente_desde` se **guardara**, no que se **aplicara**.

Ahora hay **23 tests nuevos** (261 → 284 al cerrar la corrección):

| Dónde | Qué fija |
|---|---|
| `tests/Feature/MatrizSeriesTest.php` (10) | La condición en el dominio: sin fila, desmarcada, inactiva, orden, `vigente_hasta`, `vigente_desde`, sin fechas, y el ancla contra el cierre del período |
| `tests/Feature/AprobacionGuardasTest.php` (+2) | Una serie vencida **no** bloquea la aprobación; una vigente **sí** |
| `tests/Feature/ValidadorV11Test.php` (+3) | V11 no reclama una serie vencida ni una que rige desde el futuro |
| `tests/Feature/CompletitudTest.php` (+2) | La matriz no pinta en rojo una serie vencida |
| `tests/Feature/EnvioWebTest.php` (+2) | La pantalla del envío lista exactamente las exigidas |
| `tests/Feature/CatalogoSeederTest.php` (+3) | La importación crea la matriz y no pisa una editada |
| `tests/Feature/L1SeederTest.php` (+2) | La siembra no pisa nombres reales ni la matriz editada |

El test de la aprobación se verificó **en contra**: se revirtió el arreglo y el test falló con
`APP-ENV-011`. No es un test vacío.

---

## 4. Un cuarto fallo, encontrado al corregir los tres

`L1MinimumSeeder` usaba `updateOrCreate` sobre seis códigos que **existen en el catálogo real**
(`123100` a `123105`). Cada `db:seed` —y `iniciar.bat` lo corre siempre— revertía el nombre del catálogo
a la etiqueta corta de L1: «Hospital Base San José de Osorno» volvía a «Hospital Base San José».

Además hacía `detach()` + `attach()` sobre la matriz, así que **borraba el ajuste del administrador** en
cada arranque.

Corregido: `firstOrCreate` para el establecimiento, y la matriz solo se llena cuando el establecimiento
**no tiene ninguna fila**. Dos tests lo fijan.

---

## Lo que sí se cumplía

| Regla | Dónde | Estado |
|---|---|---|
| R-SER-05 «sin fila, no obliga» | Todas las consultas parten del pivot | ✅ |
| R-SER-06 «serie desmarcada no cuenta» | `wherePivot('obligatoria', true)` | ✅ |
| R-SER-07 «serie inactiva no se espera» | `where('series.activo', true)` en las cuatro | ✅ |
| D-029 quinto día hábil | `CalendarioService` + `feriados` | ✅ |
| D-030 identidad local sin correo | Fortify recortado | ✅ |
| 3 roles / 16 permisos | `RolePermissionSeeder::MATRIZ` == `rbac-matrix.json` | ✅ |

---

## Lo que queda abierto

| # | Tema | Estado |
|---|---|---|
| R-SER-01 | Derivar la matriz del histórico de 12 meses | **No implementado.** Se sustituyó por «todo el mundo debe todo» al importar, con ajuste manual |
| Formulario de matriz | `MatrizController::edit` lista **todas** las series, incluidas las inactivas (SAX). Guardar crea filas para ellas | **Preexistente, sin impacto funcional**: todas las consultas filtran `series.activo`. Pero ensucia la tabla y el formulario ofrece algo que no se puede usar |

El resto —ventana de vigencia, tests y el borrado del seeder— está cerrado en
`feature/matriz-series-vigencia` y verificado con 285 tests.
