---
type: hoja-firma
title: Hoja de firma — severidad de los 32 códigos ENV-###
description: Versión de una página de la política de severidad, para firmar sin leer el documento completo. 24 bloqueantes y 8 advertencias.
tags: [validacion, severidad, firma, env, rem]
timestamp: 2026-11-11T15:20:00Z
topic: validacion
status: pendiente_firma
source: docs/validacion/politica-severidad.md; docs/validacion/catalogo-errores-env.md
---

# Hoja de firma — severidad de los códigos `ENV-###`

**Qué se firma:** que cada uno de estos 32 códigos tiene la severidad correcta.

**Qué significa cada severidad:**

- **Bloqueante** — el archivo no pasa. El encargado debe corregirlo y volver a subirlo.
- **Advertencia** — el archivo pasa, pero el revisor debe reconocerla antes de aprobar.

**No se firma:** el texto de los mensajes ni la redacción. Eso es cosmético y se ajusta cuando haga falta.

---

## Los 32 códigos

| Código | Etapa | Severidad | Qué reporta al usuario | Conforme |
|---|---|---|---|---|
| `ENV-001` | V0 | **Bloqueante** | El archivo supera 2 MB | ☐ |
| `ENV-002` | V0 | **Bloqueante** | Extensión no permitida | ☐ |
| `ENV-003` | V0 | **Bloqueante** | El contenido no es un Excel válido | ☐ |
| `ENV-004` | V0 | **Bloqueante** | Archivo dañado o protegido con contraseña | ☐ |
| `ENV-005` | V1 | **Bloqueante** | El nombre no sigue la convención código-serie-mes | ☐ |
| `ENV-006` | V1 | **Bloqueante** | La serie del nombre no existe en el catálogo | ☐ |
| `ENV-007` | V1 | **Bloqueante** | El mes del nombre no es el del período | ☐ |
| `ENV-008` | V1 | **Bloqueante** | El código del nombre no es el del establecimiento del envío | ☐ |
| `ENV-009` | V2 | **Bloqueante** | No se pudo identificar la cabecera | ☐ |
| `ENV-010` | V2 | **Bloqueante** | La serie de la cabecera no coincide | ☐ |
| `ENV-011` | V2 | **Bloqueante** | El año de la cabecera no coincide con el período | ☐ |
| `ENV-012` | V2 | **Bloqueante** | El mes de la cabecera no coincide con el período | ☐ |
| `ENV-013` | V2 | **Bloqueante** | El código de establecimiento de la cabecera no corresponde | ☐ |
| `ENV-014` | V3 | **Bloqueante** | La versión de plantilla no corresponde al período | ☐ |
| `ENV-015` | V4 | **Bloqueante** | El establecimiento no pertenece al Servicio de Salud del usuario | ☐ |
| `ENV-016` | V5 | **Bloqueante** | Falta una hoja obligatoria | ☐ |
| `ENV-017` | V5 | Advertencia | Hay una hoja no prevista en la plantilla | ☐ |
| `ENV-018` | V6 | Advertencia | Prestación sin datos | ☐ |
| `ENV-019` | V6 | **Bloqueante** | No se pudo leer un rango de la hoja | ☐ |
| `ENV-020` | V7 | Advertencia | Valor no numérico; se registra vacío | ☐ |
| `ENV-021` | V7 | Advertencia | Más decimales de los permitidos | ☐ |
| `ENV-022` | V8 | Advertencia | Columna no aplicable con valor | ☐ |
| `ENV-023` | V9 | Advertencia | Prestación duplicada en el archivo | ☐ |
| `ENV-024` | V10 | **Bloqueante** | El archivo no tiene ningún registro válido | ☐ |
| `ENV-025` | V11 | **Bloqueante** | Faltan series obligatorias del envío | ☐ |
| `ENV-026` | Aprobación | **Bloqueante** | Ya existe una carga aprobada para el período | ☐ |
| `ENV-027` | V0 | **Bloqueante** | El archivo contiene estructuras no permitidas | ☐ |
| `ENV-028` | V0 | Advertencia | El archivo tiene macros; no se ejecutan | ☐ |
| `ENV-029` | Carga | **Bloqueante** | El archivo ya fue subido (mismo hash) | ☐ |
| `ENV-030` | Validación | **Bloqueante** | La validación excedió el tiempo disponible | ☐ |
| `ENV-031` | V7 | Advertencia | Fórmula sin valor calculado | ☐ |
| `ENV-032` | Seguridad | **Bloqueante** | El antivirus no pudo verificar el archivo | ☐ |

**Total: 24 bloqueantes y 8 advertencias. Ninguna informativa.**

---

## Si algo no está de acuerdo

Anote el código y la severidad que corresponde:

```text
ENV-____  debería ser: ☐ Bloqueante  ☐ Advertencia   Motivo: ______________________________

ENV-____  debería ser: ☐ Bloqueante  ☐ Advertencia   Motivo: ______________________________
```

Cambiar una severidad **antes** de construir es barato. Después, cada cambio obliga a tocar código, tests y reportes.

---

## Firma

| Campo | |
|---|---|
| Nombre | |
| Cargo | |
| Unidad | Unidad de Estadísticas |
| Fecha | |
| Firma | |

Al firmar, se actualiza `status: firmada` en [`politica-severidad.md`](politica-severidad.md) y se registra la firma en `02-DOCS/wiki/sdd/decisions.md`.

---

## Anexo — otras tres firmas que se pueden tomar en la misma visita

| Documento | Qué confirma | Estado |
|---|---|---|
| [Matriz de series obligatorias](matriz-series-obligatorias.md) | Qué series debe cada establecimiento y que SAX queda fuera del MVP | Borrador · 3 preguntas |
| [Reglas de cierre de período](reglas-cierre-periodo.md) | Día 10 configurable y cierre manual por el administrador | Borrador · 4 preguntas |
| [Identidad y 2FA](../auth/decision-identidad-2fa.md) | Cuentas locales y segundo factor para revisores y admin | Borrador · 4 preguntas |

Cada uno termina con una tabla de preguntas concretas. Con esas respuestas se cierra F0 entero.
