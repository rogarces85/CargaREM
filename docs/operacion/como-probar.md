# Cómo ejecutar el portal y probar archivos

Guía corta para levantar el sistema desde cero y validar archivos REM. Está
escrita para alguien que llega al repositorio sin la máquina preparada.

## 1. Qué hace falta

| Pieza | Versión | Nota |
|---|---|---|
| XAMPP | con **PHP 8.2** | El portal no corre en PHP 7.4 |
| Composer | 2.x | Para instalar dependencias |
| MariaDB o MySQL | 10.4 / 8.0 | La base se crea con las migraciones |

Verificación rápida: `php -v` debe decir 8.2 o superior.

## 2. Qué traer de la otra máquina

El repositorio **no** contiene datos reales (Constitución, principio 22). Dos
cosas viven fuera de git y hay que copiarlas aparte:

| Qué | Dónde está | Tamaño |
|---|---|---|
| Catálogo del Minsal (CSV) | `C:\xampp\_snapshot_m7\` (CSV + `manifest.json`) | ~23 MB |
| Archivos REM de prueba | `portal\archivos\` (los `.xlsm`) | ~1,5 MB cada uno |

El catálogo se siembra con `CatalogoSeeder`. Si falta, el portal arranca igual
pero la validación reporta bloqueantes de plantilla y diccionario.

Si no tenés el catálogo a mano pero sí la instalación de Cargas REM, se
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

Revisá en `.env` que la base se llame `cargas_rem`, que el usuario de MySQL
coincida con el tuyo y que `REM_CATALOGO_DIR` apunte al catálogo:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cargas_rem
DB_USERNAME=root
DB_PASSWORD=

REM_CATALOGO_DIR=C:\xampp\_snapshot_m7
```

## 4. Crear la base y cargar datos

```bat
:: La base tiene que existir antes de migrar
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS cargas_rem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate --seed
```

El `--seed` crea los roles, los 16 permisos, el Servicio Osorno `23` y el juego
mínimo de datos: 7 establecimientos de la provincia de Osorno, 6 series, 6
períodos y 4 usuarios de prueba.

Si `REM_CATALOGO_DIR` tiene los CSV, el mismo `--seed` carga además el catálogo:
series, establecimientos, plantillas, hojas y **185.526** prestaciones en el
diccionario. Tarda un par de minutos.

Si hace falta recargar solo el catálogo después:

```bat
set REM_CATALOGO_DIR=C:\xampp\_snapshot_m7
php artisan db:seed --class=CatalogoSeeder
```

Los plazos se calculan con el calendario chileno (quinto día hábil del mes
siguiente). Para revisarlos o corregir los períodos del año:

```bat
php artisan rem:calendario 2026 --actualizar
```

## 5. Levantar el sistema

**Con Apache encendido** (el botón Start de XAMPP), el portal ya está servido:

`http://localhost/Cargas/portal/public/login`

`iniciar.bat` detecta si Apache responde y abre esa dirección. **No hay que
dejar ninguna ventana abierta**: Apache corre como servicio.

Sin Apache, `iniciar.bat` levanta el servidor de desarrollo en
`http://127.0.0.1:8000/login`. En ese caso **la ventana tiene que quedar
abierta**: si se cierra, el servidor se detiene y la página deja de responder.
Es la causa más común de «no me abre el login».

| Usuario | Clave | Rol |
|---|---|---|
| `admin@ssosorno.cl` | `Portal2026!` | Administrador |
| `revisor@ssosorno.cl` | `Portal2026!` | Revisor |
| `encargado.hospital@ssosorno.cl` | `Portal2026!` | Encargado |

Son usuarios de desarrollo: el seeder los crea y están en el repositorio a
propósito para poder probar. En cualquier entorno compartido se cambian.

Si preferís no usar Apache: `php artisan serve` y entrar por
`http://127.0.0.1:8000`, dejando la terminal abierta.

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

### Generar un mes de prueba

```bat
php artisan rem:probar --serie=A
```

Crea un envío, arma un Excel con la forma del catálogo y muestra el reporte.
No simula el ciclo completo: eso se recorre a mano desde el navegador
(encargado → revisor).

## 7. Correr los tests

```bat
php artisan test
```

Deben pasar **257 tests**. Los tests usan su propia base (`cargas_rem_test`),
que se destruye y recrea en cada corrida: no tocan los datos de desarrollo.

Si faltan tests por base inexistente:

```bat
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS cargas_rem_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## 8. Problemas conocidos

| Síntoma | Causa | Solución |
|---|---|---|
| La validación dice que falta la plantilla | Catálogo no sembrado | `php artisan db:seed --class=CatalogoSeeder` con `REM_CATALOGO_DIR` |
| `/health` devuelve 503 | Base caída o credenciales | Revisar `.env` y que MariaDB esté encendido |
| Un archivo tarda ~35 s | Se abre un Excel de 1,5 MB y 30 hojas | Es lo esperado hoy; el límite es 90 s |
| `Unknown database 'cargas_rem'` | No se creó la base | Paso 4, la creación es manual |
| Falla `composer install` por PHP | Se está usando el PHP 7.4 de otro XAMPP | `php -v` y ajustar el `PATH` |

## 9. Llevarse el proyecto a otra máquina

El repositorio no lleva datos reales ni configuración local. Al cambiar de
computadora hay que copiar aparte el catálogo, los archivos de prueba y la
configuración.

### Lo que viaja por GitHub

```bat
git clone https://github.com/rogarces85/CargaREM.git
```

Todo el código, la documentación, los tests y los manuales.

### Lo que hay que copiar a mano

| Qué | Dónde está acá | Tamaño | Para qué |
|---|---|---|---|
| Catálogo del Minsal | `C:\xampp\_snapshot_m7\` | 23 MB | Sembrar los 185.526 registros |
| Archivos REM de prueba | `portal\archivos\` | 8 MB | Validar y comparar paridad |
| Configuración | `portal\.env` | 1 KB | Base, correo, URL, `REM_CATALOGO_DIR` |
| Respaldo de la base | `C:\xampp\_backup_test\` | 81 MB | Alternativa a sembrar |

### Camino corto: restaurar el respaldo

Con el `.sql` copiado, en la máquina nueva:

```bat
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE cargas_rem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\mysql\bin\mysql.exe -u root cargas_rem < ruta\db_2026-11-14_1200.sql
```

Y los archivos subidos, si hacen falta:

```bat
tar -xzf storage_2026-11-14_1200.tar.gz -C portal\storage\app\private
```

Después `php artisan migrate --force` por si el respaldo es anterior a alguna
migración nueva.

### Camino largo: de cero

Los pasos 1 a 4 de esta guía, con `REM_CATALOGO_DIR` apuntando al catálogo.

### Comprobar que quedó bien

```bat
php artisan test
```

Deben pasar **257 tests**. El conteo no depende de los datos: los tests usan su
propia base.
