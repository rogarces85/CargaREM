---
type: stack
title: D-STACK — decisión de stack del portal (abierta)
description: Registro de la decisión pendiente sobre el stack del portal M7. No ratifica ninguna tecnología.
tags: [stack, decision, abierta, laravel, php]
timestamp: 2026-11-06T14:30:00Z
topic: stack
status: open
---

# D-STACK — decisión de stack del portal

> **Estado: abierta.** El usuario aún no decide. La constitución v1.0.0 deja el stack fuera del
> canon (principios 1–5) precisamente por esto. Este artículo reúne lo que falta para cerrarla;
> no elige por nadie.

## Por qué está abierta

`stack_modulo_envio.md` §2 y `backup_documentacion/MODULO_M7_PROFUNDIZADO.md` §11 proponen
**Laravel 8 + PHP 7.4 + MySQL 8 + AdminLTE**. Es una propuesta coherente con el servidor actual,
pero arrastra dos tecnologías sin soporte de seguridad (PHP 7.4 desde nov-2022, Laravel 8 desde
oct-2022) en una aplicación expuesta a internet con datos de salud. Eso es una decisión de riesgo,
no un detalle técnico.

## Opciones honestas

| # | Opción | Ventaja | Costo / riesgo |
|---|---|---|---|
| **A** | Laravel 8.x + PHP 7.4.3 + MySQL 8 + AdminLTE, como está propuesto | Corre en el servidor actual sin cambios de TI; ecosistema y documentación ya escritos; PhpSpreadsheet 1.29 lee `.xls/.xlsx/.xlsm` | Dos componentes EOL; parches de seguridad a mano; WAF o proxy como mitigación; migración futura más cara |
| **B** | Actualizar el servidor a PHP 8.2/8.3 y usar Laravel 11/12 (o el framework vigente) | Soporte de seguridad; menos deuda; el diseño M7 se traslada casi completo | Requiere que TI actualice Ubuntu/PHP; cambia el despliegue; retrasa el inicio hasta confirmación |
| **C** | Stack distinto (p. ej. Node/TypeScript + MySQL) elegido por el equipo | Sin herencia PHP; herramientas modernas | Hay que reescribir §11 del diseño, el contrato del puente y las dependencias de lectura de Excel; requiere runtime que hoy no existe en el servidor |

No hay una cuarta opción razonable: cualquier variante cae en A, B o C.

## Criterios para elegir

1. **Soporte de seguridad** del lenguaje y el framework durante la vida del portal (mínimo 3 años).
2. **Encaje real** en las restricciones de [`restricciones-servidor.md`](restricciones-servidor.md).
3. **Despliegue por FTP sin SSH**: ¿el stack produce un artefacto subible tal cual?
4. **Lectura de Excel legado** (`.xls`, `.xlsx`, `.xlsm`) sin ejecutar macros.
5. **Roles y permisos**, autenticación y 2FA disponibles sin servidor de aplicaciones.
6. **Conocimiento del equipo**: quién mantiene esto en 12 meses.
7. **Costo de la migración futura** si se elige A.

## Qué cierra D-STACK

1. TI del SS Osorno responde si se puede actualizar PHP/Ubuntu y si hay cron y `.htaccess`.
2. El responsable del proyecto elige A, B o C con los criterios de arriba.
3. Se registra en `02-DOCS/wiki/sdd/decisions.md` con fecha, opciones y motivo.
4. Se enmienda la constitución: **MAJOR**, porque agrega el canon de stack.
5. Se actualiza `stack_modulo_envio.md` §2 y, si cambia el framework, §11 del diseño M7.

## Mientras esté abierta

- No entra código de aplicación a `main` (principio 1).
- Sí se puede: documentar, diagramar, preparar *fixtures*, escribir el catálogo de errores,
  definir la máquina de estados y los contratos del puente.
- Los prototipos van en una rama descartable y no se fusionan.
