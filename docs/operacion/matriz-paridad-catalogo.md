# Paridad con el catálogo documentado

El catálogo de `ENV-001` a `ENV-032` está en
[`MODULO_M7_PROFUNDIZADO.md` §20.1](../../backup_documentacion/MODULO_M7_PROFUNDIZADO.md).
**Ese catálogo es la especificación**: lo que el sistema valida es lo que dice
ahí. Esta matriz contrasta la implementación contra él, código por código.

Última verificación: 12 de noviembre de 2026.

## Cómo leerla

| Estado | Significado |
|---|---|
| **Igual** | Implementado en la etapa y con la severidad documentadas |
| **Degradado** | Implementado, pero en un caso baja la severidad a propósito |
| **Distinto** | Implementado con otro significado: hay que corregir uno de los dos |
| **Ausente** | Documentado y no implementado |

## Matriz

| Código | Etapa doc. | Sev. doc. | Implementación | Estado |
|---|---|---|---|---|
| ENV-001 | V0 | B | V0 · tamaño | **Igual** |
| ENV-002 | V0 | B | V0 · extensión | **Igual** |
| ENV-003 | V0 | B | V0 · firma del contenido | **Igual** |
| ENV-004 | V0 | B | V0 · archivo ilegible o ausente | **Igual** |
| ENV-005 | V1 | B | V1 · formato del nombre | **Igual** |
| ENV-006 | V1 | B | V1 · serie inexistente | **Igual** |
| ENV-007 | V1 | B | V1 · mes del nombre | **Igual** |
| ENV-008 | V1 | B | V1 · código del nombre | **Igual** |
| ENV-009 | V2 | B | V2 · cabecera no identificable | **Igual** |
| ENV-010 | V2 | B | V2 · serie declarada | **Igual** |
| ENV-011 | V2 | B | V2 · año declarado | **Igual** |
| ENV-012 | V2 | B | V2 · mes declarado | **Igual** |
| ENV-013 | V2 | B | V2 · establecimiento declarado | **Igual** |
| ENV-014 | V3 | B | V3 · versión de plantilla | **Degradado** |
| ENV-015 | V4 | B | V4 · pertenencia al servicio | **Igual** |
| ENV-016 | V5 | B | V5 · hoja obligatoria | **Degradado** |
| ENV-017 | V5 | A | V5 · hoja no prevista | **Igual** |
| ENV-018 | V6 | A | V6 · prestación sin datos | **Igual** |
| ENV-019 | V6 | B | V6 · rango ilegible | **Igual** |
| ENV-020 | V7 | A | V7 · valor no numérico | **Igual** |
| ENV-021 | V7 | A | V7 · decimales | **Igual** |
| ENV-022 | V8 | A | V8 · columna que no aplica | **Igual** |
| ENV-023 | V9 | A | V9 · prestación duplicada | **Igual** |
| ENV-024 | V10 | B | V10 · sin registros válidos | **Igual** |
| ENV-025 | V11 | B | V11 · faltan series obligatorias | **Igual** |
| ENV-026 | Aprobación | B | No aplica: el envío es único por período | **Ausente (justificado)** |
| ENV-027 | V0 | B | V0 · objetos incrustados, vínculos externos, OLE, ActiveX | **Igual** |
| ENV-028 | V0 | A | V0 · macros | **Igual** |
| ENV-029 | Carga | B | Subida · hash duplicado | **Igual** |
| ENV-030 | Validación | B | Validación · tiempo excedido | **Igual** |
| ENV-031 | V7 | A | V7 · fórmula sin calcular | **Igual** |
| ENV-032 | Seguridad | B | No implementado: no hay antivirus en el servidor | **Ausente** |

**Resumen: 26 iguales, 2 degradados, 1 ausente justificado, 1 ausente.**

## Las dos degradaciones

**ENV-014** y **ENV-016** son bloqueantes cuando el catálogo permite saber la
respuesta, y advertencias cuando no:

| Situación | Severidad |
|---|---|
| Hay plantilla del período y la versión no coincide | **Bloqueante** |
| No hay plantilla del período | Advertencia |
| La plantilla declara hojas y falta una | **Bloqueante** |
| La plantilla no declara hojas | Advertencia |

**Por qué.** Bloquear por una carencia del catálogo dejaría el portal inservible
el mes en que el Minsal publica la plantilla tarde. La regla: se bloquea cuando
se sabe que está mal; se advierte cuando no se puede saber.

Si la jefatura prefiere el bloqueo incondicional, se cambia en una línea de cada
regla y se documenta.

## Los dos ausentes

**ENV-026 — «Ya existe una carga aprobada para el período».** No puede ocurrir:
la base tiene una restricción única por `(establecimiento, año, mes)`, así que no
existe un segundo envío del mismo período. La protección es estructural, no un
mensaje.

**ENV-032 — «El archivo no pudo ser verificado por el antivirus».** No hay
antivirus en el servidor. Implementarlo requeriría instalar ClamAV o similar y
pasarlo por cada archivo; no se puede simular sin mentir. Queda como requisito
si el Servicio lo exige.

## Lo que esta matriz no prueba

Prueba que cada código documentado existe, en la etapa correcta y con la
severidad correcta. **No prueba que la detección sea igual de fina**: que una
hoja «falte» o que un valor «no sea numérico» se decide con reglas que el
documento describe en prosa, y dos implementaciones pueden diferir en los bordes.

Para eso hace falta lo otro: un archivo que el ValidadorRem rechace, con su
captura. La primera verificación de ese tipo está hecha sobre `123100A08.xlsm`:
0 incorrectos del ValidadorRem contra 0 bloqueantes del portal.
