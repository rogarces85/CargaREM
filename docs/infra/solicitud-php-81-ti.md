---
type: infra
title: Solicitud a TI — PHP 8.1 o superior en el vhost del portal
description: Pedido formal para habilitar PHP 8.1+ en el vhost donde vivirá el portal REM, con el motivo técnico, la evidencia y las dos alternativas si no se aprueba.
tags: [infra, ti, php, seguridad, solicitud]
timestamp: 2026-11-11T17:00:00Z
topic: infra
status: para_enviar
---

# Solicitud a TI — PHP 8.1 o superior

**Para:** TI / Infraestructura
**De:** Unidad de Estadísticas — Portal REM
**Fecha:** 11 de noviembre de 2026
**Asunto:** Habilitar PHP 8.1 o superior en el vhost del portal de envío REM

---

## 1. Qué se pide

Habilitar **PHP 8.1 o superior** (idealmente 8.3) para el vhost donde se publicará el portal, `estadisticas.ssosorno.cl`.

Extensiones necesarias: `pdo_mysql`, `mbstring`, `intl`, `zip`, `gd`, `fileinfo`, `xml`, `dom`, `simplexml`, `xmlreader`, `xmlwriter`, `ctype`, `iconv`, `zlib`, `curl`, `openssl`, `bcmath` y `opcache`.

No se pide actualizar el servidor completo ni el Apache principal. Basta con que **ese vhost** corra una versión superior, como ya ocurre con otro sistema.

---

## 2. Por qué

El portal se construyó sobre **Laravel 8.83**, porque era la única versión que corría en el PHP 7.4.3 del vhost principal.

Una auditoría de dependencias (`composer audit`) sobre el proyecto real arrojó:

| Severidad | Aviso | Estado |
|---|---|---|
| **Crítica** | Bypass de autenticación en TokenGuard | **Sin parche disponible** |
| Alta | Inyección CRLF en la regla de validación de correo | **Sin parche disponible** |
| Media | Bypass de validación de archivos | **Sin parche disponible** |
| Media | Confusión de ruta en URLs firmadas | **Sin parche disponible** |
| Baja | XSS en la página de depuración | **Sin parche disponible** |

**Laravel 8 llegó a su fin de vida.** Esos avisos no se corregirán en esa rama: la corrección de la crítica existe únicamente desde **Laravel 10.50.4**.

Y Laravel 10.50.4 **exige PHP 8.1 o superior**.

Dicho de otro modo: con PHP 7.4.3 no hay forma de dejar el portal sin vulnerabilidades conocidas y sin arreglo. No es un problema de configuración; es el techo del intérprete.

---

## 3. Un antecedente que ayuda

El sistema de **Observaciones ya corre con PHP 8.2** en su propio vhost, mediante PHP-FPM. Es decir: el servidor **ya tiene** un PHP moderno funcionando; la solicitud es replicar esa configuración para un vhost adicional, no instalar algo nuevo desde cero.

---

## 4. Qué se desbloquea si se aprueba

| Cambio | Efecto |
|---|---|
| Stack a **Laravel 10.50.4+** (LTS) | Los 5 avisos quedan corregidos |
| Soporte de seguridad hasta **febrero de 2027** (Laravel 10) o **2027–2028** si se va a Laravel 11/12 | Deja de ser deuda a un año vista |
| Opcache disponible | Mejora el rendimiento de la validación, hoy con presupuesto de 90 s por archivo |
| `bcmath` e `intl` disponibles | Evita dependencias de terceros para cálculos y formato |

El código de dominio (modelos, validaciones, flujo de revisión) se conserva. Lo que se rehace es el esqueleto del framework, que a esta altura es mínimo: aún no hay funcionalidad de negocio escrita.

---

## 5. Qué pasa si no se aprueba

Se mantiene **Laravel 8.83 sobre PHP 7.4.3** y el proyecto opera con **riesgo residual aceptado**, documentado en `docs/seguridad/dependencias-eol.md`, con estas mitigaciones obligatorias:

1. Solo autenticación por sesión, nunca por token.
2. Saneo explícito de entradas que usen la regla de correo.
3. Verificación del tipo real de cada archivo con `finfo`, sin confiar en la regla de Laravel.
4. `APP_DEBUG=false` en producción.
5. Sin URLs firmadas temporales.
6. Nombre de archivo generado por el servidor.

Son mitigaciones reales, pero **reducen la exposición; no eliminan el riesgo**. El próximo aviso será igual de incurable.

---

## 6. Las tres preguntas concretas

| # | Pregunta | Por qué |
|---|---|---|
| 1 | ¿Se puede habilitar PHP 8.1+ (o 8.3) para el vhost `estadisticas.ssosorno.cl`, con PHP-FPM si hace falta? | Es la petición principal |
| 2 | ¿Se pueden habilitar en ese PHP las extensiones listadas en §1? | Sin ellas el portal no arranca |
| 3 | Si la respuesta es no, ¿cuál es el motivo y la fecha estimada de revisión? | Permite registrar la decisión con fundamento y no volver a preguntar cada mes |

---

## 7. Plazo

La respuesta condiciona el resto del proyecto: mientras no llegue, el desarrollo queda detenido en la base ya construida. No hay urgencia de horas, pero sí de semanas: cada mes que pasa se escribe código sobre una base que podría reemplazarse.
