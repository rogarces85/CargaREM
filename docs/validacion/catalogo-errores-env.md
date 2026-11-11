---
type: catalogo
title: Catálogo de errores ENV-### — Validación REM
description: Lista completa de códigos de error, severidad, etapa, mensaje al usuario y acción sugerida para el pipeline V0–V11.
tags: [validacion, catalogo, env, rem, paridad]
timestamp: 2026-11-10T15:00:00Z
topic: validacion
source: backup_documentacion/MODULO_M7_PROFUNDIZADO.md §20.1 (líneas 1461-1492)
---

# Catálogo de errores ENV-###

> **Fuente canónica:** `backup_documentacion/MODULO_M7_PROFUNDIZADO.md` §20.1 (líneas 1461-1492).
> **Principio 6 (Constitución v3.1.0):** Toda regla de validación V0–V11 tiene un código `ENV-###` único, una severidad asignada y al menos un test que la dispare.
> **Referencia:** SPECS.md §10 (Casos límite y errores).

## V0 — Archivo (existencia, tamaño, extensión, tipo real, integridad)

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-001** | V0 | Bloqueante | El archivo supera el tamaño máximo permitido (2 MB). | Reduzca el archivo o divídalo; límite: 2 MB por petición. |
| **ENV-002** | V0 | Bloqueante | Extensión no permitida. Use .xls, .xlsx o .xlsm. | Guarde el archivo con extensión válida (.xls, .xlsx, .xlsm). |
| **ENV-003** | V0 | Bloqueante | El contenido no corresponde a un archivo Excel válido. | Verifique que el archivo no esté corrupto y sea un Excel genuino. |
| **ENV-004** | V0 | Bloqueante | El archivo está dañado o protegido con contraseña. | Elimine la contraseña o repare el archivo antes de subirlo. |
| **ENV-027** | V0 | Bloqueante | El archivo contiene estructuras no permitidas. | Guarde como .xlsx estándar; evite XML externo o características avanzadas. |
| **ENV-028** | V0 | Advertencia | El archivo contiene macros; no se ejecutarán. | Las macros se ignoran por seguridad; guarde como .xlsx si es posible. |

## V1 — Nombre de archivo (convención código-serie-mes)

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-005** | V1 | Bloqueante | El nombre no sigue el formato código–serie–mes. | Renómbrelo como `CODIGOSERIEMES.ext` (ej.: `123456A09.xlsx`). Se aceptan sufijos `(1)`, `_corregido`. |
| **ENV-006** | V1 | Bloqueante | La serie del nombre no existe en el catálogo. | Verifique que la serie (A, BS, BM, D, P, F) sea correcta. |
| **ENV-007** | V1 | Bloqueante | El mes del nombre no coincide con el período del envío. | Corrija el mes en el nombre del archivo al período actual. |
| **ENV-008** | V1 | Bloqueante | El código del nombre no corresponde al establecimiento del envío. | Use el código de establecimiento asignado a este envío. |

## V2 — Cabecera interna (detección por etiquetas o coordenadas)

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-009** | V2 | Bloqueante | No se pudo identificar la cabecera del archivo. | Verifique que el archivo tenga las etiquetas SERIE, AÑO, MES, VERSIÓN, ESTABLECIMIENTO en las primeras 25 filas × 15 columnas. |
| **ENV-010** | V2 | Bloqueante | La serie declarada en el archivo no coincide. | La serie en la cabecera debe ser igual a la serie seleccionada. |
| **ENV-011** | V2 | Bloqueante | El año del archivo no coincide con el período. | Corrija el año en la cabecera del archivo. |
| **ENV-012** | V2 | Bloqueante | El mes del archivo no coincide con el período. | Corrija el mes en la cabecera del archivo. |
| **ENV-013** | V2 | Bloqueante | El código de establecimiento del archivo no corresponde. | El código en la cabecera debe coincidir con el establecimiento del envío. |

## V3 — Versión de plantilla

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-014** | V3 | Bloqueante | La versión de plantilla no corresponde al período. | Use la plantilla vigente para el año/mes/serie actual. |

## V4 — Pertenencia (establecimiento → SS y usuario)

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-015** | V4 | Bloqueante | El establecimiento no pertenece al Servicio de Salud. | Solo puede subir archivos de establecimientos asignados a su usuario. |

## V5 — Hojas obligatorias y rangos

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-016** | V5 | Bloqueante | Falta la hoja obligatoria «{hoja}». | Asegúrese de incluir todas las hojas definidas en la plantilla para esta serie. |
| **ENV-017** | V5 | Advertencia | Hoja no prevista en la plantilla: «{hoja}». | Revise si la hoja extra es necesaria; se ignorará en la validación. |

## V6 — Mapeo de celdas y lectura por prestación (Diccionario)

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-018** | V6 | Advertencia | Prestación sin datos en la hoja «{hoja}». | Verifique que la prestación tenga valores en el rango correspondiente. |
| **ENV-019** | V6 | Bloqueante | No se pudo leer el rango {rango} de la hoja «{hoja}». | Revise celdas combinadas, rangos vacíos o referencias incorrectas en la plantilla. |

## V7 — Tipo de dato y decimales

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-020** | V7 | Advertencia | Valor no numérico en {celda}; se registrará vacío. | Ingrese un número válido en la celda indicada. |
| **ENV-021** | V7 | Advertencia | Valor con más decimales de los permitidos en {celda}. | Ajuste los decimales según la configuración de la prestación. |
| **ENV-031** | V7 | Advertencia | La celda {celda} contiene una fórmula sin valor calculado. | Guarde el archivo con valores calculados (no solo fórmulas). |

## V8 — Excepciones (columnas no aplicables)

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-022** | V8 | Advertencia | Columna no aplicable con valor en {celda}. | La prestación no aplica para esta columna; déjela vacía. |

## V9 — Duplicados internos

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-023** | V9 | Advertencia | Prestación duplicada en el archivo: {codigo}. | Elimine la fila duplicada; cada prestación debe aparecer una sola vez. |

## V10 — Al menos un registro válido

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-024** | V10 | Bloqueante | El archivo no contiene registros válidos. | Verifique que al menos una prestación tenga datos válidos. |

## V11 — Completitud por envío (nivel envío)

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-025** | V11 | Bloqueante | Faltan series obligatorias: {lista}. | Suba los archivos de las series faltantes antes de enviar a revisión. |

## Proceso — Aprobación, carga, validación, seguridad

| Código | Etapa | Severidad | Mensaje al usuario | Acción sugerida |
|---|---|---|---|---|
| **ENV-026** | Aprobación | Bloqueante | Ya existe una carga aprobada para el período. | No se puede aprobar dos veces; contacte al admin si necesita corregir. |
| **ENV-029** | Carga | Bloqueante | Este archivo ya fue subido en este envío (mismo hash). | No vuelva a subir el mismo archivo; corrija y suba una versión nueva. |
| **ENV-030** | Validación | Bloqueante | La validación excedió el tiempo disponible; reintente. | El servidor está ocupado; espere unos segundos y vuelva a intentarlo. |
| **ENV-032** | Seguridad | Bloqueante | El archivo no pudo ser verificado por el antivirus. | Contacte al administrador; verificación de seguridad fallida. |

---

## Notas de implementación

1. **Severidad y parada de cadena** (Constitución principio 6, PLAN §11.3):
   - Bloqueante: detiene la cadena de validación (salvo V11 que siempre se ejecuta).
   - Advertencia: no detiene; revisor debe marcar "revisada" antes de aprobar.
   - Informativa: solo para reporte.

2. **Códigos de proceso vs archivo** (M7 §20.1 nota):
   - `ENV-025` a `ENV-032` son de proceso, no de lectura de archivo, pero se muestran igual en el reporte para referencia única.

3. **Mensajes en español chileno** (Constitución principio 11): sin jerga técnica, acción sugerida concreta.

4. **Tests obligatorios** (Principio 6): cada `ENV-###` debe tener al menos un test unitario que lo dispare.

5. **Paridad** (Principio 10): cambios en V6–V8 exigen comparación contra golden files con tolerancia 0 en numéricos.

---

## Checklist de cobertura (Principio 6)

- [x] ENV-001 a ENV-004 (V0 archivo)
- [x] ENV-005 a ENV-008 (V1 nombre)
- [x] ENV-009 a ENV-013 (V2 cabecera)
- [x] ENV-014 (V3 versión plantilla)
- [x] ENV-015 (V4 pertenencia)
- [x] ENV-016 a ENV-017 (V5 hojas)
- [x] ENV-018 a ENV-019 (V6 mapeo celdas)
- [x] ENV-020 a ENV-021, ENV-031 (V7 tipo dato)
- [x] ENV-022 (V8 excepciones)
- [x] ENV-023 (V9 duplicados)
- [x] ENV-024 (V10 registro válido)
- [x] ENV-025 (V11 completitud)
- [x] ENV-026 (aprobación)
- [x] ENV-027 a ENV-028 (V0 estructuras/macros)
- [x] ENV-029 (hash duplicado)
- [x] ENV-030 (timeout)
- [x] ENV-031 (fórmula sin valor)
- [x] ENV-032 (antivirus)

**Total: 32 códigos ENV-### documentados.**

---

**Archivo guardado en:** `C:\xampp\htdocs\www\CargaREM\docs\validacion\catalogo-errores-env.md`