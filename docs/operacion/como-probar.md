# Cómo ejecutar el portal y probar archivos

Guía corta para levantar el sistema desde cero y validar archivos REM. Está
escrita para alguien que llega al repositorio sin la máquina preparada.

## 1. Qué hace falta

| Pieza | Versión | Nota |
|---|---|---|
| XAMPP | con **PHP 8.2** | El portal no corre en PHP 7.4 |
| Composer | 2.x | Para instalar dependencias |
| MariaDB o MySQL | 10.4 / 8.0 | La base se crea sola con las migraciones |

Verificación rápida: `php -v` debe decir 8.2 o superior.

## 2. Qué traer de la otra máquina

El repositorio **no** contiene datos reales (Constitución, principio 22). Dos
cosas viven fuera de git y hay que copiarlas aparte:

| Qué | Dónde está | Tamaño |
|---|---|---|
| Snapshot del catálogo | `C:\xampp\_snapshot_m7\` (10 CSV + `manifest.json`) | ~27 MB |
| Archivos REM de prueba | `portal\archivos\` (los `.xlsm`) | ~1,5 MB cada uno |

Sin el snapshot no se pueden validar archivos: sin catálogo no hay
diccionario, plantillas ni hojas, y el portal lo dice con `APP-ENV-004`.

Si no tenés el snapshot a mano pero sí la instalación de Cargas REM, se
regenera con el exportador:

```bat
cd 01-TOOLS\rem-export
.\export-mdb.ps1
```

Tarda unos 30 segundos y deja los CSV en `C:\xampp\_snapshot_m7\`.

## 3. Instalar

```bat
cd C:\xampp\htdocs\www\Cargas\portal

composer install
copy .env.example .env
php artisan key:generate
```

Revisá en `.env` que la base se llame `cargas_rem` y que el usuario de MySQL
coincida con el tuyo:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cargas_rem
DB_USERNAME=root
DB_PASSWORD=
```

## 4. Crear la base y cargar datos

```bat
:: La base tiene que existir antes de migrar
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS cargas_rem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate --seed
php artisan catalogos:importar C:\xampp\_snapshot_m7 --activar
```

El `--seed` crea los roles, los 18 permisos y el juego mínimo de datos:
7 establecimientos de la provincia de Osorno, 6 series, 6 períodos y 4
usuarios de prueba.

La importación tarda unos 20 segundos y deja **185.526** prestaciones en el
diccionario. Al terminar, el catálogo queda activo.

## 5. Levantar el sistema

Con Apache encendido desde el panel de XAMPP:

`http://localhost/Cargas/portal/public/login`

| Usuario | Clave | Rol |
|---|---|---|
| `admin@ssosorno.cl` | `Portal2026!` | Administrador |
| `revisor@ssosorno.cl` | `Portal2026!` | Revisor |
| `encargado.hospital@ssosorno.cl` | `Portal2026!` | Encargado |

Son usuarios de desarrollo: el seeder los crea y están en el repositorio a
propósito para poder probar. En cualquier entorno compartido se cambian.

Si preferís no usar Apache: `php artisan serve` y entrar por
`http://127.0.0.1:8000`.

## 6. Probar los archivos REM

### Sin subirlos: mirar qué declaran

```bat
php artisan rem:inspeccionar portal\archivos
```

Muestra establecimiento, período, serie, versión y cantidad de hojas de cada
archivo. Sirve para saber si un archivo es el que se cree que es.

### Validarlos de verdad

```bat
php artisan rem:validar portal\archivos\123100A09.xlsm
```

El comando toma los datos del propio archivo (establecimiento, serie y mes
salen de su cabecera), crea el envío si no existe, lo sube, lo valida y
muestra el reporte agrupado por código.

Con `--detalle` muestra además cada hallazgo con su hoja y su celda:

```bat
php artisan rem:validar portal\archivos\123100A09.xlsm --detalle
```

Un archivo ya validado se puede volver a validar: el comando reutiliza el
registro existente en lugar de chocar con la protección de duplicados.

### Desde el navegador

Menú **Envíos** → crear un envío → abrirlo → subir el Excel de una serie.
La validación corre sola y el detalle muestra los hallazgos.

### Simular un mes completo

```bat
php artisan rem:simular-mes --establecimientos=2 --observar=1
```

Recorre el ciclo entero con datos reales: carga, validación, revisión,
observación, corrección y carga confirmada. Sirve para ver el sistema con datos
y para llegar al UAT con algo probado a escala.

## 7. Correr los tests

```bat
php artisan test
```

Deben pasar **164 tests**. Los tests usan su propia base (`cargas_rem_test`),
que se destruye y recrea en cada corrida: no tocan los datos de desarrollo.

Si faltan tests por base inexistente:

```bat
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS cargas_rem_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## 8. Problemas conocidos

| Síntoma | Causa | Solución |
|---|---|---|
| `No hay catálogo activo` | Falta importar | `php artisan catalogos:importar ... --activar` |
| `/health` devuelve 503 | Base caída o credenciales | Revisar `.env` y que MariaDB esté encendido |
| Un archivo tarda ~35 s | Se abre un Excel de 1,5 MB y 30 hojas | Es lo esperado hoy; el límite es 90 s |
| `Unknown database 'cargas_rem'` | No se creó la base | Paso 4, la creación es manual |
| Falla `composer install` por PHP | Se está usando el PHP 7.4 de otro XAMPP | `php -v` y ajustar el `PATH` |
