---
type: seguridad
title: Dependencias — del Laravel 8 sin parche al Laravel 12 limpio
description: Historia de los avisos de seguridad que trajo el canon PHP 7.4.3, cómo se resolvieron al migrar a Laravel 12 con PHP 8.2, y qué prácticas siguen siendo obligatorias aunque ya no haya avisos.
tags: [seguridad, dependencias, eol, laravel, phpspreadsheet, riesgo]
timestamp: 2026-11-11T18:00:00Z
topic: seguridad
status: resuelto
---

# Dependencias — del Laravel 8 sin parche al Laravel 12 limpio

> **Medido el 2026-11-11** con `composer audit` sobre el proyecto real, antes y después de migrar.

---

## 0. Estado actual: sin avisos

| | |
|---|---|
| Framework | **Laravel 12.72.0** |
| PHP | 8.2 |
| `composer audit` | **«No security vulnerability advisories found»** |
| Decisión | C-015 · Constitución v4.0.0 |

Lo que sigue es el registro de **por qué se llegó hasta acá**: la lección sirve para la próxima dependencia que haya que elegir.

---

## 1. El origen del problema

El canon anterior era **PHP 7.4.3** (C-010) porque era lo que corría el vhost del servidor. Ese PHP salió de soporte el **28 de noviembre de 2022**.

Todo lo que corriera encima heredaba esa fecha: la última versión de Laravel compatible era la 8.x, y cualquier librería quedaba congelada en su última rama que aceptara 7.4. **Esas ramas ya no reciben parches.**

---

## 2. Laravel 8.83.29 — los 5 avisos que obligaron a migrar

`composer audit` reportaba:

| Severidad | Aviso | ¿Alcanzaba al portal? |
|---|---|---|
| **Crítica** | `PKSA-b41t-wbzk-9q51` — *TokenGuard authentication bypass* por credenciales no-string | **Solo con autenticación por token.** El portal usa sesión |
| **Alta** | `PKSA-3r5d-mb8f-1qw9` — inyección CRLF en la regla de validación `email` | **Sí**, si un formulario valida email con la regla por defecto |
| Media | `PKSA-8qx3-n5y5-vvnd` — *File Validation Bypass* | **Sí**, pero el diseño nunca confió en esa regla (§3) |
| Media | `PKSA-m5cs-t1y6-qpcs` — confusión de ruta en URLs firmadas temporales | Solo con URLs firmadas |
| Baja | `PKSA-d5tc-s1qs-h781` — XSS en la página de depuración | Solo con `APP_DEBUG=true` |

Aviso adicional de `league/flysystem` (baja): normalización de rutas con UTF-8 malformado.

**Ninguno se iba a corregir.** La corrección de la crítica apareció en Laravel 10.50.4, pero esa rama siguió afectada por otros avisos, y Laravel 11 tampoco quedó limpia. La primera sin ninguno fue **Laravel 12.68+**, que exige PHP 8.2. Esa es la cadena que llevó a C-014 y a C-015.

---

## 3. Prácticas que siguen vigentes

Seis reglas nacieron para neutralizar aquellos cinco avisos. **Con Laravel 12 ya no hacen falta para eso, pero cuatro siguen siendo buena ingeniería** y una es obligatoria por la Constitución. Se mantienen: quitar una regla cuesta más de lo que ahorra.

| # | Práctica | Por qué se queda |
|---|---|---|
| 1 | **Solo autenticación por sesión.** Sin tokens de API ni Sanctum en el portal | El portal no expone API (D-007). Si algún día lo hace, se decide entonces |
| 2 | **Sanear `\r` y `\n` antes de validar un correo** | La regla `email` de Laravel es input del usuario; sanear es gratis |
| 3 | **Tipo real del archivo con `finfo` y firma ZIP/OLE2**, no la regla `mimes` | **Obligatoria:** principio 19 y `ENV-003`/`ENV-004`. El portal recibe archivos de terceros |
| 4 | **`APP_DEBUG=false` en producción** | **Obligatoria:** principio 20 |
| 5 | **Nombre de archivo generado por el servidor** | **Obligatoria:** principio 19, y evita la clase de fallo de §4 |
| 6 | URLs firmadas temporales solo si se necesitan y con criterio | Hoy no se usan; no hay nada que prohibir |

Las reglas 3, 4 y 5 ya estaban en la Constitución y en el catálogo `ENV-###`. Este documento solo explica **por qué** están ahí.

---

## 4. El lector de Excel — resuelto

| Dato | Valor |
|---|---|
| Versión en Laravel 12 | **5.11** |
| Versión que se usó en Laravel 8 | 1.30.8 |
| Avisos abiertos | **0** en ambas |
| Probado el 2026-11-11 | En Laravel 8: escribe y lee `.xlsx`, lectores `Xls`, `Ods` y `Csv`, acentos y decimales correctos |

Una conclusión anterior de este documento estaba equivocada y conviene dejarla escrita: el bloqueo inicial de Composer **no era de seguridad** sino de `zipstream-php`. La versión 1.30.8 resultó limpia y funcionaba en PHP 7.4. El salto a 5.11 en Laravel 12 es de versión mayor: **su API de lectura hay que revisarla contra el validador en F2**, no traducirla a ciegas.

### La regla de uso (D16)

**El lector recibe siempre una ruta generada por el servidor, nunca el nombre que subió el usuario.**

Es la clase de fallo que más costó en esta librería: la crítica `CVE-2026-34084` era SSRF/RCE en `IOFactory::load` cuando el nombre del archivo lo controlaba el usuario. Está corregida, pero la regla evita depender de que el próximo parche llegue a tiempo.

Implementación: el archivo se guarda en `storage/app/envios/` con un nombre aleatorio y el motor de validación recibe esa ruta absoluta. Nunca se toca `getClientOriginalName()` para construir una ruta.

---

## 5. Lo que queda

| ID | Decisión | Estado |
|---|---|---|
| **D15** | Riesgo de Laravel 8.83 en EOL | ✅ **Cerrada:** no se aceptó. C-014 reabrió y C-015 cerró con Laravel 12 |
| **D16** | Lector de Excel | ✅ **Cerrada:** la regla de §4 |
| **—** | **Despliegue** | 🟡 **Abierto y es el único:** el portal no publica hasta que TI habilite PHP 8.2. `docs/infra/solicitud-php-82-ti.md` |

Si TI responde que no, el proyecto tiene dos salidas: volver a Laravel 8 aceptando el riesgo, o quedarse en Laravel 12 sin desplegar. **Ninguna es gratis, y la decisión vuelve a ser de negocio.**



