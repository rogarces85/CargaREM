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

# Editar .env: base, correo, URL, PAQUETE_HMAC_KEY
php artisan migrate --force
php artisan db:seed --force          # roles, permisos y datos mínimos
php artisan catalogos:importar /ruta/al/snapshot --activar

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
| `MAIL_MAILER` | `smtp` |
| `PAQUETE_HMAC_KEY` | una cadena larga y secreta |

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

**Probado:** restaurar el respaldo real tomó 21 s y dejó las 33 tablas con sus
filas.

## Registros

| Canal | Archivo | Qué guarda |
|---|---|---|
| `validacion` | `storage/logs/validacion-*.log` | Cada validación: envío, serie, códigos |
| `seguridad` | `storage/logs/seguridad-*.log` | Accesos fallidos, bloqueos |
| `correo` | `storage/logs/correo-*.log` | Avisos enviados y fallidos |
| general | `storage/logs/laravel-*.log` | Errores |

Rotan por día y se conservan 90 días. **No guardan contenido de celdas ni el
texto de las observaciones**: solo identificadores y códigos.

## Tareas programadas

| Tarea | Frecuencia | Efecto |
|---|---|---|
| Respaldo | diaria | Copia base y archivos |
| `queue:work` | no aplica | El portal no usa colas |

## Problemas comunes

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| `/health` da 503 | Base caída o credenciales | Revisar `.env` y el servicio de MySQL |
| 500 en todas las páginas | `storage/` o `bootstrap/cache/` sin permiso de escritura | `chown -R www-data:www-data storage bootstrap/cache` |
| «No hay catálogo activo» | Falta importar | `php artisan catalogos:importar ... --activar` |
| Una validación tarda ~35 s | Excel de 1,5 MB y 30 hojas | Es el comportamiento esperado; el límite es 90 s |
| «Hay 2 validaciones en curso» | Semáforo lleno | Reintentar; el límite protege la memoria |
| Los correos no salen | SMTP mal configurado | Probar con `MAIL_MAILER=log` y mirar el registro |
| El disco crece | Catálogos importados y antiguos | Revisar `catalogo_snapshots`: cada importación guarda su diccionario |

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
| Cada importación guarda su diccionario completo | El respaldo crece: conviene purgar snapshots viejos |
| CSP permite estilos y scripts en línea | Se mitiga moviendo el JavaScript a archivos propios |
