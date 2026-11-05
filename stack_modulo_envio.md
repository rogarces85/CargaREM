# Stack propuesto: Módulo de envío de estadísticas por establecimiento

**Estado:** propuesta provisional (no definitiva). Complementa la sección 2.10 de `informe.md`.
**Última actualización:** documento de trabajo.

---

## 1. Restricciones del entorno (confirmadas)

Fuente: `phpinfo` del servidor, consulta a MySQL, conexión FTP y respuestas del equipo.

| Ámbito | Dato confirmado | Implicancia |
|---|---|---|
| Servidor | Ubuntu 20.04, `estadisticas.ssosorno.cl` (HTTPS), IP interna 10.8.152.199 | Acceso público por HTTPS; el servidor está en red interna |
| Servidor web | Apache 2.4 prefork, `mod_php` | Un proceso PHP por conexión; sin PHP-FPM |
| PHP | 7.4.3 (fuera de soporte desde nov. 2022) | No se puede actualizar; se usa un framework compatible con 7.4 |
| Base de datos | MySQL 8.0.42, `utf8mb4`, modo `STRICT_TRANS_TABLES` | Compatible con Laravel 8; declarar `utf8mb4` explícitamente |
| Límites PHP | `upload_max_filesize=2M`, `post_max_size=8M`, `max_file_uploads=20`, `max_execution_time=1000`, `memory_limit=1024M` | Un archivo por petición; validación síncrona |
| Permisos de subida | Sin acceso a `php.ini`; posible `.htaccess` a confirmar | Límites bajo control del servidor |
| Acceso al servidor | Solo FTP (FileZilla), sin SSH | Despliegue por FTP; sin Composer ni Artisan en el servidor |
| Raíz FTP | Acceso a `/` (y a `/html` = `/var/www/html`); permite escritura y cambio de permisos | Código Laravel fuera de la carpeta pública |
| Correo | `sendmail` y SMTP en `localhost:25` | Notificaciones por correo son viables |
| Acceso externo | Público por HTTPS | Requiere medidas de seguridad de aplicación completas |
| Tamaño de archivos REM | 1.562 KB a 1.609 KB (≈1,6 MB) en la muestra | Caben en 2 MB, con margen limitado |
| Envío mensual completo | ~7 archivos (6 series + SAX) ≈ 11 MB | Supera `post_max_size`: subida secuencial, una petición por archivo |
| Cron / cola | No confirmado | Sin cola persistente; se evita dependencia de workers |

---

## 2. Stack propuesto

| Capa | Tecnología | Versión | Motivo |
|---|---|---|---|
| Lenguaje | PHP | 7.4.3 (existente) | Restricción del servidor |
| Framework | Laravel | 8.x (última compatible con PHP 7.4) | Compatibilidad; ver riesgo de soporte en sección 5 |
| Frontend | AdminLTE | 3.x (Bootstrap 4) | Requisito del equipo (dashboard) |
| Plantillas | Blade | Incluido en Laravel | Integración nativa con AdminLTE |
| Gráficos | Chart.js | Incluido en AdminLTE | Dashboard sin dependencias extra |
| Tablas | DataTables (paginación servidor) | Compatible con Bootstrap 4 | Listados grandes (registros, errores) |
| Autenticación | Laravel Breeze o Fortify | Compatible con Laravel 8 | Login, recuperación de contraseña |
| Roles y permisos | spatie/laravel-permission | v5.x (compatible con PHP 7.4) | Roles Encargado, Revisor, Administrador |
| Lectura Excel | PhpSpreadsheet | 1.29.x (compatible con PHP 7.4) | Lee `.xls`, `.xlsx`, `.xlsm` sin ejecutar macros |
| Base de datos | MySQL | 8.0.42 (existente) | Disponible; soporte concurrente |
| Correo | Laravel Mail vía `sendmail`/SMTP local | Incluido | Notificaciones de estado |
| Colas | No se usan | — | Sin cron ni worker confirmados |
| Almacenamiento | Disco local fuera de `public/` | — | Archivos con nombre generado |
| Despliegue | Build local + subida por FTP | — | Sin SSH |

### Estructura de despliegue propuesta

```
/var/www/
├── estadisticas/        ← código Laravel (no accesible desde la web)
│   ├── app/, config/, routes/, resources/, database/
│   ├── bootstrap/cache/ ← escritura
│   ├── storage/         ← escritura (archivos, logs, sesiones)
│   ├── vendor/          ← construido localmente
│   └── .env
└── html/                ← público
    ├── index.php        ← ajustado para apuntar a /var/www/estadisticas
    ├── .htaccess
    └── assets (AdminLTE, CSS, JS)
```

Tres ajustes técnicos:
- `html/index.php` debe apuntar a `/var/www/estadisticas/vendor/autoload.php` y `bootstrap/app.php`.
- `vendor/` se construye localmente con `composer.json` fijando `"platform": {"php": "7.4.3"}`, para que el resultado sea compatible con el servidor.
- Permisos de escritura en `storage/` y `bootstrap/cache/`; hay que verificar que el usuario FTP y `www-data` puedan escribir.

---

## 3. Decisiones de diseño derivadas

| Decisión | Razón |
|---|---|
| Un archivo por petición HTTP | `post_max_size=8M` impide enviar un mes completo en una sola petición |
| Validación síncrona dentro de la petición | No hay cola ni cron confirmados; un archivo de ~1,6 MB cabe en `max_execution_time=1000` |
| Sin SSH: migraciones generadas localmente | Se importan con phpMyAdmin o conexión remota (por confirmar) |
| Base de datos consolidada (no un `.mdb` por serie y año) | Concurrencia de varios encargados; consultas para el dashboard |
| Usuario MySQL dedicado para la aplicación | Principio de mínimo privilegio |
| Archivos fuera de `public/` | Evita acceso directo a documentos de salud |

---

## 4. Requisitos de seguridad (acceso desde internet)

- Login con contraseña robusta, bloqueo tras intentos fallidos y, de preferencia, segundo factor.
- Protección CSRF en todos los formularios.
- Validación del tipo real del archivo (contenido, no solo extensión); macros desactivadas.
- Límite de tasa (rate limiting) en login y subida.
- Aislamiento de datos por establecimiento en cada consulta (no solo en la vista).
- Auditoría de accesos y cambios de estado (RN-ENV-3).
- `APP_DEBUG=false` en producción; sin `info.php` ni páginas de diagnóstico.
- FTP con TLS obligatorio ("Requerir FTP explícito sobre TLS").
- Revisión legal de datos de salud (Ley 21.719 de protección de datos personales, vigencia prevista dic. 2026, y normativa sanitaria aplicable).

---

## 5. Riesgos abiertos

| # | Riesgo | Impacto | Mitigación propuesta |
|---|---|---|---|
| R1 | PHP 7.4 sin soporte de seguridad | Alto (público en internet) | Actualización a PHP 8.x si el servidor lo permite; mientras tanto, WAF o proxy inverso delante |
| R2 | Laravel 8 sin soporte desde oct. 2022 | Medio-alto | Mismo que R1; planificar migración a Laravel 11/12 cuando PHP se actualice |
| R3 | MySQL 8.0 llegó o llega a fin de soporte (abril 2026) | Medio | Planificar actualización a MySQL 8.4 LTS o MariaDB soportada |
| R4 | Ubuntu 20.04 sin soporte estándar | Medio | Confirmar con TI del SS Osorno si hay ESM o plan de actualización |
| R5 | Sin cron ni SSH confirmados | Medio | Operar sin cola; validar tiempos reales con archivos de prueba |
| R6 | Archivo cerca del límite de 2 MB | Medio | Medir con archivos de SAX y de series más grandes; solicitar `.htaccess` con `php_value` |
| R7 | Migración desde Access a MySQL | Alto | Proyecto separado: exportación de datos históricos y validación de conteos |
| R8 | Puente con CARGAS REM (escritorio) | Medio | Definir si el portal exporta lo aprobado o reemplaza la carga |
| R9 | Datos de salud expuestos a internet | Alto | Medidas de sección 4 y revisión legal |

---

## 6. Pendientes para cerrar el stack

1. Confirmar si se puede actualizar PHP o si el stack queda en 7.4 con Laravel 8.
2. Confirmar qué rutas puede ver el usuario FTP y si `.htaccess` con `php_value` funciona.
3. Definir nombre de base de datos y usuario MySQL dedicado.
4. Definir fuente de identidad de los encargados (cuentas propias, RUT, correo institucional).
5. Decidir el puente con CARGAS REM (exportación desde el portal o reemplazo de la carga).
6. Confirmar tamaño máximo real de los archivos (incluido SAX, cuando se documente).
7. Verificar con TI la política de contraseñas, segundo factor y registro de auditoría requeridos.

---

*Este documento es una propuesta de trabajo. La decisión final de stack se toma cuando se cierren los pendientes de la sección 6.*
