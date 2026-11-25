---
type: stack
title: Restricciones del servidor (evidencia)
description: Qué permite hoy el servidor de destino, separando lo confirmado de lo observado, lo contradicho y lo pendiente.
tags: [stack, servidor, restricciones, php, mysql, apache]
timestamp: 2026-11-06T20:30:00Z
topic: stack
---

# Restricciones del servidor

> **Evidencia previa a C-015. No es el canon.** Este diagnóstico del 2026-11-06 es el que abrió
> D-STACK y terminó en el pedido de PHP 8.2. El 2026-11-12 **TI confirmó PHP 8.2** en el vhost, y
> la Constitución v5.0.0 fija el canon: Laravel 12.72 + PHP 8.2 + Tailwind CSS v4.
>
> Se conserva porque explica **por qué** se pidió el cambio de versión y qué límites del servidor
> siguen vigentes: `upload_max_filesize=2M`, `post_max_size=8M`, sin `opcache`, sin SSH y
> despliegue por FTP.
>
> **Vigente:** [`sdd/constitution.md`](../sdd/constitution.md) §0 y
> [`docs/infra/solicitud-php-82-ti.md`](../../../docs/infra/solicitud-php-82-ti.md).

## 1. Confirmado por diagnóstico directo

Ejecutado en `https://estadisticas.ssosorno.cl/sist_envios/diagnostico.php` el 2026-11-06.

| Ámbito | Dato | Consecuencia |
|---|---|---|
| PHP | **7.4.3-4ubuntu2.29** (EOL 28-nov-2022) | **Solo corre código PHP 7.4**; un stack PHP 8 exige actualizar el servidor o PHP-FPM |
| SAPI | `apache2handler` | `mod_php` prefork: **una sola versión de PHP por instancia Apache** |
| Ubuntu | **20.04.6 LTS** (soporte estándar terminó 31-may-2025) | ESM hasta 2030 con Ubuntu Pro; PPA `ondrej/php` ya no construye para 20.04 |
| Kernel | 5.15.0-97-generic | HWE kernel de 20.04 |
| MySQL | 8.0.42 (confirmado por `sist_observaciones`) | Motor concurrente disponible; `utf8mb4` explícito |
| `upload_max_filesize` | **2M** | Un archivo por petición; mes completo (~11 MB) no cabe |
| `post_max_size` | 8M | Coherente con el límite de subida |
| `memory_limit` | 1024M | Validación síncrona posible; concurrencia limitada por RAM |
| `max_execution_time` | 1000s | Límite generoso para validación |
| `display_errors` | **1 (debería ser 0 en producción)** | Riesgo de fuga de información; corregir en `php.ini` |
| Extensiones cargadas | `pdo_mysql`, `mbstring`, `intl`, `zip`, `gd`, `xml`, `fileinfo`, `openssl`, `curl` | **Faltan**: `pdo_sqlite`, `opcache`, `bcmath` — requeridas por Laravel 13 |

## 2. Observado en otro vhost del mismo dominio

En `https://estadisticas.ssosorno.cl/sist_observaciones/` corre un sistema con:

| Señal | Valor | Lectura |
|---|---|---|
| PHP documentado | 8.2 | `deploy/setup-server.sh` instala `php8.2` y `libapache2-mod-php8.2` |
| Despliegue | `git`, `composer`, `systemctl` | Probablemente **PHP-FPM** o un **Apache distinto** |
| Cron | Worker/minuto + timer systemd | Cron disponible en ese entorno |

**Inferencia:** el Apache principal (este vhost) sirve PHP 7.4 vía `mod_php`. El sistema de Observaciones usa **PHP-FPM 8.2** o un contenedor aparte. No son el mismo runtime.

## 3. Corrección histórica

La versión inicial de este artículo y `stack_modulo_envio.md` reportaban PHP 7.4.3 como dato único.
La investigación previa asumió PHP 8.3 basándose en el sistema de Observaciones. **Esa asunción era incorrecta para este vhost.** El diagnóstico lo confirma: **PHP 7.4.3 es la realidad del Apache que servirá el portal M7**.

## 4. Sin confirmar (bloquea decisiones)

> **Actualización 2026-11-12:** TI confirmó PHP 8.2 para el vhost. Las dos primeras filas quedaron
> resueltas; el resto sigue abierto y se sigue operando por FTP.

| Dato | Por qué importa | Cómo se cierra |
|---|---|---|
| ~~¿Se puede actualizar Ubuntu/PHP?~~ | **Resuelto:** TI habilitó PHP 8.2 | — |
| ~~¿Se puede instalar PHP-FPM 8.2 para este vhost?~~ | **Resuelto:** es el runtime del despliegue | — |
| ¿Existe SSH para el usuario del proyecto? | Requerido para `composer`, `artisan`, migraciones | Respuesta de TI. Hoy se trabaja por FTP |
| ¿Se puede habilitar `opcache`? | Rendimiento: sin él, cada petición recompila | `apt install php8.2-opcache` |
| Cuota y ruta de respaldos | Define retención y operación | Respuesta de TI |
| Antivirus disponible (ClamAV) | Control de archivos maliciosos | Respuesta de TI |

## 5. Cómo se confirmó PHP-FPM disponible

Se preguntó a TI si había un pool PHP-FPM 8.2/8.3 corriendo en el servidor. **La respuesta fue sí:**
PHP 8.2 quedó habilitado para el vhost del portal, que es lo que sostiene el canon Laravel 12.72.

## 6. Lo que ninguna propuesta puede asumir

- Que el servidor se actualizará «mientras tanto».
- Que habrá SSH, Composer o Artisan en producción (probable, no confirmado).
- Que una cola o un worker resolverá el tiempo de validación.
- Que se puede subir un mes completo en una petición (`upload_max_filesize=2M` sigue vigente).
- Que Access (`Global.mdb`) es el almacén del portal: es fuente histórica, no destino concurrente.
- Que `opcache`, `pdo_sqlite` y `bcmath` están disponibles (hoy **no** lo están).
- Que el servidor compila la UI: no tiene Node. Se sube `portal/public/build/` ya compilado.