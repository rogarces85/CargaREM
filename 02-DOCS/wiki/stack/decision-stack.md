---
type: stack
title: D-STACK — decisión de stack del portal (cerrada)
description: Decisión ratificada sobre el stack del portal M7, con las opciones descartadas y las condiciones de la elección.
tags: [stack, decision, cerrada, laravel, php, mysql, adminlte]
timestamp: 2026-11-06T19:00:00Z
topic: stack
status: closed
---

# D-STACK — decisión de stack del portal

> **Estado: CERRADA el 2026-11-06.**
> Decisión: **Opción A — Laravel 13 + PHP 8.3 (objetivo 8.5) + MySQL 8 + AdminLTE 4.**
> Registro: `02-DOCS/wiki/sdd/decisions.md` (C-007) y constitución v2.0.0.
> Comparación completa: [`opciones-stack.md`](opciones-stack.md).

## 1. Canon ratificado

| Capa | Decisión | Nota |
|---|---|---|
| Lenguaje | PHP 8.3 mínimo; 8.5 cuando el servidor se actualice | Laravel 13 admite 8.3–8.5 |
| Framework | Laravel 13.x | Actualización mayor anual, barata |
| Base de datos (producción) | MySQL 8 | 8.0 hoy (EOL), objetivo 8.4 LTS |
| Base de datos (desarrollo) | SQLite | Solo local y suite rápida |
| Base de datos (paridad) | MySQL 8 real en Docker | No MariaDB, no SQLite |
| Interfaz | AdminLTE 4.x, `dist/` precompilado | Sin Node en producción |
| Autenticación | Laravel Fortify | Deja abierta la puerta al 2FA |
| Roles y permisos | `spatie/laravel-permission` v8 | Tests positivos y negativos por permiso |
| Excel | PhpSpreadsheet 5.x | Paridad obligatoria con *golden files* |
| Tests | PHPUnit o Pest | Sin umbral de cobertura |
| Despliegue | Subdirectorio `/sist_envios/` | Igual que el sistema de Observaciones |

## 2. Por qué A y no otra

1. **Es la idea original del usuario** —PHP + AdminLTE + MySQL— sin las versiones muertas.
2. **El diseño M7 ya está escrito para Laravel.** Se traslada cambiando versiones, no arquitectura.
3. **Es la más rápida de construir** con un desarrollador: 14–20 semanas.
4. **El ecosistema cubre** RBAC, 2FA, Excel, tablas y auditoría sin inventar nada.
5. **Queda soportada hasta 2029** sin migraciones de plataforma.

## 3. Opciones descartadas

| Opción | Por qué no |
|---|---|
| **B. Laravel 8 + PHP 7.4 + MySQL 8.0 + AdminLTE 3** | Cuatro componentes EOL. Su único argumento —«es lo único que corre en el servidor»— quedó contradicho: el servidor ejecuta PHP 8.2 para otro sistema. Migración futura de 6–10 semanas |
| **C. Symfony 7.4 LTS** | Técnicamente correcta y con mejor LTS, pero exige reescribir §11 del diseño y suma 3–4 semanas sin beneficio proporcional |
| **D. CodeIgniter 4.7** | Viable, pero auth, RBAC y auditoría se construyen a mano; sin LTS |
| **E. Slim 4 + Twig** | Se construye el framework. 20–30 semanas para un alcance que Laravel resuelve en 14–20 |
| **Node / Next.js** | No hay runtime Node en el servidor y el despliegue es por FTP |

## 4. Condiciones de la decisión

La elección se sostiene solo si se cumplen estas condiciones. Si alguna falla, se reabre D-STACK:

1. **PHP del servidor ≥ 8.3.** Si el diagnóstico devuelve 8.2, se cae a **Laravel 12** (soporte hasta feb-2027) y se registra la caducidad. Si devuelve 7.4, se reabre la decisión.
2. **Existe una vía de despliegue.** Con SSH: `git` + `composer` + `artisan`. Sin SSH: artefacto completo por FTP y una ruta de migración protegida de un solo uso.
3. **MySQL sube a 8.4 antes de la salida a producción.** 8.0 está EOL desde abril 2026.
4. **La paridad se valida en MySQL**, nunca solo en SQLite.

## 5. Qué queda abierto

| Tema | Dónde se decide |
|---|---|
| Fuente de identidad, 2FA, política de contraseñas | D1, D10 |
| Plazo mensual, series obligatorias, tratamiento de SAX | D4, D5, D6 |
| Puente con CARGAS REM | D7 |
| Retención, respaldos, antivirus | D9, D12, D13 |
| Migración histórica | D14 |

D11 (PHP/Laravel: quedarse o actualizar) **queda resuelta** por esta decisión.

## 6. Consecuencias documentales

- `stack_modulo_envio.md` §2 queda superado: proponía Laravel 8 / PHP 7.4.
- `backup_documentacion/MODULO_M7_PROFUNDIZADO.md` §11.7 requiere actualización de versiones.
- La constitución pasa a v2.0.0 con el canon de stack.
