---
type: stack
title: Prototipo Laravel 12 — qué cambia y cuánto cuesta migrar
description: Resultado de levantar Laravel 12 sobre PHP 8.2 local. Versiones de dependencias, diferencias estructurales con Laravel 8, y el trabajo real que separa al portal actual del stack limpio.
tags: [stack, laravel-12, prototipo, migracion, php-82, seguridad]
timestamp: 2026-11-11T17:30:00Z
topic: stack
status: verificado
---

# Prototipo Laravel 12

> Rama `prototype/laravel-10` (nombre heredado; se probó **Laravel 12**, porque 10 y 11 siguen afectados por avisos). Carpeta `portal-l12/`. **Descartable: no se fusiona.**

---

## 1. Por qué se hizo

C-014 reabrió D-STACK y pidió PHP 8.2 a TI. Mientras llega la respuesta, se levantó un prototipo para responder tres preguntas con hechos en vez de con estimaciones:

1. ¿Corre un Laravel limpio en la máquina local?
2. ¿Siguen disponibles las cuatro dependencias del portal?
3. ¿Cuánto código real hay que rehacer?

---

## 2. Resultado

| Elemento | Valor |
|---|---|
| Framework | **Laravel 12.72.0** |
| PHP local | 8.2.12 (requisito del framework: `^8.2`) |
| `composer audit` | **«No security vulnerability advisories found»** |
| HTTP | 200 por Apache, en `http://localhost/Cargas/portal-l12/public` |
| Zona horaria y locale | `America/Santiago` · `es` |

**Laravel 10 y 11 no sirven.** El prototipo empezó en 10 y Composer lo rechazó: todos los parches hasta 10.50.4 arrastran avisos. En este servidor de paquetes solo **12.68+** y **13.27+** están limpios, y Laravel 13 exige PHP 8.3, que el servidor no tiene.

**El número que importa: PHP 8.2.** Es lo que pide Laravel 12, y es exactamente la versión que el servidor **ya corre** en el vhost de Observaciones.

---

## 3. Dependencias

Las cuatro del portal existen en versión moderna y sin avisos:

| Paquete | En Laravel 8 (PHP 7.4) | En Laravel 12 (PHP 8.2) |
|---|---|---|
| `spatie/laravel-permission` | 5.11.1 | **6.25** |
| `laravel/fortify` | 1.19.1 | **1.42** |
| `yajra/laravel-datatables-oracle` | 9.21.2 | **12.7** |
| `phpoffice/phpspreadsheet` | 1.30.8 | **5.11** |

El salto de PhpSpreadsheet es el más grande: 1.30 → 5.11. La API de lectura por rango y de evaluación de fórmulas cambia; hay que revisarla contra la documentación del validador, no traducirla a ciegas.

---

## 4. Qué se rehace y qué se conserva

Comparación de los dos árboles, sin contar `vendor/` ni assets.

### Desaparece en Laravel 12

| Archivo | Qué lo reemplaza |
|---|---|
| `app/Http/Kernel.php` | `bootstrap/app.php` con `->withMiddleware()` |
| `app/Console/Kernel.php` | `routes/console.php` |
| `app/Exceptions/Handler.php` | `bootstrap/app.php` con `->withExceptions()` |
| 8 middlewares en `app/Http/Middleware/` | Configurados en `bootstrap/app.php` |
| `Auth`, `Broadcast`, `Event`, `RouteServiceProvider` | `bootstrap/providers.php` |
| `config/broadcasting|cors|hashing|sanctum|view.php` | Valores por defecto del framework |
| `resources/lang/en/` | `lang/` en la raíz |
| `routes/api.php`, `routes/channels.php` | `php artisan install:api` los crea si hacen falta |
| `server.php`, `webpack.mix.js` | Vite (`vite.config.js`) |

### Aparece en Laravel 12

- `bootstrap/providers.php` — registro de providers.
- `vite.config.js` — el portal no lo usa (AdminLTE precompilado), se puede borrar.
- Migración base renombrada a `0001_01_01_000000_create_users_table`.

### Se conserva tal cual

- **`HealthController`** — PHP puro; el mismo archivo funciona en ambos.
- **`routes/web.php`** — idéntico.
- **Los assets de AdminLTE** — son estáticos; se copian.
- **Las migraciones del dominio** — sintaxis Eloquent, compatible.
- **Blade, policies, servicios y modelos** — sin cambios.

---

## 5. Dos trampas encontradas

### 5.1 `APP_TIMEZONE` no hace nada

En Laravel 12 el `config/app.php` publicado trae `'timezone' => 'UTC'` **fijo**. Poner `APP_TIMEZONE=America/Santiago` en el `.env` no cambia nada: la variable no se lee. Hay que editar `config/app.php`.

En Laravel 8 la variable tampoco existía, pero al menos el archivo era explícito. Aquí la trampa es que el `.env` **parece** funcionar y no funciona.

### 5.2 El `APP_URL` con subdirectorio vuelve a romper la suite

El mismo problema que ya se corrigió en `portal/`: con `APP_URL` apuntando a un subdirectorio, cada `$this->get('/ruta')` resuelve contra ese subdirectorio y devuelve 404. La corrección es la misma —`APP_URL=http://localhost` en `phpunit.xml`— y se traslada sin cambios.

Que el prototipo haya tropezado con lo mismo confirma que no era un accidente: es una propiedad de servir Laravel desde un subdirectorio.

---

## 6. Costo real de la migración

| Trabajo | Esfuerzo |
|---|---|
| Recrear el esqueleto | 1 comando |
| Repetir las 4 configuraciones (app, database, logging, phpunit) | Media hora |
| Portar `HealthController` y `routes/web.php` | 15 minutos |
| Copiar AdminLTE | 10 minutos |
| Migraciones del dominio (F1.3 en adelante) | Se escriben directo en L12 |
| Revisar la API de PhpSpreadsheet 5.x | Parte de F2, no de la migración |

**Conclusión:** lo construido hasta hoy se porta en menos de una hora. Lo que no se puede recuperar es el tiempo que se siga escribiendo sobre Laravel 8.

---

## 7. Estado del prototipo

| Verificación | Resultado |
|---|---|
| `php artisan --version` | 12.72.0 |
| `composer audit` | sin avisos |
| `php artisan migrate` | corre (SQLite por defecto) |
| HTTP por Apache | 200 |
| Zona horaria y locale | correctos tras editar `config/app.php` |
| Suite | 1 test falla por el `APP_URL` conocido; se corrige igual que en L8 |

El prototipo **no se fusiona**. Existe para que, si TI responde que sí, la decisión se ejecute en días y no en semanas.

