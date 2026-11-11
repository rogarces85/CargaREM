---
type: decision
title: Identidad de usuarios y segundo factor (2FA)
description: Cómo se crean, autentican y desactivan los usuarios del portal, qué política de contraseña aplica y qué roles exigen segundo factor. Implementa D-001 y D-010.
tags: [auth, identidad, 2fa, seguridad, d1, d10]
timestamp: 2026-11-11T15:10:00Z
topic: auth
status: borrador_para_firma
source: sdd/decisions.md (D-001, D-010); MODULO_M7_PROFUNDIZADO.md §13.1; SPECS.md RF-001–RF-016; Constitución §5
---

# Identidad de usuarios y segundo factor (2FA)

> **Tarea:** T-F0-08 · **Estado:** borrador para firma por la Unidad de Estadísticas y TI.
> **Decisiones que implementa:** **D-001** (cuentas locales) y **D-010** (2FA para revisores y administradores).
> **Para qué sirve:** sin esto no se puede construir el login (F1.2, T-F1-10 a T-F1-17), que es la primera pieza de código del portal.

---

## 1. Las decisiones, en una frase

**Los usuarios los crea el administrador dentro del portal, con contraseña local. No hay directorio institucional ni SSO. Revisores y administradores, además, usan un segundo factor.**

Se descartó el correo institucional como fuente de identidad (depende de un servidor que no controlamos) y el directorio del Servicio de Salud (requiere integración con TI y no está disponible). Las cuentas locales permiten arrancar sin depender de nadie y se pueden migrar después sin cambiar el modelo de permisos.

---

## 2. Roles

| Rol | Alcance | 2FA |
|---|---|---|
| `encargado` | Sus establecimientos asignados | Opcional |
| `revisor` | Establecimientos de su Servicio de Salud | **Obligatorio** |
| `admin` | Global | **Obligatorio** |
| `consulta` | Solo lectura, su Servicio de Salud | Opcional |

Un usuario puede tener **varios establecimientos** mediante la tabla pivote `usuario_establecimiento`. El alcance se aplica **en la consulta SQL**, en cada petición (Constitución, principio 18), no en la vista.

---

## 3. Reglas

### 3.1 Alta y baja

| ID | Regla |
|---|---|
| **R-ID-01** | Solo el administrador crea usuarios. No hay autorregistro. |
| **R-ID-02** | El administrador define la contraseña inicial y la comunica por un canal fuera del portal. |
| **R-ID-03** | El usuario debe cambiar la contraseña en el primer acceso. |
| **R-ID-04** | Un usuario **nunca se elimina**: se desactiva (`activo = 0`). La auditoría y los envíos lo referencian. |
| **R-ID-05** | El administrador no puede resetear su propia contraseña ni cambiarse su propio rol. Evita perder el último acceso administrativo por error. |
| **R-ID-06** | Desactivar un usuario no borra sus envíos ni su historial; solo impide el acceso. |

### 3.2 Contraseña

| ID | Regla |
|---|---|
| **R-ID-07** | Mínimo **12 caracteres**, con al menos una mayúscula y un número. |
| **R-ID-08** | Se guarda con `bcrypt` (`Hash::make`), nunca en claro ni con hash reversible. |
| **R-ID-09** | No se permite reutilizar las **3** últimas contraseñas. |
| **R-ID-10** | El reset del administrador genera una contraseña aleatoria de 12 caracteres (mayúscula, minúscula, dígito, símbolo) y obliga al cambio en el siguiente acceso. |
| **R-ID-11** | La recuperación por correo usa un token de **un solo uso** que expira en **1 hora**. |

### 3.3 Autenticación y sesión

| ID | Regla |
|---|---|
| **R-ID-12** | Login por email o usuario + contraseña. |
| **R-ID-13** | Límite de **5 intentos por minuto** (`throttle:5,1`); superado, bloqueo temporal de **15 minutos**. |
| **R-ID-14** | Sesión de **8 horas**, con `secure`, `http_only`, `same_site=lax` y regeneración de ID al autenticar. |
| **R-ID-15** | Todo login, logout y login fallido se registra en auditoría. |

### 3.4 Segundo factor

| ID | Regla |
|---|---|
| **R-ID-16** | 2FA por **TOTP** (aplicación autenticadora), vía `laravel/fortify`. No se usa SMS: depende de telefonía y de un proveedor externo. |
| **R-ID-17** | Un `revisor` o `admin` sin 2FA activo es forzado a configurarlo en el siguiente acceso. No puede usar el portal sin completarlo. |
| **R-ID-18** | Se emiten **códigos de recuperación** de un solo uso al activar 2FA. |
| **R-ID-19** | Si un usuario pierde el segundo factor, el administrador puede reiniciarlo. La acción queda auditada y no revela el secreto. |
| **R-ID-20** | El 2FA no se puede desactivar sin una nueva configuración: bajar de revisor a encargado no lo apaga, solo lo vuelve opcional. |

---

## 4. Estructura

Tabla `users` (estándar de Laravel 8 + extensiones):

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `name` | VARCHAR(255) | Nombre visible |
| `email` | VARCHAR(255) | Único; se usa para el login y para la recuperación |
| `password` | VARCHAR(255) | Hash bcrypt |
| `activo` | TINYINT(1) | 0 = no puede autenticarse |
| `ultimo_acceso` | TIMESTAMP | Último login correcto |
| `two_factor_secret` | TEXT | Cifrado por Fortify |
| `two_factor_recovery_codes` | TEXT | Cifrado por Fortify |
| `two_factor_confirmed_at` | TIMESTAMP | NULL = 2FA no configurado |

Tablas de soporte: `roles`, `permissions`, `model_has_roles`, `model_has_permissions` (spatie) y `usuario_establecimiento`.

---

## 5. Lo que queda fuera

| Fuera del MVP | Motivo |
|---|---|
| Directorio institucional (LDAP / Active Directory) | Requiere integración con TI; no está disponible |
| SSO institucional | Igual |
| 2FA por SMS | Dependencia externa y coste; TOTP cubre el caso |
| Usuario con múltiples roles simultáneos | No aporta al flujo actual; simplifica los tests de permisos |
| Autenticación de API pública | El MVP no expone API; el puente es descarga manual (D-007) |

Si TI habilita un directorio institucional más adelante, se registra una decisión nueva en `sdd/decisions.md` y se agrega un *driver* de autenticación. El modelo de roles y permisos no cambia.

---

## 6. Lo que debe confirmar negocio y TI antes de firmar

| # | Pregunta | Por qué importa |
|---|---|---|
| 1 | ¿La recuperación de contraseña por correo funciona en el servidor? | Si no hay correo saliente, el reset solo puede hacerlo el administrador. Cambia la regla R-ID-11. |
| 2 | ¿El bloqueo de 15 minutos es aceptable, o debe ser hasta que el administrador lo libere? | Afecta a quien se equivoca cinco veces un día de cierre. |
| 3 | ¿Quién es el administrador inicial y cómo se crea? | El primer usuario no puede crearlo nadie desde la interfaz. Propuesta: *seeder* con contraseña temporal que obliga cambio. |
| 4 | ¿Un encargado puede tener establecimientos de dos Servicios de Salud? | Si sí, el revisor no puede verlo completo y cambia el alcance del rol. |

---

## 7. Trazabilidad

| Elemento | Origen |
|---|---|
| D-001 (identidad local) | `02-DOCS/wiki/sdd/decisions.md` |
| D-010 (2FA revisores y admin) | `02-DOCS/wiki/sdd/decisions.md` |
| §13.1 Autenticación y sesión | `backup_documentacion/MODULO_M7_PROFUNDIZADO.md` |
| RF-001 a RF-004, RF-013 a RF-016 | `specs/SPECS.md` |
| Principios 8, 18, 21 | `02-DOCS/wiki/sdd/constitution.md` |
| T-F0-08, T-F1-10 a T-F1-17 | `specs/TASK.md` |
