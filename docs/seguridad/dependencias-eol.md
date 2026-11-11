---
type: seguridad
title: Dependencias al final de su vida — riesgo aceptado y mitigaciones
description: Avisos de seguridad abiertos en Laravel 8.83 y en el lector de Excel, derivados del canon PHP 7.4.3. Qué es alcanzable en este portal, qué no, y cómo se reduce.
tags: [seguridad, dependencias, eol, laravel, phpspreadsheet, riesgo]
timestamp: 2026-11-11T16:30:00Z
topic: seguridad
status: decision_pendiente
---

# Dependencias al final de su vida

> **Medido el 2026-11-11** con `composer audit` sobre el proyecto real.

---

## 1. El origen del problema

El canon es **PHP 7.4.3** (C-010) porque es lo que corre el vhost del servidor y TI no va a actualizarlo. Ese PHP salió de soporte el **28 de noviembre de 2022**.

Todo lo que corra encima hereda esa fecha: la última versión de Laravel compatible es la 8.x, y la de cualquier librería queda congelada en su última rama que acepte 7.4. **Esas ramas ya no reciben parches de seguridad.**

No es un descuido del proyecto. Es el costo de la restricción, y hay que nombrarlo.

---

## 2. Laravel 8.83.29 — 5 avisos abiertos

`composer audit` reporta:

| Severidad | Aviso | ¿Alcanza a este portal? |
|---|---|---|
| **Crítica** | `PKSA-b41t-wbzk-9q51` — *TokenGuard authentication bypass* por credenciales no-string | **Solo si se usa autenticación por token.** El portal usa sesión |
| **Alta** | `PKSA-3r5d-mb8f-1qw9` — inyección CRLF en la regla de validación `email` | **Sí**, si un formulario valida email con la regla por defecto |
| Media | `PKSA-8qx3-n5y5-vvnd` — *File Validation Bypass* | **Sí**, pero el diseño ya no confía en esa regla (§3) |
| Media | `PKSA-m5cs-t1y6-qpcs` — confusión de ruta en URLs firmadas temporales | Solo si se usan URLs firmadas |
| Baja | `PKSA-d5tc-s1qs-h781` — XSS en la página de depuración | Solo con `APP_DEBUG=true` |

Aviso adicional de `league/flysystem` (baja): normalización de rutas con UTF-8 malformado. Afecta a `Storage` y se mitiga no aceptando nombres de archivo del usuario.

**Ninguno se va a corregir.** Laravel 8 está en EOL y la corrección de la crítica vive en Laravel 10.50.4 en adelante.

---

## 3. Mitigaciones obligatorias

Estas no son sugerencias: si un cambio las rompe, `REVIEW.md` lo declara fallo grave.

| # | Mitigación | Qué neutraliza |
|---|---|---|
| 1 | **Solo autenticación por sesión.** No se habilitan tokens de API ni Sanctum para el portal | La crítica de TokenGuard |
| 2 | **Nunca `$request->validate(['email' => 'email'])` con datos sin sanear.** Se filtra `\r` y `\n` antes, o se usa una regla propia | Inyección CRLF |
| 3 | **El tipo real del archivo se comprueba con `finfo` y firma ZIP/OLE2**, no con la regla `mimes` | El *File Validation Bypass* |
| 4 | **`APP_DEBUG=false` en producción** y sin páginas de diagnóstico | XSS de la página de depuración |
| 5 | **No se usan URLs firmadas temporales** | Confusión de ruta |
| 6 | **Nombre de archivo generado por el servidor**, nunca el del usuario | Normalización de rutas de Flysystem |

Las reglas 2, 3 y 6 ya estaban en la Constitución y en el catálogo `ENV-###`. Este documento solo explica **por qué** están ahí y qué pasa si se relajan.

---

## 4. El lector de Excel — decisión pendiente

`phpoffice/phpspreadsheet` es la única librería que lee `.xls`, `.xlsx` y `.xlsm` con fórmulas en PHP 7.4. Las alternativas (`box/spout`, lectores simples) no cubren `.xls` ni evalúan fórmulas, y el validador las necesita.

Pero:

| Dato | Valor |
|---|---|
| Última versión compatible con PHP 7.4 | **1.29.12** |
| Avisos abiertos en 1.29.12 | **13** |
| Versión que los corrige | 2.x |
| PHP mínimo de 2.x | **8.1** |

Con PHP 7.4.3 no hay versión de PhpSpreadsheet sin avisos. Composer 2.9 lo bloquea por defecto (`block-insecure`), y por eso **no está instalado todavía**.

### Las opciones

| # | Opción | Coste | Consecuencia |
|---|---|---|---|
| **A** | Instalar 1.29.12 desactivando el bloqueo solo para sus avisos, y endurecer todo el perímetro de entrada | Bajo | Se acepta el riesgo del lector. Requiere validar tipo real, tope de tamaño y rechazo de estructuras no permitidas (`ENV-027`) antes de abrir el archivo |
| **B** | Convertir cada Excel a CSV **fuera** del portal (un script en el servidor de archivos) y que el portal solo lea CSV | Medio | El portal no toca el formato binario, pero se pierde la validación en el momento de la subida y aparece un paso manual |
| **C** | Revisar el canon: exigir PHP 8.1+ en el servidor | Alto | Mata C-010. Depende de TI, que ya dijo que no |
| **D** | Dejar la decisión para F2 y seguir con F1 | Ninguno hoy | Es lo que se hizo: el lector no se necesita hasta F2 |

La opción **A** es la única que no cambia el alcance del MVP. La decisión es de negocio y TI, no del agente: se registra cuando se cierre.

---

## 5. Lo que queda abierto

| ID | Decisión | Bloquea |
|---|---|---|
| **D15** | Riesgo aceptado de Laravel 8.83 en EOL con las 6 mitigaciones | Nada, si se respetan |
| **D16** | Lector de Excel: opción A, B o C | F2, no F1 |

Mientras D15 y D16 estén abiertas, el proyecto puede avanzar en F1. **F2 no puede empezar sin cerrar D16.**
