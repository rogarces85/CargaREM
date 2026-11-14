# CargaREM — Portal M7

Documentación técnica de **Cargas REM** (el sistema que existe hoy) y especificación del **Portal M7**: el módulo web de envío mensual de estadísticas REM por establecimiento.

> **Estado:** **D-STACK cerrada (C-015): Laravel 12.72 sobre PHP 8.2.** El portal corre en [`portal/`](portal/), migra contra `cargas_rem`, responde por Apache y pasa sus tests con **cero avisos de seguridad**. El despliegue espera a que TI habilite PHP 8.2 en el vhost: ver [`solicitud-php-82-ti.md`](docs/infra/solicitud-php-82-ti.md).

---

## Contexto

**Cargas REM** es la aplicación de escritorio que la Unidad de Estadísticas usa hoy: WinForms, .NET Framework 4.8, x86. Carga, valida y consolida archivos Excel mensuales del REM en bases Access por Servicio de Salud, genera el archivo de envío MINSAL y sincroniza catálogos contra los servicios web del MINSAL.

Hoy los establecimientos envían sus archivos por correo y la Unidad los revisa y carga a mano. El **Portal M7** propone un portal donde cada encargado sube sus archivos, recibe validación inmediata y sigue el estado de su envío, con revisión y aprobación por la Unidad.

El portal **no reemplaza** a Cargas REM: recibe, valida, ordena la revisión y aprueba la estadística mensual. La generación del archivo MINSAL y la integración automática quedan fuera del MVP. La especificación vigente es [`specs/MVP_V2.md`](specs/MVP_V2.md).

---

## Mapa del repositorio

| Ruta | Qué contiene |
|---|---|
| [`portal/`](portal/) | **La aplicación.** Laravel **12.72.0** sobre PHP **8.2**, fijado en Composer. MySQL 8 en producción, MariaDB 10.4 en local. AdminLTE 3.2 en `public/assets` |
| [`specs/`](specs/) | **La especificación vigente:** [`MVP_V2.md`](specs/MVP_V2.md) (alcance v2.0, 14-11-2026). El alcance anterior queda en [`specs/_historico/`](specs/_historico/) para trazabilidad |
| [`02-DOCS/wiki/`](02-DOCS/wiki/) | Wiki del proyecto: [`sdd/constitution.md`](02-DOCS/wiki/sdd/constitution.md) (Constitución **v3.1.0**, 28 principios), [`sdd/decisions.md`](02-DOCS/wiki/sdd/decisions.md) (registro append-only de decisiones), `stack/` (entorno y decisión de stack), `dominio/` (pipeline V0–V11). Puerta de entrada: [`index.md`](02-DOCS/wiki/index.md) |
| [`informe.md`](informe.md) | Informe técnico completo de Cargas REM: arquitectura, módulos M1–M6, reglas de negocio, flujos, §2.10 del módulo de envío, pendientes P1–P13 |
| [`backup_documentacion/`](backup_documentacion/) | Documentación profundizada por módulo (M1–M7). El diseño funcional y técnico del portal está en [`MODULO_M7_PROFUNDIZADO.md`](backup_documentacion/MODULO_M7_PROFUNDIZADO.md) (81 KB) |
| [`docs/`](docs/) | 7 diagramas SVG por módulo, [`FLUJO_DATOS_INTEGRAL_SISTEMA.md`](docs/FLUJO_DATOS_INTEGRAL_SISTEMA.md), `validacion/` (catálogo `ENV-###`, paridad de cabecera, política de severidad), [`datos/`](docs/datos/fuentes-datos-mdb.md) (inventario de las bases Access) y [`seguridad/`](docs/seguridad/dependencias-eol.md) (avisos abiertos por el canon PHP 7.4 y sus mitigaciones) |
| [`Flujos/`](Flujos/) | 8 infografías PNG de los flujos principales |
| [`REVIEW.md`](REVIEW.md) | Qué mira el revisor: 11 fallos graves, rutas que no se revisan, tope de nits y rutas que exigen decisión humana |
| [`tests/Parity/fixtures/`](tests/Parity/fixtures/) | *Fixtures* de paridad. Hoy solo [`rbac-matrix.json`](tests/Parity/fixtures/rbac-matrix.json) (18 permisos × 4 roles) |
| [`01-TOOLS/`](01-TOOLS/) | Herramientas de máquina. Incluye [`rem-export`](01-TOOLS/rem-export/README.md), el exportador de catálogos `.mdb → CSV`; y una plantilla para credenciales (`.env` local, fuera de git) |
| [`AGENTS.md`](AGENTS.md) · `.rsc/` · `.opencode/` | Harness de trabajo del agente (rsc 4.1.2) |

---

## Módulos documentados

**M1–M6** describen el sistema de escritorio que existe hoy. **M7** es el portal nuevo.

| Módulo | Documento | Alcance | Diagrama |
|---|---|---|---|
| **M1** Arranque y Configuración | [`MODULO_M1_PROFUNDIZADO.md`](backup_documentacion/MODULO_M1_PROFUNDIZADO.md) | Shell principal, splash, selección de Servicio de Salud, actualización inicial | [SVG](docs/M1_DIAGRAMA_ARQUITECTURA.svg) |
| **M2** Carga de Datos | [`MODULO_M2_PROFUNDIZADO.md`](backup_documentacion/MODULO_M2_PROFUNDIZADO.md) | Motor de validación `CargaExcel`, carga individual y por carpeta, web services | [SVG](docs/M2_DIAGRAMA_CARGA.svg) |
| **M3** Errores y Control de Carga | [`MODULO_M3_PROFUNDIZADO.md`](backup_documentacion/MODULO_M3_PROFUNDIZADO.md) | Registro y consulta de errores, hoja de control con cálculo de puntos | [SVG](docs/M3_DIAGRAMA_ERRORES.svg) |
| **M4** Consulta e Informes | [`MODULO_M4_PROFUNDIZADO.md`](backup_documentacion/MODULO_M4_PROFUNDIZADO.md) | Matriz mensual, duplicidad, búsqueda por prestación, comparativos, informe INF1 | [SVG](docs/M4_DIAGRAMA_CONSULTAS.svg) |
| **M5** Mantenimiento y Exportación | [`MODULO_M5_PROFUNDIZADO.md`](backup_documentacion/MODULO_M5_PROFUNDIZADO.md) | Borrado selectivo, compactación de Access, exportación DBF y archivo MINSAL | [SVG](docs/M5_DIAGRAMA_MANTENIMIENTO.svg) |
| **M6** Sincronización y Actualización | [`MODULO_M6_PROFUNDIZADO.md`](backup_documentacion/MODULO_M6_PROFUNDIZADO.md) | Actualización de catálogos y versiones contra los web services del MINSAL | [SVG](docs/M6_DIAGRAMA_SYNCHRONIZATION.svg) |
| **M7** Envío por Establecimiento | [`MODULO_M7_PROFUNDIZADO.md`](backup_documentacion/MODULO_M7_PROFUNDIZADO.md) | **Portal nuevo:** carga, validación, revisión, aprobación y paquete de carga | [SVG](docs/M7_DIAGRAMA_ENVIO.svg) |

---

## Stack canon — decisión cerrada (C-015)

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.2 |
| Framework | Laravel 12.72 |
| Base de datos | MySQL 8.0.42 (`utf8mb4`), base `cargas_rem`; MariaDB 10.4 en local |
| UI | AdminLTE 3.2 (Bootstrap 4), `dist/` precompilado, sin Node en producción |
| JS | Vanilla |

**Por qué esta versión y no una anterior:** Laravel 8 acumula 5 avisos de seguridad sin parche posible. La primera rama limpia es Laravel 12.68+, que exige PHP 8.2 — la versión que el servidor ya corre en el vhost de Observaciones. Decisión en [`sdd/decisions.md`](02-DOCS/wiki/sdd/decisions.md) (C-015). El despliegue espera a que TI habilite PHP 8.2.

Otras restricciones confirmadas: `upload_max_filesize=2M`, `post_max_size=8M`, sin `pdo_sqlite`/`opcache`/`bcmath` en el servidor, acceso por FTP, sin SSH confirmado. Detalle en [`stack/restricciones-servidor.md`](02-DOCS/wiki/stack/restricciones-servidor.md).

---

## Estado del proyecto

El alcance está cerrado en [`specs/MVP_V2.md`](specs/MVP_V2.md) (v2.0, 14-11-2026) y el portal está construido sobre ese corte:

| Módulo | Qué hay | Estado |
|---|---|---|
| **MOD-01** Identidad y usuarios | Login, logout, cuenta propia, 3 roles, permisos, alta de usuarios y restablecimiento de contraseña por el administrador | ✅ |
| **MOD-02** Catálogos | Servicios, establecimientos, series, períodos, matriz establecimiento-serie, plantillas, diccionario sembrado, calendario de plazos y feriados | ✅ |
| **MOD-03** Envíos y archivos | Crear envío, subir, versionar, revalidar, descargar original y reporte | ✅ |
| **MOD-04** Validación | V0–V11 (sin V8) con hallazgos y severidad | ✅ (paridad con *golden files* pendiente de insumos) |
| **MOD-05** Estados | `recibido → validado / con_errores → en_revision → observado / aprobado` | ✅ |
| **MOD-06** Revisión | Bandeja con filtros, observaciones por serie, aprobación completa | ✅ |
| **MOD-07** Completitud | Matriz con semáforo, totales y atraso | ✅ |
| **MOD-08** Auditoría | Append-only en tabla, servicios y observers; sin pantalla de consulta | ✅ |
| **MOD-09** Notificaciones | Aviso en base de datos + campana en la cabecera | ✅ |
| **MOD-11** Seguridad | Cabeceras, CSRF, rate limit, health check, logs, validación real de archivo | ✅ |

Fuera del corte: paquete ZIP y confirmación de carga (v3), correo de aviso, snapshots de catálogo, excepciones V8, exportación y drill-down de completitud, panel por rol y UI de auditoría (v2.1), 2FA y rol `consulta` (v2.1), multi-servicio (v3). El detalle está en [`docs/reestructuracion/matriz-funcionalidades.md`](docs/reestructuracion/matriz-funcionalidades.md).

### Lo que espera a personas

1. **Paridad con *golden files*:** archivos Excel reales anonimizados y su salida del ValidadorRem. La estructura existe en [`tests/Parity/fixtures/`](tests/Parity/fixtures/README.md); la comparación está armada: `php artisan rem:paridad`.
2. **Decisiones abiertas** (§8 de la especificación): series obligatorias (D5) y tratamiento de SAX (D6). Identidad (D-030) y plazo (D-029) quedaron resueltos.
3. **UAT formal** del flujo encargado → revisor: [`docs/uat/guion-uat.md`](docs/uat/guion-uat.md).

---

## Entorno de referencia

El desarrollo va en **dos fases** (C-011): primero el MVP completo en local, después el traslado al servidor.

| Fase | Dónde | Stack real |
|---|---|---|
| **1 — MVP local** | `C:\xampp` (Windows) | PHP 8.2.12 · MariaDB 10.4.32 · Apache 2.4.58 |
| **2 — Servidor** | `estadisticas.ssosorno.cl` (Ubuntu 20.04) | **PHP 8.2** (confirmado por TI) · MySQL 8.0.42 · Apache 2.4 `mod_php` |

Los límites y las diferencias entre ambos entornos —incluidas las cinco que rompen al migrar— están verificados y documentados en [`stack/entorno-local.md`](02-DOCS/wiki/stack/entorno-local.md).

- Acceso al servidor: solo FTP con TLS.
- Publicación prevista: `https://estadisticas.ssosorno.cl/`.
- La paridad numérica se valida contra MySQL 8 en la fase 2, nunca contra MariaDB.

---

## Cómo se trabaja aquí

- **Ramas:** `main` está protegida y cerrada para el agente. La rama base de documentación es `docs/constitucion-y-stack`; el código entra por `feature/*` con pull request.
- **Reglas de la casa:** [Constitución v3.1.0](02-DOCS/wiki/sdd/constitution.md). Manda sobre cualquier otro documento, incluido este README.
- **Qué se aprueba:** [REVIEW.md](REVIEW.md).
- **Decisiones:** toda decisión significativa se agrega a [`sdd/decisions.md`](02-DOCS/wiki/sdd/decisions.md). Es append-only: no se borra ni se reescribe una entrada.
- **Documentos canónicos:** `informe.md`, `stack_modulo_envio.md` y `MODULO_M7_PROFUNDIZADO.md` son la fuente. La wiki enlaza y sintetiza; no los duplica.

---

## Convenciones de la documentación

| Símbolo | Significado |
|---|---|
| ✅ | Confirmado en el sistema o en la documentación |
| 🟡 | Decisión propuesta o pendiente de validación |
| ❓ | Pendiente de definición por negocio o TI |

**Idioma:** tablas, columnas, estados y textos de interfaz en español; clases, métodos y variables en inglés.

---

## Datos sensibles

Este repositorio **no debe contener** datos reales de establecimientos, bases de datos (`.mdb`, `.sql`), credenciales ni archivos `.env`. Los datos REM son confidenciales (Ley 21.719). Revisa cada commit antes de subirlo.

## Resumen de entrega

Para quien toma el proyecto: [docs/entrega/estado-del-proyecto.md](docs/entrega/estado-del-proyecto.md).

## Trabajar en otra máquina

El repositorio lleva el código, los tests y la documentación. **No lleva datos
reales ni configuración local**: eso viaja aparte (principio 22).

### Lo que hay que llevar aparte

| Qué | Dónde | Tamaño |
|---|---|---|
| Catálogo del Minsal (CSV) | `C:\xampp\_snapshot_m7\` | 22 MB |
| Archivos REM de prueba | `portal\archivos\` | 8 MB |
| Configuración | `portal\.env` | 1 KB |
| Respaldo de la base (opcional) | un `.sql` de `01-TOOLS/backup/` | 54 MB |

El catálogo se puede regenerar con `01-TOOLS\rem-export\export-mdb.ps1` si hay
acceso al Access del Minsal. El respaldo se genera con
`01-TOOLS\backup\respaldo.ps1`.

### Puesta en marcha

**1. Traer el código**

```bat
cd C:\xampp\htdocs\www
git clone https://github.com/rogarces85/CargaREM.git Cargas
cd Cargas\portal
```

**2. Copiar los datos**

```bat
:: el catálogo
xcopy RUTA\catalogo\* C:\xampp\_snapshot_m7\ /E /I /Y

:: los archivos de prueba
xcopy RUTA\archivos\* C:\xampp\htdocs\www\Cargas\portal\archivos\ /E /I /Y

:: la configuración
copy RUTA\configuracion\.env C:\xampp\htdocs\www\Cargas\portal\.env
```

**3. Instalar y crear la base**

```bat
composer install

C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS cargas_rem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

set REM_CATALOGO_DIR=C:\xampp\_snapshot_m7
php artisan migrate --seed
```

`migrate --seed` crea los usuarios y permisos, el Servicio Osorno `23` y, si
`REM_CATALOGO_DIR` apunta a la exportación, siembra el catálogo de validación
(series, establecimientos, plantillas, hojas y diccionario).

**4. Restaurar el respaldo (alternativa al paso 3, más rápido)**

Si se trae el `.sql`, en vez de sembrar:

```bat
C:\xampp\mysql\bin\mysql.exe -u root cargas_rem < RUTA\db_AAAA-MM-DD_HHMM.sql
tar -xzf RUTA\storage_AAAA-MM-DD_HHMM.tar.gz -C portal\storage\app\private
php artisan migrate --force
```

**5. Arrancar**

```bat
cd C:\xampp\htdocs\www\Cargas
iniciar.bat
```

Abre `http://127.0.0.1:8000/login`. Usuarios de desarrollo, creados por el
seeder: `admin@ssosorno.cl`, `revisor@ssosorno.cl`,
`encargado.hospital@ssosorno.cl` y `encargado.postas@ssosorno.cl`, todos con la
clave `Portal2026!`.

**6. Comprobar**

```bat
php artisan test
```

Deben pasar **257 tests**. Usan su propia base (`cargas_rem_test`) y no tocan los
datos de trabajo.

La guía detallada, con problemas comunes y cómo validar archivos REM:
[`docs/operacion/como-probar.md`](docs/operacion/como-probar.md).
## Cómo se ejecuta

Guía paso a paso, incluido cómo probar archivos REM reales:
[`docs/operacion/como-probar.md`](docs/operacion/como-probar.md).

O más rápido: doble clic en **`iniciar.bat`**, que verifica PHP, crea la base, migra, siembra, carga el catálogo si hace falta y abre el navegador. Para probar archivos: **`probar-archivos.bat`**.

Resumen manual: `composer install` → `.env` con `REM_CATALOGO_DIR` → `php artisan migrate --seed` → entrar por `http://localhost/Cargas/portal/public/login`.