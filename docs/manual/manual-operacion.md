# Manual de operación

Para quien mantiene el portal desplegado: instalación, respaldos, registros y
qué hacer cuando algo falla.

## Requisitos

| Pieza | Versión | Nota |
|---|---|---|
| PHP | **8.2** | Confirmado por TI el 12-nov-2026; ver `docs/infra/solicitud-php-82-ti.md` |
| MySQL | 8.0.42 | `utf8mb4`, `STRICT_TRANS_TABLES` |
| Apache | 2.4 | `mod_php` o PHP-FPM 8.2 |
| Composer | 2.x | Solo para instalar; no queda en el servidor |

Extensiones de PHP necesarias: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`,
`gd` o `imagick` (para los reportes), `openssl`.

## Instalación desde cero

```bash
cd /var/www/cargas/portal

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# Editar .env: base, correo (recuperación de clave), URL, REM_CATALOGO_DIR
php artisan migrate --force
php artisan db:seed --force          # roles, permisos, Servicio y catálogo si hay CSV

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Variables que no pueden quedar mal

| Variable | Valor en producción |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://estadisticas.ssosorno.cl/cargas` |
| `DB_DATABASE` | `cargas_rem` |
| `MAIL_MAILER` | `smtp` (solo para recuperación de contraseña) |
| `REM_CATALOGO_DIR` | ruta a los CSV del Minsal para sembrar el catálogo |

## Despliegue por FTP

El servidor no tiene SSH confirmado, así que se sube por FTP:

1. Comprimir el proyecto **sin** `vendor/`, `node_modules/`, `storage/logs/*`
   ni `.env`.
2. Subir y descomprimir donde corresponda.
3. Subir el `.env` de producción por separado (nunca dentro del ZIP).
4. Ejecutar `php artisan migrate --force` desde el panel o por cron único.
5. Limpiar cachés: `php artisan optimize:clear` y volver a cachear.

## Comprobar que está sano

```bash
curl -s https://<servidor>/health
```

```json
{"status":"ok","checks":{"db":"ok","storage":"ok"},"entorno":"production","version":"12.72.0"}
```

Si responde 503, alguna comprobación falló. El detalle **no** se muestra al
público: está en el registro.

## Respaldos

Script: `01-TOOLS/backup/backup.sh` (Linux) o `respaldo.ps1` (Windows).

```bash
# Diario a las 2:15
15 2 * * * /var/www/cargas/01-TOOLS/backup/backup.sh >> /var/log/cargas-backup.log 2>&1
```

Deja `db_AAAA-MM-DD_HHMM.sql` y `storage_AAAA-MM-DD_HHMM.tar.gz`, y borra lo
que tenga más de 30 días.

### Restaurar

```bash
mysql -u root -e "CREATE DATABASE cargas_rem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root cargas_rem < backup/db_2026-11-12_0215.sql
tar -xzf backup/storage_2026-11-12_0215.tar.gz -C portal/storage/app/private
php artisan migrate:status   # todas las migraciones deben figurar como Ran
```

**Probado:** restaurar el respaldo real tomó 21 s y dejó las tablas con sus
filas (el esquema v2.0 tiene 31 tablas).

## Registros

| Canal | Archivo | Qué guarda |
|---|---|---|
| `validacion` | `storage/logs/validacion-*.log` | Cada validación: envío, serie, códigos |
| `seguridad` | `storage/logs/seguridad-*.log` | Accesos fallidos, bloqueos |
| general | `storage/logs/laravel-*.log` | Errores y avisos del portal |

Rotan por día y se conservan 90 días. **No guardan contenido de celdas ni el
texto de las observaciones**: solo identificadores y códigos.

## Tareas programadas

| Tarea | Frecuencia | Efecto |
|---|---|---|
| Respaldo | diaria | Copia base y archivos |
| Feriados del año siguiente | anual, antes de enero | Cargarlos en **Feriados** y revisar **Calendario de plazos** |
| `queue:work` | no aplica | El portal no usa colas |

### Calendario de plazos

El plazo de cada período es el **quinto día hábil del mes siguiente** (D-029).
Los días no hábiles son sábados, domingos y los feriados de la tabla `feriados`.

```bash
php artisan rem:calendario 2026              # muestra los doce plazos del año
php artisan rem:calendario 2026 --actualizar # crea los períodos que faltan y corrige fechas
```

Si un año no tiene feriados cargados, el comando lo advierte y la pantalla
**Calendario de plazos** muestra el aviso: el plazo se calcula solo con fines de
semana y puede quedar 1 o 2 días más largo. Los feriados se cargan a mano
(pantalla **Feriados**) porque la lista oficial cambia cada año.

## Problemas comunes

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| `/health` da 503 | Base caída o credenciales | Revisar `.env` y el servicio de MySQL |
| 500 en todas las páginas | `storage/` o `bootstrap/cache/` sin permiso de escritura | `chown -R www-data:www-data storage bootstrap/cache` |
| «La validación pide plantilla» | Catálogo no sembrado | `php artisan db:seed --class=CatalogoSeeder` con `REM_CATALOGO_DIR` |
| Una validación tarda ~35 s | Excel de 1,5 MB y 30 hojas | Es el comportamiento esperado; el límite es 90 s |
| «Hay 2 validaciones en curso» | Semáforo lleno | Reintentar; el límite protege la memoria |
| No llega el correo de recuperación | SMTP mal configurado | Probar con `MAIL_MAILER=log` y mirar el registro; el administrador puede asignar clave |
| El disco crece por el diccionario | La carga del catálogo reemplaza 185.526 filas | No se acumulan snapshots: `diccionario` es una sola tabla |

## Contactos

| Tema | Quién |
|---|---|
| Servidor, PHP, permisos | TI del Servicio de Salud |
| Catálogos del Minsal | Referente de Estadísticas |
| Uso del portal | Unidad de Estadísticas |

## Pendientes conocidos

| Tema | Riesgo |
|---|---|

| Paridad con el ValidadorRem sin verificar | El motor aplica las reglas documentadas, pero no está probado que coincida con el sistema anterior |
| Sin snapshot de catálogo por archivo | Revalidar usa el catálogo vigente; se conservan hallazgos, hash y `validado_en` |
| CSP permite estilos y scripts en línea | Se mitiga moviendo el JavaScript a archivos propios |
