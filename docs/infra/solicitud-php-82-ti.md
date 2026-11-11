---
type: infra
title: Solicitud a TI — PHP 8.2 en el vhost del portal
description: Pedido formal para habilitar PHP 8.2 en el vhost del portal REM, la misma versión que ya corre el sistema de Observaciones, con la evidencia de seguridad y las preguntas concretas.
tags: [infra, ti, php, seguridad, solicitud, laravel-12]
timestamp: 2026-11-11T17:40:00Z
topic: infra
status: para_enviar
---

# Solicitud a TI — PHP 8.2 en el vhost

**Para:** TI / Infraestructura
**De:** Unidad de Estadísticas — Portal REM
**Fecha:** 11 de noviembre de 2026
**Asunto:** Habilitar PHP 8.2 en el vhost del portal de envío REM

---

## 1. Qué se pide

Habilitar **PHP 8.2** para el vhost `estadisticas.ssosorno.cl`.

**No es una versión nueva ni un servidor nuevo: es la misma versión que ya corre el sistema de Observaciones en otro vhost**, mediante PHP-FPM. Se pide replicar esa configuración para un vhost adicional.

Extensiones necesarias: `pdo_mysql`, `mbstring`, `intl`, `zip`, `gd`, `fileinfo`, `xml`, `dom`, `simplexml`, `xmlreader`, `xmlwriter`, `ctype`, `iconv`, `zlib`, `curl`, `openssl`, `bcmath` y `opcache`.

---

## 2. Por qué

El portal se construyó sobre **Laravel 8.83**, porque era lo único que corría en el PHP 7.4.3 del vhost.

Una auditoría de dependencias sobre el código real arrojó **5 avisos de seguridad abiertos, uno crítico**, y **ninguno tiene parche**: Laravel 8 llegó a su fin de vida.

La búsqueda de una versión sana dio este resultado, medido, no supuesto:

| Versión de Laravel | PHP que exige | Avisos abiertos |
|---|---|---|
| 8.83 | 7.4 | **5** (1 crítico) |
| 10.50.4 | 8.1 | **Todavía afectada** |
| 11.x | 8.2 | **Todavía afectada** |
| **12.68+** | **8.2** | **0** |
| 13.27+ | 8.3 | 0 |

La primera versión limpia que corre en una versión de PHP disponible en el servidor es **Laravel 12**, y pide **PHP 8.2**. Ni más ni menos que lo que Observaciones ya usa.

---

## 3. Ya está probado

Para no pedir algo sin verificar, se levantó un prototipo local:

| Verificación | Resultado |
|---|---|
| Laravel | **12.72.0** |
| PHP | **8.2.12** |
| `composer audit` | **sin avisos** |
| Aplicación por Apache | HTTP 200 |
| Migraciones | corren |

El mismo prototipo confirma que las cuatro librerías del portal existen en versión moderna y sin avisos: `spatie/laravel-permission` 6.25, `laravel/fortify` 1.42, `yajra/laravel-datatables-oracle` 12.7 y `phpoffice/phpspreadsheet` 5.11.

Detalle completo: `docs/stack/prototipo-laravel-12.md`.

---

## 4. Qué se desbloquea si se aprueba

| Efecto | Detalle |
|---|---|
| **Cero avisos de seguridad** | Hoy hay 5, uno crítico, sin posibilidad de arreglo |
| Soporte con parches hasta **2027–2028** | Laravel 12 es la rama vigente |
| Opcache disponible | La validación de un archivo tiene presupuesto de 90 s; hoy corre sin caché de código |
| Deja de ser deuda a un año vista | No hay que volver a pedir nada en 2027 |

---

## 5. Qué pasa si no se aprueba

Se mantiene Laravel 8.83 sobre PHP 7.4.3 con **riesgo residual aceptado** y seis mitigaciones documentadas en `docs/seguridad/dependencias-eol.md`:

1. Solo autenticación por sesión, nunca por token.
2. Saneo explícito de toda entrada que use la regla de correo.
3. Tipo real de archivo verificado con `finfo`, sin confiar en la regla de Laravel.
4. `APP_DEBUG=false` en producción.
5. Sin URLs firmadas temporales.
6. Nombre de archivo generado por el servidor.

Reducen la exposición. No eliminan el riesgo: el próximo aviso será igual de incurable.

---

## 6. Las tres preguntas concretas

| # | Pregunta | Por qué importa |
|---|---|---|
| 1 | ¿Se puede habilitar **PHP 8.2 con PHP-FPM** para el vhost `estadisticas.ssosorno.cl`, como ya se hizo para Observaciones? | Es la petición, y no requiere infraestructura nueva |
| 2 | ¿Se pueden habilitar las extensiones de §1 en ese PHP? | Sin ellas el portal no arranca |
| 3 | Si la respuesta es no, ¿cuál es el motivo y una fecha estimada de revisión? | Permite registrar la decisión con fundamento en vez de volver a preguntar |

---

## 7. Plazo

La respuesta condiciona el resto del proyecto. El desarrollo está **detenido** en la base ya construida: cada semana que pasa sin respuesta es una semana sin avanzar, porque escribir más código sobre Laravel 8 es trabajo que se descarta.

No hay urgencia de horas. Sí de semanas.
