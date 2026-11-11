---
type: stack
title: Entorno local de desarrollo
description: Qué hay instalado en la máquina de trabajo, qué falta para Laravel 13 y por qué SQLite no reemplaza a MySQL en la validación.
tags: [stack, entorno, local, xampp, php, sqlite, mysql]
timestamp: 2026-11-06T19:00:00Z
topic: stack
---

# Entorno local de desarrollo

> Verificado el 2026-11-06 en la máquina de trabajo (XAMPP en `C:\xampp`).

## 1. Lo que hay hoy

| Componente | Versión real | ¿Sirve para el stack elegido? |
|---|---|---|
| PHP (XAMPP) | **8.2.12** | ❌ Laravel 13 exige 8.3+ |
| MySQL (XAMPP) | **MariaDB 10.4.32** | ❌ No es MySQL 8; no sirve como espejo de producción |
| Apache (XAMPP) | 2.4 | ✅ |
| Composer | vía XAMPP | ✅ |
| Node | no requerido | ✅ AdminLTE se usa precompilado |

Aclaración importante: el «PHP 8.2.12 observado» que aparece en la documentación del sistema de
Observaciones corresponde a **este XAMPP**, no al servidor. La versión del servidor sigue pendiente
de confirmación.

## 2. Requisito previo: PHP 8.3 o 8.4

Laravel 13 no arranca en 8.2. Tres caminos, en orden de menor fricción:

| Opción | Cómo | Costo |
|---|---|---|
| **A. Reemplazar el binario de PHP en XAMPP** | Descargar PHP 8.3/8.4 Thread Safe para Windows, reemplazar `C:\xampp\php`, ajustar `httpd.conf` | 30 minutos |
| **B. Laragon con PHP 8.3+** | Instalar Laragon y mover el proyecto | 1 hora |
| **C. Docker** | `php:8.4-apache` + `mysql:8.0` con `docker-compose` | 2 horas, pero reproducible |

La opción A es suficiente para L1. La C conviene cuando se llegue a la puerta de paridad.

## 3. SQLite y MySQL no son intercambiables

SQLite se usa para desarrollar rápido y para la suite corta. **No** reemplaza a MySQL en la
validación. Diferencias que importan en este proyecto:

| Aspecto | SQLite | MySQL 8 | Consecuencia |
|---|---|---|---|
| Tipado numérico | dinámico | estricto | Puede ocultar errores de redondeo con tolerancia 0 |
| `utf8mb4_0900_ai_ci` | no existe | sí | Cambia comparaciones con acentos y mayúsculas |
| `lockForUpdate()` | no-op | sí | La concurrencia no se puede probar en SQLite |
| `STRICT_TRANS_TABLES` | no aplica | sí | Truncamientos silenciosos distintos |
| `fullText()` | no soportado | sí | Evitar en el modelo |
| `after()` / `comment()` | ignorados | aplican | Ruido en migraciones |

Además, el MySQL de XAMPP es **MariaDB 10.4**, que tampoco es MySQL 8: no tiene
`utf8mb4_0900_ai_ci` y maneja `JSON` distinto.

## 4. Entorno recomendado

| Capa | Herramienta | Uso |
|---|---|---|
| PHP | 8.3 o 8.4 | Desarrollo y tests |
| Base rápida | SQLite (`database/database.sqlite`) | Suite de tests y navegación |
| Base de paridad | MySQL 8.0 y 8.4 en Docker | Migraciones, concurrencia, paridad numérica |
| Servidor web | `php artisan serve` | Desarrollo |
| Assets | AdminLTE 4 `dist/` | Sin Node, sin Vite |
| Auth | Laravel Fortify | Deja abierta la puerta al 2FA |

Comandos de referencia para la base de paridad:

```
docker run --rm -d --name mysql80 -e MYSQL_ROOT_PASSWORD=secret -p 3307:3306 mysql:8.0
docker run --rm -d --name mysql84 -e MYSQL_ROOT_PASSWORD=secret -p 3308:3306 mysql:8.4
```

## 5. Contrato de compatibilidad

Reglas para que el mismo código corra en SQLite y en MySQL:

1. Nada de SQL crudo con sintaxis MySQL (`ON DUPLICATE KEY`, `DATE_FORMAT`, `LIMIT ?,?`).
2. `upsert()` en vez de `REPLACE INTO`.
3. Nada de `fullText()`.
4. `enum` del esquema → `string` + validación en `FormRequest`.
5. Decimales con cast `decimal:2`; comparación en PHP, no en SQL.
6. Fechas con `date`/`datetime`; nunca funciones de fecha del motor.
7. `lockForUpdate()` documentado como no-op en SQLite.

## 6. Qué se puede y qué no se puede probar en local

| Aspecto | SQLite | Dónde se valida de verdad |
|---|---|---|
| DDL, claves foráneas, índices | ✅ | ambos |
| Máquina de estados y guardas | ✅ | ambos |
| Policies y aislamiento | ✅ | ambos |
| Lectura de Excel | ✅ | ambos |
| UI completa con AdminLTE | ✅ | ambos |
| Paridad numérica tolerancia 0 | ❌ | solo MySQL |
| Concurrencia y locks | ❌ | solo MySQL |
| Colaciones y acentos | ❌ | solo MySQL |
| Límite de subida y timeouts | ❌ | solo servidor |
| SMTP | ❌ | solo servidor |
