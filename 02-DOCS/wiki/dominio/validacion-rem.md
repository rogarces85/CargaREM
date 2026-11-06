---
type: dominio
title: Validación REM — pipeline, severidad y paridad
description: Síntesis operable del motor V0–V11 y de cómo se prueba que el portal valida igual que ValidadorRem.
tags: [dominio, validacion, rem, paridad, env]
timestamp: 2026-11-06T14:30:00Z
topic: dominio
---

# Validación REM — pipeline, severidad y paridad

> Fuente: `backup_documentacion/MODULO_M7_PROFUNDIZADO.md` §7, §17 y §20.1. Este artículo existe
> para que la constitución pueda citar la barra sin copiar el diseño completo. Si hay conflicto,
> manda el documento fuente.

## 1. Etapas

| Etapa | Qué valida | Fuente | Si falla |
|---|---|---|---|
| **V0** | Existencia, tamaño, extensión, tipo real, archivo corrupto o cifrado | Sistema de archivos, `finfo`, firma ZIP/OLE2 | Bloqueante |
| **V1** | Nombre: código, serie, mes | Regex + catálogos | Bloqueante |
| **V2** | Cabecera: serie, año, mes, versión, código de establecimiento | `plantilla_cabecera` o detección por etiqueta | Bloqueante |
| **V3** | Versión de plantilla contra `plantillas(anio, mes, serie)` | Catálogo espejo | Bloqueante |
| **V4** | Pertenencia del establecimiento al SS y al usuario | `establecimientos` + pivot | Bloqueante |
| **V5** | Hojas obligatorias y rangos | `plantilla_hojas` (Thojas) | Bloqueante / advertencia |
| **V6** | Mapeo de celdas y lectura por prestación | `diccionario` (Diccionario) | Bloqueante si no se puede leer |
| **V7** | Tipo de dato y decimales | `diccionario.tipo_dato` | Advertencia |
| **V8** | Columnas no aplicables | `excepciones` (Excep/colexcep) | Advertencia |
| **V9** | Duplicados internos de prestación | Mismo archivo | Advertencia |
| **V10** | Al menos un registro válido | Resultado acumulado | Bloqueante |
| **V11** | Completitud de series del envío | `establecimiento_serie` | Bloqueante para pasar a revisión |

V0–V10 se ejecutan por archivo. **V11 se evalúa por envío**, después de cada subida y antes de
habilitar "Enviar a revisión".

## 2. Severidad

| Severidad | Efecto | Regla de oro |
|---|---|---|
| **Bloqueante** | Impide `validado` y `en_revision` | Nunca se degrada a advertencia para "avanzar" |
| **Advertencia** | No impide avanzar, pero el revisor debe verla y marcarla revisada | Se muestra, no se ignora |
| **Informativa** | Solo para el reporte | No decide nada |

Una advertencia que se repite y siempre se ignora es candidata a bloqueante; el cambio se decide
con la Unidad de Estadísticas y se registra en `02-DOCS/wiki/sdd/decisions.md`.

## 3. Códigos de error

Catálogo completo y mensaje al usuario: `MODULO_M7_PROFUNDIZADO.md` §20.1 (`ENV-001` a `ENV-0xx`).
Regla de la constitución (principio 6): **ninguna regla nueva sin código, severidad y test**.

## 4. Paridad con ValidadorRem (golden files)

El portal no puede inventar la validación. Para afirmar paridad:

1. Reunir 12–20 archivos reales **anonimizados**: por serie, por mes, con y sin errores, en `.xls`,
   `.xlsx` y `.xlsm`, más un caso SAX cuando exista.
2. Ejecutar ValidadorRem en entorno de prueba con copia de `Global.mdb` y base vacía.
3. Exportar `datos` y `LogError` resultantes.
4. Guardar el par (entrada → salida esperada) como *fixture*.
5. Comparar: cantidad de registros, claves, valores por columna (tolerancia 0 en numéricos) y
   hallazgos bloqueantes.
6. Toda diferencia se documenta y se decide: ¿bug del portal o regla no documentada?

**Ningún cambio en V6–V8 se fusiona sin esta comparación** (constitución, principio 10).

## 5. Riesgo que no se resuelve leyendo código

La semántica exacta de `inicio`, `fin` y `posicion` en la tabla `Diccionario` **no está
confirmada**. Puede significar rango origen → columna destino, columnas fijas por prestación o
desplazamiento. **No se implementa por intuición**: primero se produce el *golden output* del
validador actual y se ajusta el lector hasta que coincida.

Lo mismo aplica a las coordenadas de cabecera: se detectan por etiqueta (`SERIE`, `AÑO`, `MES`,
`VERSIÓN`, `ESTABLECIMIENTO`) y solo se fijan coordenadas cuando la detección no alcance.

## 6. Matriz mínima de fixtures

| Caso | Esperado |
|---|---|
| Archivo válido serie A | `validado`, 0 bloqueantes |
| Mes distinto al período | `ENV-012` bloqueante |
| Serie del nombre distinta a la cabecera | `ENV-010` bloqueante |
| Versión de plantilla antigua | `ENV-014` bloqueante |
| Hoja obligatoria faltante | `ENV-016` bloqueante |
| Archivo `.xls` legado | Validación correcta |
| `.xlsm` con macros | Válido + advertencia `ENV-028` |
| Archivo corrupto | `ENV-004` bloqueante |
| Nombre sin convención | `ENV-005` bloqueante |
| Prestación duplicada | `ENV-023` advertencia |
| Establecimiento de otro SS | `ENV-015` bloqueante |
| Reenvío de la misma serie | Nueva versión, anterior `reemplazado` |
| Mismo SHA-256 | `ENV-029` bloqueante |

## 7. Presupuesto de ejecución

Validación síncrona dentro de la petición, con lock de concurrencia (máximo 2) y lectura
`setReadDataOnly(true)`, hoja por hoja. Objetivo: p95 < 90 s y < 512 MB por archivo de ~1,6 MB.
Esto es un presupuesto de la constitución (principio 25), no una aspiración.
