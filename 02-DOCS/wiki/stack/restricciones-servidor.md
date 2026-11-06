---
type: stack
title: Restricciones del servidor (evidencia)
description: Lo que el servidor de destino permite hoy. Es restricción confirmada, no decisión de stack.
tags: [stack, servidor, restricciones, php, mysql]
timestamp: 2026-11-06T14:30:00Z
topic: stack
---

# Restricciones del servidor

> **Esto es evidencia, no una elección de stack.** La elección está abierta en
> [`decision-stack.md`](decision-stack.md) (D-STACK). Cualquier candidato debe cumplir esta tabla
> o la restricción se cambia primero con TI y se registra en
> `02-DOCS/wiki/sdd/decisions.md`.

Fuente original: `stack_modulo_envio.md` §1, obtenido de `phpinfo`, consulta a MySQL, conexión FTP
y respuestas del equipo. Este artículo lo resume para que la constitución pueda citarlo.

## Confirmado

| Ámbito | Dato | Consecuencia para cualquier stack |
|---|---|---|
| Servidor | Ubuntu 20.04, `estadisticas.ssosorno.cl`, IP interna 10.8.152.199 | Acceso público por HTTPS; el servidor vive en red interna |
| Servidor web | Apache 2.4 prefork con `mod_php` | Un proceso PHP por conexión; no hay PHP-FPM ni proxy de aplicación |
| PHP | 7.4.3, sin soporte desde noviembre 2022 | Solo corre código PHP 7.4; un stack PHP 8 exige cambiar el servidor |
| Base de datos | MySQL 8.0.42, `utf8mb4`, `STRICT_TRANS_TABLES` | Motor concurrente disponible; `utf8mb4` explícito |
| Subidas | `upload_max_filesize=2M`, `post_max_size=8M`, `max_file_uploads=20` | Un archivo por petición; un mes completo (~11 MB) no cabe en una sola |
| Ejecución | `max_execution_time=1000`, `memory_limit=1024M` | Validación síncrona posible; concurrencia limitada por RAM |
| Acceso | Solo FTP con TLS (FileZilla), sin SSH | Sin Composer, Artisan, cron por shell ni reinicio de servicios en destino |
| Raíz FTP | `/` y `/html` (= `/var/www/html`), con escritura y cambio de permisos | Código fuera de la carpeta pública; assets dentro |
| Correo | `sendmail` y SMTP en `localhost:25` | Notificaciones por correo viables, sin colas |
| Archivos REM | 1.562–1.609 KB por archivo | Cabe en 2 MB con margen limitado |
| Envío mensual | ~7 archivos ≈ 11 MB | Subida secuencial obligatoria |

## Sin confirmar (bloquea decisiones)

| Dato | Por qué importa | Cómo se cierra |
|---|---|---|
| Si se puede actualizar PHP | Define si el stack puede ser PHP 8 / framework moderno | Respuesta de TI del SS Osorno |
| Si `.htaccess` acepta `php_value` | Permitiría subir el límite de 2 MB | Prueba en el servidor |
| Si existe cron o programador de tareas | Habilita recordatorios y limpieza | Respuesta de TI |
| Ruta y cuota de respaldos | Define retención y operación | Respuesta de TI |
| Antivirus disponible (ClamAV) | Control de archivos maliciosos | Respuesta de TI |

## Lo que ninguna propuesta puede asumir

- Que el servidor se actualizará "mientras tanto".
- Que habrá SSH, Composer o Artisan en producción.
- Que una cola o un worker resolverá el tiempo de validación.
- Que se puede subir un mes completo en una petición.
- Que Access (`Global.mdb`) es el almacén del portal: es fuente histórica, no destino concurrente.
