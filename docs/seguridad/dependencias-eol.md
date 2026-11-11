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

## 4. El lector de Excel — resuelto

| Dato | Valor |
|---|---|
| Versión adoptada | **1.30.8** |
| Avisos abiertos | **0** |
| Compatibilidad | PHP `>=7.4.0 <8.5.0` → corre en 7.4.3 y en 8.1+ |
| Probado el 2026-11-11 | Escribe y lee `.xlsx`; lectores `Xls`, `Ods` y `Csv` disponibles; acentos y decimales correctos |

Una conclusión anterior de este documento estaba equivocada y conviene dejarlo escrito: el bloqueo inicial de Composer no era de seguridad sino de `zipstream-php`. Con `^1.29` se resolvía a 1.30.x, que exige `zipstream` 2.2+, y en ese momento la resolución chocaba con PHP 7.4. La versión **1.30.8** funciona con `zipstream` **2.2.6**, que sí acepta 7.4, y no arrastra ningún aviso.

### La regla de uso (D16)

**El lector recibe siempre una ruta generada por el servidor, nunca el nombre que subió el usuario.**

Es la clase de fallo que más costó en esta librería: la crítica `CVE-2026-34084` era SSRF/RCE en `IOFactory::load` cuando el nombre del archivo lo controlaba el usuario. Está corregida en 1.30.8, pero la regla evita depender de que el próximo parche llegue a tiempo.

Implementación: el archivo se guarda en `storage/app/envios/` con un nombre aleatorio y el motor de validación recibe esa ruta absoluta. Nunca se toca `getClientOriginalName()` para construir una ruta.

---

## 5. Lo que queda abierto

| ID | Decisión | Estado |
|---|---|---|
| **D15** | Riesgo de Laravel 8.83 en EOL | ❌ **No se acepta.** **C-014 reabrió D-STACK** y se pidió PHP 8.1+ a TI |
| **D16** | Lector de Excel | ✅ **Resuelto:** 1.30.8 con la regla de §4 |

Mientras D-STACK esté abierta, el principio 1 de la Constitución vuelve a bloquear código de aplicación nuevo. `portal/` queda **provisional y congelado**.

Si TI responde que no, se vuelve a esta página y se cierra D15 como *riesgo aceptado* con las 6 mitigaciones de §3.
