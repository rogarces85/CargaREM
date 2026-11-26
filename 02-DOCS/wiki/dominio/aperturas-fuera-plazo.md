---
type: dominio
title: Aperturas de fuera de plazo
description: Cómo se pide, aprueba y vence una apertura para cargar un período cerrado, y por qué no cambia el estado del período.
tags: [dominio, plazos, aperturas, excepciones, periodo]
timestamp: 2026-11-26T12:00:00Z
topic: dominio
---

# Aperturas de fuera de plazo

> **Decisión:** D-044. **Requisitos:** RF-043.
> **Implementación:** `App\Services\AperturaService`,
> `App\Http\Controllers\AperturaController`, tabla `aperturas`.

## 1. Qué es y qué no es

Una **apertura** es una ventana con motivo que habilita a un establecimiento a
cargar un período que, por su estado, no acepta cargas.

**No es** un cambio de estado del período. Un período `cerrado` sigue cerrado
para los demás establecimientos; la apertura abre la puerta de una oficina, no
cambia el horario del edificio.

## 2. El flujo

| Paso | Quién | Qué pasa |
|---|---|---|
| 1 | Encargado | Pide la apertura con establecimiento, período y motivo (≥ 15 caracteres) |
| 2 | Sistema | Avisa al administrador y crea la fila en `pendiente` |
| 3 | Administrador | Resuelve con motivo (aprobar o rechazar) y, si aprueba, fija hasta cuándo |
| 4 | Sistema | Avisa al establecimiento; mientras la ventana esté vigente, la carga se permite |
| 5 | — | Vencida la fecha, el bloqueo vuelve solo |

El administrador también puede **abrir por su cuenta** (origen `admin`), para un
establecimiento o para **todos**, con motivo y fecha de término.

## 3. Reglas

| ID | Regla |
|---|---|
| **R-APL-01** | Solo el administrador resuelve y solo él abre por su cuenta. |
| **R-APL-02** | El motivo de la solicitud y el de la resolución son obligatorios. |
| **R-APL-03** | La ventana vale para un establecimiento (o todos) y un período, hasta su `vigente_hasta`. |
| **R-APL-04** | La apertura no cambia el estado del período: habilita a ese establecimiento. |
| **R-APL-05** | Vencida la apertura, el bloqueo vuelve sin intervención (la comprobación es por fecha, no por proceso). |
| **R-APL-06** | Una solicitud pendiente del mismo establecimiento y período se reutiliza. |

## 4. Qué habilita exactamente

`EnvioService` consulta `AperturaService::permite()` antes de rechazar por
período cerrado o en revisión:

- **Período `cerrado`**: se puede crear el envío y subir archivos mientras la
  ventana esté vigente.
- **Período `en_revision`**: se aplica la misma excepción para un envío nuevo o
  una subida que no sea corrección de un observado. Un envío `observado` sigue
  su curso normal sin necesitar apertura: la corrección ya la pidió el flujo.
- **Administrador**: no necesita apertura nunca.

El envío creado al amparo de una apertura guarda `apertura_id`. La marca
`fuera_de_plazo` **se conserva**: es un hecho, no una sanción. Lo que cambia es
que la pantalla puede explicar con qué autorización entró.

## 5. Avisos

| Evento | Destinatario |
|---|---|
| `apertura_solicitada` | Administradores |
| `apertura_aprobada`, `apertura_rechazada` | Usuarios del establecimiento |
| `apertura_abierta` (por cuenta propia) | Usuarios del establecimiento (o de todos) |

## 6. Trazabilidad

| Elemento | Dónde |
|---|---|
| Migración | `portal/database/migrations/2026_11_26_100003_aperturas_fuera_plazo.php` |
| Modelo | `portal/app/Models/Apertura.php` |
| Servicio | `portal/app/Services/AperturaService.php` |
| Policy | `portal/app/Policies/AperturaPolicy.php` |
| Pantalla | `portal/resources/views/aperturas/index.blade.php` |
| Comando | `php artisan rem:aperturas:cerrar` |
| Tests | `portal/tests/Feature/AperturaFueraPlazoTest.php` |
