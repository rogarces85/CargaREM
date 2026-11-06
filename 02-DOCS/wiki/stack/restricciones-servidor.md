---
type: stack
title: Restricciones del servidor (evidencia)
description: Qué permite hoy el servidor de destino, separando lo confirmado de lo observado, lo contradicho y lo pendiente.
tags: [stack, servidor, restricciones, php, mysql, apache]
timestamp: 2026-11-06T19:00:00Z
topic: stack
---

# Restricciones del servidor

> **Esto es evidencia, no una elección de stack.** La elección ya está tomada en
> [`decision-stack.md`](decision-stack.md). Este artículo registra qué se sabe, qué se corrigió y
> qué falta confirmar.

## 1. Confirmado por verificación directa

Verificado contra `https://estadisticas.ssosorno.cl/sist_observaciones/` el 2026-11-06.

| Ámbito | Dato | Consecuencia |
|---|---|---|
| Dominio | `estadisticas.ssosorno.cl` por HTTPS | Acceso público |
| Servidor web | Apache, versión oculta (`ServerTokens Prod`) | Buena práctica; no se filtra la versión |
| `X-Powered-By` | ausente | La versión de PHP no se puede leer desde HTTP |
| Cookies | `secure; HttpOnly; SameSite=Lax` | Configuración endurecida en uso |
| `.htaccess` | `X-Frame-Options: DENY` aplicado | `AllowOverride` está activo: los límites de PHP son configurables |
| Estructura | Aplicación en subdirectorio `/sist_observaciones/` | Se pueden alojar varios sistemas sin tocar la raíz |
| Raíz `/` | Sitio DEIS en Bootstrap 4 | Convive un sistema legado con uno moderno |
| MySQL | `10.8.152.199`, `observaciones_rem`, `utf8mb4` | Mismo motor que usará el portal |
| Subidas | Import de Excel de hasta 10 MB documentado | El límite de 2 MB no es estructural |
| Cron | Worker cada minuto, timer systemd y `cron.daily` | Hay cron disponible |
| Despliegue | `git`, `composer`, `systemctl` en la documentación de ese sistema | Probablemente hay SSH; confirmar con TI |

## 2. Observado y pendiente de confirmar

| Dato | Estado | Cómo se cierra |
|---|---|---|
| Versión exacta de PHP del vhost de Cargas | **Se asume 8.3** | Diagnóstico temporal (ver §4) |
| Existencia de SSH para el usuario del proyecto | Probable | Respuesta de TI |
| `upload_max_filesize` real de ese vhost | Configurable | El mismo diagnóstico |
| Cuota y ruta de respaldos | Pendiente | Respuesta de TI |
| Antivirus disponible (ClamAV) | Pendiente | Respuesta de TI |
| Versión de Ubuntu del host | Probable 22.04 | El mismo diagnóstico (`/etc/os-release`) |

## 3. Corrección importante respecto de la versión anterior

La versión previa de este artículo afirmaba como *confirmado* que el servidor tenía **PHP 7.4.3** y
un techo de **2 MB** por subida. Ambos datos quedaron contradichos:

- En el mismo dominio corre un sistema con **PHP 8.2** documentado y desplegado.
- Apache con `mod_php` solo admite **una versión de PHP por instancia**: si ese sistema corre en
  8.2, el Apache sirve 8.2 (salvo PHP-FPM por vhost, que también deja 8.2 disponible).
- El límite de subida es configuración de `php.ini`/`.htaccess`, no una propiedad del servidor.

El dato de «7.4.3» proviene de un `phpinfo` antiguo o de otro host. **No debe usarse como
restricción de diseño.**

## 4. Cómo confirmar la versión de PHP sin SSH

Subir un archivo temporal a la carpeta del proyecto y abrirlo con un token:

```php
<?php
// diagnostico.php — borrar inmediatamente después de leerlo.
$token = 'PON-AQUI-32-CARACTERES-ALEATORIOS';
if (!hash_equals($token, (string)($_GET['k'] ?? ''))) { http_response_code(404); exit; }
header('Content-Type: application/json; charset=UTF-8');

$ext = ['pdo_mysql','pdo_sqlite','mbstring','intl','zip','gd','xml',
        'fileinfo','openssl','curl','opcache','bcmath'];
$out = [
  'PHP_VERSION' => PHP_VERSION,
  'PHP_SAPI' => PHP_SAPI,
  'PHP_OS' => PHP_OS,
  'KERNEL' => php_uname('r'),
  'OS_RELEASE' => @file_get_contents('/etc/os-release'),
  'upload_max_filesize' => ini_get('upload_max_filesize'),
  'post_max_size' => ini_get('post_max_size'),
  'memory_limit' => ini_get('memory_limit'),
  'max_execution_time' => ini_get('max_execution_time'),
  'display_errors' => ini_get('display_errors'),
  'extensiones' => [],
];
foreach ($ext as $e) { $out['extensiones'][$e] = extension_loaded($e); }
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
```

Reglas de uso:

1. Nombre de archivo aleatorio o token largo; nunca `phpinfo.php`.
2. Abrirlo una vez, guardar el JSON y **borrarlo en la misma sesión**.
3. No commitearlo. La constitución prohíbe páginas de diagnóstico en producción (principio 20).
4. Si el resultado no es 8.3 o superior, revisar el escenario de respaldo en `decision-stack.md`.

## 5. Lo que ninguna propuesta puede asumir

- Que habrá SSH, Composer o Artisan en producción (probable, no confirmado).
- Que una cola o un worker resolverá el tiempo de validación.
- Que se puede subir un mes completo en una petición.
- Que Access (`Global.mdb`) es el almacén del portal: es fuente histórica, no destino concurrente.
- Que el servidor se actualizará «mientras tanto».
