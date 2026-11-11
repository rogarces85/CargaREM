---
type: stack
title: Entorno local de desarrollo (verificado)
description: Qué hay realmente instalado en XAMPP, cómo se compara con el servidor Linux de producción y qué diferencias pueden hacer que algo funcione en local y falle en producción.
tags: [stack, entorno, local, xampp, php, mariadb, dos-fases]
timestamp: 2026-11-11T15:40:00Z
topic: stack
---

# Entorno local de desarrollo

> **Verificado el 2026-11-11** en `C:\xampp`. Cada dato salió de la máquina con un comando, no de un supuesto. Si algo no se pudo verificar, dice «sin confirmar».

---

## 1. Estrategia en dos fases

El MVP se construye y se prueba en local. Cuando funcione, se traslada al servidor Linux.

| Fase | Dónde | Stack real | Qué se prueba |
|---|---|---|---|
| **1 — MVP local** | `C:\xampp` (Windows) | PHP 8.2.12 · MariaDB 10.4.32 · Apache 2.4.58 | El flujo completo: carga, validación, revisión, aprobación, paquete |
| **2 — Servidor** | `estadisticas.ssosorno.cl` (Ubuntu 20.04) | **PHP 8.2** (hoy 7.4.3, pendiente de TI) · MySQL 8.0.42 · Apache 2.4 `mod_php` | Paridad con *golden files* y operación real |

**Por qué en este orden:** en local se itera en segundos y sin riesgo. En el servidor, cada prueba depende de FTP y de un despliegue. Construir primero contra el servidor multiplica el costo de cada error.

**Criterio de salida de la fase 1:** ciclo encargado → revisor completo en local, con la suite en verde y los presupuestos de rendimiento medidos en local.

**Criterio de salida de la fase 2:** paridad 100 % contra *golden files* corriendo en MySQL 8 y PHP 8.2, no antes.

> Las dos fases usan Laravel 12.72 (C-015). La diferencia es la versión de PHP y el motor de base de datos que los hospedan: 8.2 con MariaDB 10.4 en local, 8.2 con MySQL 8.0.42 en el servidor.

---

## 2. Qué hay instalado

| Componente | Versión real | ¿Sirve? |
|---|---|---|
| XAMPP | 8.2.12 | ✅ como entorno de desarrollo |
| PHP | **8.2.12** (ZTS, x64, VC++ 2019) | 🟡 más nuevo que producción. Ver §5 |
| Apache | 2.4.58 (Win64, Apache Lounge VS17) | ✅ `mod_php`, `mod_rewrite`, `mod_headers`, `mod_ssl`, `mod_proxy` |
| Base de datos | **MariaDB 10.4.32** | 🟡 no es MySQL 8. Ver §4 |
| Composer | 2.9.7 | ✅ |
| Node / npm | 26.13.0 / 11.19.1 | ✅ solo para construir assets, no en producción |
| Git | 2.51.1 | ✅ |

Servicios en ejecución: `Apache2.4` y `mysql`, ambos en inicio automático. Puertos 80 y 3306 escuchando.

Extensiones de PHP **presentes** y relevantes: `pdo_mysql`, `pdo_sqlite`, `mysqli`, `mbstring`, `intl`, `gd`, `zip`, `fileinfo`, `curl`, `openssl`, `bcmath`, `dom`, `xml`, `SimpleXML`, `soap`, `com_dotnet`, `iconv`, `exif`.

Extensiones **ausentes**: `sqlite3` (el que falta es el driver suelto, no PDO), `Zend OPcache`, `xdebug`, `imagick`, `sodium`, `apcu`.

---

## 3. Límites de PHP: local contra producción

| Directiva | Local (verificado) | Producción | Efecto |
|---|---|---|---|
| `upload_max_filesize` | **256M** | **2M** | Local acepta archivos 128 veces más grandes |
| `post_max_size` | **256M** | 8M | Idem |
| `memory_limit` | **2048M** | 1024M | Local es más permisivo: una validación puede pasar aquí y morir allá |
| `max_execution_time` | 300 | 1000 | Local es **más estricto**: una validación lenta falla aquí y no allá |
| `max_input_time` | 300 | sin confirmar | — |
| `max_file_uploads` | 20 | 20 | Igual |
| `display_errors` | On | On (a corregir) | En producción debe quedar `Off` (Constitución, principio 20) |
| `date.timezone` | `Europe/Berlin` | sin confirmar | Laravel fija `America/Santiago` en `config/app.php`, pero PHP queda desalineado fuera de Laravel |
| OPcache | deshabilitado | no instalado | Sin diferencia funcional; solo rendimiento |

### La regla que importa

**El límite de 2 MB vive en el código, no en `php.ini`.**

Que en local haya 256 MB es útil: permite subir un archivo de 10 MB para comprobar que **el portal lo rechaza**. Si el límite se delegara a `php.ini`, ese archivo se aceptaría en local y se rechazaría en el servidor sin que nadie lo note hasta producción.

Implementación: `config('rem.archivos.max_kb') === 2048` se valida en el `FormRequest` y otra vez en el motor de validación (código `ENV-001`).

---

## 4. Base de datos: MariaDB 10.4 local contra MySQL 8 producción

No son el mismo motor. Cinco diferencias pueden hacer que algo funcione en local y falle en el servidor:

| Diferencia | Local | Producción | Riesgo |
|---|---|---|---|
| **`sql_mode`** | `NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION` | `STRICT_TRANS_TABLES` incluido | **Alto.** En local un `INSERT` con dato inválido se trunca en silencio; en producción lanza error |
| **`lower_case_table_names`** | `1` (nombres insensibles a mayúsculas) | `0` (sensibles) | **Alto.** `CREATE TABLE Envios` funciona en local y falla en Linux |
| **Collation por defecto** | `utf8mb4_general_ci` | `utf8mb4` de MySQL 8 (`0900_ai_ci`) | Medio. Cambia el orden de los `ORDER BY` y el resultado de comparaciones |
| **Tipo `JSON`** | Alias de `LONGTEXT` con `CHECK` | Tipo nativo | Medio. `JSON_EXTRACT` y los índices funcionales no son iguales |
| **`max_allowed_packet`** | **1 MB** | 8 MB o más | Medio. Una importación grande puede fallar solo en local |
| **Palabras reservadas** | Menos reservadas | `rank`, `groups`, `system`, `lead`, `lag` lo son | Alto. Una columna llamada `rank` pasa en local y rompe en producción |

### Mitigaciones obligatorias

1. **Fijar el modo estricto en la conexión**, no confiar en el servidor: en `config/database.php`, `'modes' => ['STRICT_TRANS_TABLES', 'NO_ZERO_DATE', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION']`.
2. **Tablas siempre en `snake_case` y en minúsculas.** Nunca un nombre con mayúsculas.
3. **Declarar `utf8mb4` explícito** en cada migración (`$table->charset = 'utf8mb4'`).
4. **No usar palabras reservadas de MySQL 8** como nombre de columna.
5. **Probar las migraciones en MySQL 8 antes de la fase 2.** La forma barata: un contenedor `mysql:8.0` solo para correr `php artisan migrate` una vez.

---

## 5. PHP del local contra PHP del servidor

Esta es la diferencia más traicionera: **el código que escribas en local puede no existir en producción.**

| Riesgo | Ejemplo | Cómo se controla |
|---|---|---|
| Sintaxis de PHP 8.x | `match`, `?->`, propiedades promovidas, argumentos nombrados, `enum`, `readonly` | No usarlos. Revisión en `REVIEW.md` |
| Deprecaciones de 8.2 | propiedades dinámicas en 8.2 (error en 9.0) | `error_reporting` local ya las excluye, pero igual son señal |
| Tipos estrictos | uniones con `null` distintas entre versiones | Tests |
| Librerías | una dependencia nueva que exige 8.0+ | `composer.json` con `platform.php = 7.4.3` |

### La defensa concreta

- **`composer.json` con `"config": {"platform": {"php": "7.4.3"}}`** (tarea T-F1-02). Composer resuelve las dependencias como si corriera en producción, aunque el PHP real sea 8.2.
- **PHPStan con `phpVersion: 70400`** en `phpstan.neon`. Detecta el uso de funciones y sintaxis que no existen en 7.4 *antes* de desplegar. Es la única verificación automática real de este riesgo.
- **No se degrada el XAMPP.** Hay alrededor de 25 proyectos en `htdocs\www` que dependen del PHP 8.2. Cambiar el binario rompe a los demás. La paridad con 7.4 se valida en el servidor, en la fase 2.

---

## 6. Cómo se sirve el portal en local

| Dato verificado | Valor |
|---|---|
| `DocumentRoot` | `C:/xampp/htdocs/www` (no `htdocs`) |
| `AllowOverride` | `All` en el DocumentRoot → `.htaccess` funciona |
| `.htaccess` en la raíz de `www` | no existe |
| Vhosts personalizados | ninguno; todo se sirve por ruta |

Dos formas de levantar el portal, en orden de preferencia:

1. **`php artisan serve`** (puerto 8000). Es la recomendada para desarrollo: la raíz del sitio es `public/`, las rutas funcionan limpias y no hay que pelear con subdirectorios. No requiere tocar Apache.
2. **`http://localhost/Cargas/portal/public`**. Sirve para comprobar que la aplicación también funciona bajo Apache con `mod_php`, que es lo que hay en producción. La ruta larga es la contrapartida de tener `DocumentRoot` en `www`.

En producción la publicación es `https://estadisticas.ssosorno.cl/`, con el portal en su propio directorio y el DocumentRoot apuntando a `public/`.

---

## 7. Bases de datos

### Las dos bases heredadas: eliminadas

`cargasrem` y `cargasrem_test` tenían 41 tablas y 19 migraciones fechadas el 2026-10-26. No fueron creadas por el equipo y no se reconocen como parte del proyecto. El **2026-11-11** se respaldaron y se eliminaron.

```text
C:\xampp\_respaldo_bd\cargasrem-20261111-1227.sql        24,8 MB
C:\xampp\_respaldo_bd\cargasrem_test-20261111-1227.sql    0,1 MB
```

El respaldo vive **fuera del repositorio**: contiene datos y la Constitución (principio 22) prohíbe `.sql` con datos en git.

### Se parte de cero (C-012)

El portal usa una base nueva y vacía, con el esquema del módulo M7. Los catálogos no se heredan de la base borrada: se **reimportan desde las bases Access del sistema**, que son la fuente real y están actualizadas.

- Nombre: **`cargas_rem`** (y `cargas_rem_test` para la suite). Minúsculas y guion bajo, igual que `observaciones_rem`: el MySQL de producción en Linux es sensible a mayúsculas en el nombre de la base.
- Origen de los datos: [`docs/datos/fuentes-datos-mdb.md`](../../../docs/datos/fuentes-datos-mdb.md).
- Ruta: `.mdb` → CSV (Windows, fase 1) → MySQL. El servidor Linux nunca abre un Access.

### Bases que siguen en la máquina

`rem_anexo`, `observaciones_rem`, `deudas`, `osorno_runners`, `osorno_runners_asistencia`, `runosorno`, `runcoach_dev`, `test`. Son de otros sistemas y no se tocan.

---

## 8. Lo que falta para arrancar la fase 1

| # | Falta | Por qué |
|---|---|---|
| 1 | Crear la base `cargas_rem` vacía | §7 |
| 2 | Exportar los catálogos de `.mdb` a CSV | §7 y `docs/datos/fuentes-datos-mdb.md` |
| 3 | Fijar `platform.php = 7.4.3` en `composer.json` | §5 |
| 4 | Configurar `sql_mode` estricto en la conexión | §4 |
| 5 | ~~Crear el proyecto Laravel 8.83~~ Hecho con Laravel 12.72 (T-F1-01, C-015) | Fase 1 |

---

## 9. Cómo se verificó

```powershell
C:\xampp\php\php.exe -v                      # PHP 8.2.12
C:\xampp\php\php.exe -m                      # extensiones
C:\xampp\php\php.exe --ini                   # C:\xampp\php\php.ini
C:\xampp\apache\bin\httpd.exe -v             # Apache 2.4.58
C:\xampp\mysql\bin\mysql.exe --version       # MariaDB 10.4.32
C:\xampp\mysql\bin\mysql.exe -u root -e "SHOW DATABASES"
composer --version                            # 2.9.7
```

Los valores de `max_execution_time` y `max_input_time` que se leen por CLI (`0` y `-1`) **no** son los del sitio web: la CLI de PHP los sobrescribe al arrancar. Los de la tabla §3 son los del `php.ini`, que es lo que aplica Apache.
