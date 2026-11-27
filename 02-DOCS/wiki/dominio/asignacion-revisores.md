---
type: dominio
title: Asignación de establecimientos y alcance
description: Cómo se reparte la revisión entre el Equipo REM, qué ve cada rol y por qué el alcance se aplica en la consulta.
tags: [dominio, roles, alcance, equipo-rem, asignacion, seguridad]
timestamp: 2026-11-26T12:00:00Z
topic: dominio
---

# Asignación de establecimientos y alcance

> **Decisiones:** D-041 (nombre del rol) y D-042 (alcance por asignación).
> **Requisitos:** RF-042, R-ALC-01 a R-ALC-03.
> **Implementación:** `App\Dominio\Alcance`, tabla `usuario_establecimiento`.

## 1. El modelo, en una frase

El administrador reparte establecimientos. Cada persona alcanza **exactamente**
lo que tiene asignado; el administrador alcanza todo; y quien no tiene
asignación **no ve nada** —con un aviso— en lugar de verlo todo.

## 2. Los roles

| Clave interna | Nombre en pantalla | Alcance | Qué hace |
|---|---|---|---|
| `encargado` | Encargado de establecimiento | Sus establecimientos | Sube, corrige, pide apertura de plazo |
| `revisor` | **Equipo REM** | Sus establecimientos asignados | Revisa, aprueba, reabre, pide nueva subida, usa el Validador |
| `admin` | Administrador | Todo | Usuarios, asignaciones, catálogos, períodos, aperturas, auditoría |

**La clave `revisor` no cambia.** Cambian el nombre visible y el alcance. El
nombre se resuelve en `App\Support\RolUi::etiqueta()`: un solo lugar para toda
la interfaz.

## 3. Reglas

| ID | Regla |
|---|---|
| **R-ALC-01** | El revisor solo alcanza lo asignado. Sin asignación no alcanza **ninguno**; nunca se cae a «todo». |
| **R-ALC-02** | El encargado alcanza lo suyo; el administrador, todo. |
| **R-ALC-03** | Un establecimiento puede tener varios revisores. El primero que actúa queda como `revisor_id` del envío. |
| **R-ALC-04** | Sin asignación: la bandeja y el panel muestran un mensaje y el administrador recibe un aviso. |
| **R-ALC-05** | El alcance se aplica en la **consulta**, no en la vista. |

## 4. Qué se ve afectado

Un solo lugar decide, y lo usan todos estos lectores:

| Lector | Qué resuelve con `Alcance` |
|---|---|
| `Envio::scopeForUser` | Qué envíos puede ver y operar |
| `CompletitudService` | Qué establecimientos entran en la matriz |
| `RevisionController` | Qué establecimientos ofrece el filtro |
| `CompletitudController` | Idem |
| `EnvioController` | Qué establecimientos ofrece el alta y el filtro |
| `PanelService` | Qué cifras cuenta |
| `AperturaController` | Qué aperturas puede ver y pedir |

> Antes de la v3 esta regla estaba escrita en **cinco** lugares y no coincidían:
> dos daban al revisor todo el Servicio y tres filtraban por `servicio_id`. Es
> el mismo patrón que ya había fallado con la matriz de series.

## 5. Casos que resuelve el reparto

| Caso | Comportamiento |
|---|---|
| Establecimiento sin revisor | Su estadística no le llega a nadie del equipo: el panel del administrador lo advierte y el aviso de subida va al administrador |
| Revisor nuevo | Nace sin establecimientos; no ve datos hasta que se le asigne |
| Revisor desactivado | No recibe avisos (`activo = false`), pero su asignación se conserva |
| Dos revisores en el mismo establecimiento | Ambos ven y reciben; el primero que actúa queda como revisor del envío |

## 6. Trazabilidad

| Elemento | Dónde |
|---|---|
| Regla | `portal/app/Dominio/Alcance.php` |
| Etiquetas de rol | `portal/app/Support/RolUi.php` |
| Pantalla de reparto | `portal/app/Http/Controllers/Admin/AsignacionController.php` y `resources/views/admin/asignaciones/` |
| Asignación en el usuario | `portal/resources/views/admin/usuarios/form.blade.php` |
| Aviso al administrador | `portal/app/Services/Avisar.php` (`sinAsignacion`) |
| Tests | `portal/tests/Feature/AsignacionRevisoresTest.php` |
