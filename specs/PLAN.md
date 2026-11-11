# PLAN.md — Plan técnico del MVP (Portal M7)

> **Ubicación canónica:** `specs/PLAN.md`
> **Fuentes:** `specs/AGENT.md`, `specs/SPECS.md`, `backup_documentacion/MODULO_M7_PROFUNDIZADO.md`, `02-DOCS/wiki/sdd/constitution.md` v3.0.0, `02-DOCS/wiki/sdd/decisions.md`, `02-DOCS/wiki/dominio/validacion-rem.md`, `02-DOCS/wiki/stack/*`, `REVIEW.md`.
> **Estado:** propuesta técnica para revisión. No sustituye la decisión de stack (D-STACK abierta).
> **Fecha:** 2026-11-09

---

## 0. Cómo leer este documento

- Este plan responde **cómo** construir el MVP. El **qué** y el **por qué** están en `specs/SPECS.md`.
- Los identificadores `RF-###`, `OBJ-###`, `RNF-###`, `CA-###`, `RN-ENV-###`, `ENV-###` y `PEND-###` se conservan de `specs/SPECS.md`. No se crean referencias duplicadas para los mismos requisitos.
- Identificadores nuevos del plan: `MOD-` (módulo), `DAT-` (entidad de datos), `TEC-` (tecnología), `DEP-` (dependencia), `RIES-` (riesgo), `FT-` (flujo técnico), `CONT-` (contradicción), `CAP-` (capacidad).
- El stack está **abierto** (D-STACK, `PEND-001`). Donde una decisión depende del stack, el plan presenta las ramas y **no elige en silencio**.
- Este documento no contiene código de implementación ni un inventario atómico de tareas. Eso corresponde a `specs/TASK.md`.

---

## 1. Resumen técnico

**Estrategia.** Construir un portal web modular en PHP, con validación síncrona dentro de la petición, máquina de estados del envío en un servicio único, versionado de archivos con snapshot de catálogo y auditoría *append-only*. El portal **no** escribe en Access ni en CARGAS REM en el MVP: valida, ordena la revisión y entrega un paquete descargable (D7 = opción A).

**Decisión arquitectónica más importante.** Monolito modular con **validación síncrona** y **estado persistido en un servicio único**. Se defendió contra las restricciones confirmadas: `upload_max_filesize=2M`, `memory_limit=1024M`, sin SSH confirmado, sin cron confirmado y presupuesto p95 < 90 s (Constitución, principios 3 y 25).

| Alternativa | Por qué se descarta |
|---|---|
| Cola / worker / cron | No confirmado en el servidor; el camino crítico no puede depender de infraestructura inexistente (principio 3). |
| Microservicios / API + SPA | Sobrequitectura para 30 establecimientos y 3 roles; añade despliegue y depuración sin beneficio. |
| Modelo de lectura separado (CQRS) | El volumen (30 establecimientos × 7 series) no lo justifica; una sola fuente de verdad evita inconsistencias. |
| Escribir en Access / CARGAS REM | Incompatible con Linux, riesgoso por bloqueos y fuera del alcance del MVP (D7 = A). |

**Portabilidad entre ramas de stack.** El plan es válido para las dos ramas abiertas por D-STACK:

- **Rama B (baseline asumido en `SPECS.md`):** Laravel 8.83 + PHP 7.4.3 + MySQL 8 + AdminLTE 3.2. Corre hoy; carga con deuda de soporte.
- **Rama A1/A2 (condicional):** Laravel 13 + PHP 8.3+ + MySQL 8 + AdminLTE 4. Requiere que TI habilite PHP 8.3.

Las capas, los contratos, el modelo de datos y el orden de fases no cambian entre ramas. Cambian versiones de dependencias y mecánicas (ver `TEC-01` a `TEC-14`).

**Gate inicial.** La fase **F0** cierra D-STACK y la paridad con *golden files* **antes** de escribir código de aplicación (Constitución, principios 1 y 10). Ninguna fase de código arranca sin ese cierre.

**Capacidades cubiertas.**

| ID | Capacidad | Módulo principal | Requisitos |
|---|---|---|---|
| **CAP-01** | Identidad y acceso | MOD-01 | RF-001 a RF-004, RF-013 a RF-016 |
| **CAP-02** | Catálogos y estructura base | MOD-02 | RF-005 a RF-012 |
| **CAP-03** | Envío y archivos | MOD-03 | RF-017 a RF-023 |
| **CAP-04** | Validación REM | MOD-04 | RF-019, RF-021, RF-025 (V11), RF-033 (parte) |
| **CAP-05** | Estados y revisión | MOD-05, MOD-06 | RF-024 a RF-032 |
| **CAP-06** | Completitud y tablero | MOD-07 | RF-033, RF-035, RF-036 |
| **CAP-07** | Auditoría y notificaciones | MOD-08, MOD-09 | RF-037, RF-038 |
| **CAP-08** | Paquete y puente | MOD-10 | RF-039 |
| **CAP-09** | Seguridad y operación transversal | MOD-11 | RNF-008 a RNF-016, RN-ENV-9 |

---

## 2. Decisiones y tecnologías

### 2.1 Tecnologías

| ID | Tecnología | Propósito | Justificación | Restricciones relevantes | Estado |
|---|---|---|---|---|---|
| **TEC-01** | Framework PHP | Base del portal | El diseño M7 §11 está escrito para Laravel; ecosistema completo para auth, RBAC, Excel y tablas | **D-STACK abierta**: rama B (Laravel 8.83 / PHP 7.4.3) o rama A (Laravel 13 / PHP 8.3+) | **Pendiente** (PEND-001) |
| **TEC-02** | MySQL 8.0.42 `utf8mb4` | Almacén de producción | Concurrencia, InnoDB, transacciones, claves foráneas; confirmado en el servidor | Objetivo 8.4 LTS antes del EOL de 8.0 (abril 2026) | **Confirmada** |
| **TEC-03** | SQLite local + MySQL 8 en Docker | Desarrollo y pruebas | SQLite para suite rápida y navegación; MySQL 8 para paridad numérica y concurrencia | SQLite no reemplaza a MySQL en paridad ni en locks (`lockForUpdate` es no-op); el servidor no tiene `pdo_sqlite`, pero es local | **Confirmada** (C-008) |
| **TEC-04** | PhpSpreadsheet | Leer `.xls`, `.xlsx`, `.xlsm` sin ejecutar macros | Única vía viable de lectura fiel; paridad con *golden files* | Versión condicionada al stack: 1.29.x (B) o 5.x (A); la lectura puede diferir y se detecta con paridad | **Confirmada en propósito; versión pendiente** |
| **TEC-05** | AdminLTE | UI administrativa | Plantilla madura, assets precompilados, sin Node en producción | Contradicción de versión: el encabezado de `SPECS.md` dice 3.2; `SPECS.md` §7.2 y `AGENT.md` dicen 4.x | **Propuesta; versión pendiente** |
| **TEC-06** | Laravel Fortify | Autenticación (login, sesión, recuperación) | Deja abierta la puerta al 2FA (D10); más ligero que Breeze para Blade | 2FA aún no decidido; D1 pendiente (fuente de identidad) | **Propuesta** |
| **TEC-07** | `spatie/laravel-permission` | Roles y permisos (RBAC) | Permisos granulares, tests positivos y negativos sencillos | Versión condicionada: 5.x (B) o 8.x (A) | **Propuesta; versión pendiente** |
| **TEC-08** | Disco local fuera de `public/` | Almacenar archivos REM | Principio 19; sin `storage:link` (SSH no confirmado) | Descarga solo por controlador autenticado; permisos 640 | **Confirmada** |
| **TEC-09** | Laravel Mail (sendmail/SMTP local) | Notificaciones por correo | `sendmail`/SMTP confirmados en el servidor | Síncrono, timeout 5 s, tolerante a fallo; D8 asumido (portal + correo) | **Propuesta** |
| **TEC-10** | Paginación Eloquent + Blade (MVP) / DataTables (alternativa) | Tablas servidor | El MVP necesita filtros y paginación, no una librería extra | DataTables añade JS y dependencia; se reserva para L3 si la UX lo exige | **Propuesta** (recomendada la paginación propia) |
| **TEC-11** | Laravel Sanctum | Tokens API del puente | Prepara la opción B (importador) sin cambiar el MVP | Solo si se adopta B; el MVP A no lo requiere | **Propuesta diferida** |
| **TEC-12** | Antivirus (ClamAV) | Escanear archivos antes de validar | Defensa en profundidad | No confirmado en el servidor (D12); si no está, se apoya en validación de tipo real | **Pendiente** |
| **TEC-13** | `mysqldump` + copia de `storage/` | Respaldo y recuperación | RNF-016; continuidad | D13 pendiente con TI; manual/phpMyAdmin como respaldo | **Pendiente** |
| **TEC-14** | Despliegue FTP con artefacto local | Publicar en producción | Sin SSH confirmado; principio 4 | `vendor/`, assets y migraciones exportadas a SQL se construyen en local | **Confirmada** |

### 2.2 Restricciones impuestas por el contexto

Estas restricciones son **confirmadas** por `02-DOCS/wiki/stack/restricciones-servidor.md` y gobiernan todo el plan:

| Restricción | Valor confirmado | Consecuencia técnica |
|---|---|---|
| PHP del vhost | **7.4.3** (`apache2handler`, `mod_php` prefork) | Laravel 13/12 no corren; una sola versión PHP por instancia Apache |
| Ubuntu | 20.04.6 LTS (soporte estándar terminado) | Plan de actualización con TI; no se asume |
| MySQL | 8.0.42, `utf8mb4`, `STRICT_TRANS_TABLES` | Declarar `utf8mb4` explícito; validación estricta |
| Subida | `upload_max_filesize=2M`, `post_max_size=8M` | Un archivo por petición; mes completo (~11 MB) no cabe |
| Ejecución | `memory_limit=1024M`, `max_execution_time=1000s` | Validación síncrona posible; concurrencia limitada por RAM |
| Extensiones | Faltan `pdo_sqlite`, `opcache`, `bcmath` en el servidor | No afecta producción con MySQL; sí afecta un hipotético SQLite en servidor |
| Acceso | Solo FTP; SSH no confirmado | Artefacto completo + ruta de migración *one-shot* protegida |
| Cron | No confirmado | Sin colas ni trabajadores; validación dentro de la petición |
| Correo | `sendmail` y SMTP en `localhost:25` | Notificaciones viables |

### 2.3 Decisiones técnicas ya confirmadas

| Decisión | Fuente |
|---|---|
| Validación síncrona dentro de la petición | Constitución principio 3; `MODULO_M7_PROFUNDIZADO.md` §7.6 |
| Estado del envío escrito solo por un servicio único | Constitución principio 7; M7 §5 |
| Aislamiento por establecimiento en la consulta | Constitución principio 18; RN-ENV-1 |
| Archivos fuera de `public/`, descarga autenticada | Constitución principio 19; RN-ENV-9 |
| Auditoría *append-only* | Constitución principio 21 |
| Datos REM confidenciales (Ley 21.719) | Constitución principio 22 |
| Paridad con *golden files*, tolerancia 0 en numéricos | Constitución principio 10 |
| Un archivo por petición HTTP | M7 §2.1 (P3) |
| Versionado de archivos, nada se sobrescribe | M7 §2.1 (P2); RN-ENV-8 |
| Snapshot de catálogo por archivo | M7 §4.2; RN-ENV-11 |
| Puente MVP = descarga manual (D7 = A) | M7 §12.1; `SPECS.md` RF-039 |
| L1 en local con SQLite; paridad en MySQL | C-008 |

---

## 3. Arquitectura general

### 3.1 Capas

```text
Navegador (Blade + AdminLTE, sin Node)
        │  HTTP POST/GET con CSRF
        ▼
Rutas (web.php / api.php)
        │
        ▼
Middleware: autenticación · throttle · 2FA (si D10) · cabeceras de seguridad
        │
        ▼
FormRequest: formato de entrada + autorización (policy) + mensajes en español
        │
        ▼
Controller: traduce HTTP a servicios; no contiene reglas de negocio
        │
        ▼
Services: EnvíoService · EstadoEnvioService · ValidadorArchivo ·
         CompletitudService · PaqueteService · CatalogoService ·
         AuditoriaService · Notificador
        │
        ▼
Reglas de validación (V0–V11) · Repositorios/Eloquent · Storage privado
        │
        ▼
MySQL 8 (InnoDB, utf8mb4)  +  storage/app/private/envios
```

### 3.2 Límites de responsabilidad

| Componente | Responsabilidad | No debe hacer |
|---|---|---|
| Controller | Orquestar petición y respuesta | Reglas de negocio, SQL complejo |
| FormRequest | Formato, autorización, mensajes | Validar contenido del Excel |
| `EnvioService` | Crear envío, subir versión, versionar, revalidar | Leer celdas |
| `ValidadorArchivo` | Ejecutar la cadena V0–V10 | Cambiar estados del envío |
| `EstadoEnvioService` | Recalcular y transicionar estado; guardas | Escribir archivos ni hallazgos |
| `CompletitudService` | Matriz, totales y exportación | Decidir series obligatorias (viene del catálogo) |
| `PaqueteService` | Manifiesto y ZIP | Aprobar envíos |
| Policy | Autorización sobre un envío/archivo | Filtrar listados (lo hace el *query scope*) |
| Query scope | Aplicar alcance en listados y descargas | Reemplazar a la policy |

### 3.3 Mecanismos de comunicación

- **HTTP**: formularios POST con token CSRF; descargas GET con `Content-Disposition: attachment`.
- **Internos**: los servicios se invocan por métodos; el estado del envío solo lo escribe `EstadoEnvioService`.
- **Persistencia**: Eloquent con *bindings*; nada de SQL concatenado.
- **Notificaciones**: `Notificador` escribe en `notifications` y envía correo en el mismo flujo, tolerante a fallo.
- **Auditoría**: `AuditoriaService::registrar` se llama desde los servicios dentro de la transacción.

### 3.4 Decisión clave y su defensa

**Monolito modular con validación síncrona.** Es la única opción que cumple las tres restricciones duras: no hay worker/cron confirmado, el archivo cabe en la petición y el presupuesto es p95 < 90 s. Un modelo asíncrono añadiría una infraestructura que el servidor no confirma; un modelo distribuido añadiría complejidad sin ganancia para 30 establecimientos. El acoplamiento se contiene con servicios y reglas de validación desacopladas.

---

## 4. Estructura del proyecto

El repositorio hoy es documentación. Se propone que el código de aplicación viva en `portal/` para no mezclarlo con los documentos canónicos. La estructura interna sigue el diseño M7 §11.2.

```text
Cargas/
├── specs/                      # AGENT.md, SPECS.md, PLAN.md, TASK.md
├── 02-DOCS/                    # wiki y conocimiento
├── backup_documentacion/       # M1–M7 (fuente canónica del dominio)
├── docs/ y Flujos/             # diagramas
└── portal/                     # ← aplicación Laravel (propuesta)
    ├── app/
    │   ├── Modules/
    │   │   ├── Catalogos/
    │   │   │   ├── Importadores/       # ImportadorCsv, ImportadorSnapshot
    │   │   │   └── Models/             # Servicio, Establecimiento, Serie, Plantilla, Diccionario
    │   │   ├── Envios/
    │   │   │   ├── Http/Controllers/   # EnvioController, ArchivoController, RevisionController, CompletitudController
    │   │   │   ├── Http/Requests/      # CrearEnvioRequest, SubirArchivoRequest, ObservarRequest, AprobarRequest
    │   │   │   ├── Models/             # Envio, EnvioArchivo, Validacion, Observacion, EnvioTransicion
    │   │   │   ├── Policies/           # EnvioPolicy, ArchivoPolicy
    │   │   │   ├── Services/           # EnvioService, EstadoEnvioService, CompletitudService, PaqueteService
    │   │   │   └── Services/Validacion/
    │   │   │       ├── ValidadorArchivo.php
    │   │   │       ├── ContextoValidacion.php
    │   │   │       ├── ResultadoValidacion.php
    │   │   │       ├── ReglaValidacion.php
    │   │   │       └── Reglas/          # V0Archivo, V1Nombre, V2Cabecera, … V11Completitud
    │   │   ├── Auditoria/
    │   │   └── Notificaciones/
    │   └── Providers/
    ├── config/rem.php            # extensiones, límites, series, paquete
    ├── database/
    │   ├── migrations/           # versionadas; se exportan a SQL para FTP
    │   └── seeders/              # seed mínimo de L1
    ├── resources/views/          # layouts, envios, revision, completitud, admin, correo
    ├── routes/
    │   ├── web.php
    │   └── api.php               # solo si se adopta el puente B
    ├── storage/app/private/envios/{anio}/{mes}/{codigo}/{envio}/v{version}/
    ├── tests/                    # Unit, Feature, Parity
    ├── composer.json             # platform php 7.4.3 si se elige B
    ├── .env                      # ignorado; fuera de public/
    └── public/                   # document root en despliegue (index.php + assets)
```

**Responsabilidad de cada elemento relevante.**

| Elemento | Responsabilidad |
|---|---|
| `Modules/Catalogos` | Importar y reflejar catálogos; definir plantillas y series requeridas |
| `Modules/Envios` | Núcleo: envíos, archivos, validación, estados, revisión, completitud, paquete |
| `Modules/Auditoria` | Registro *append-only* de eventos |
| `Modules/Notificaciones` | Avisos en BD y correo |
| `Services/Validacion/Reglas` | Una clase por etapa V0–V11, con código y severidad |
| `config/rem.php` | Reglas de negocio configurables (series, límites, HMAC) |
| `database/migrations` | Esquema versionado; exportado a SQL para el despliegue FTP |
| `storage/app/private/envios` | Archivos originales, fuera del document root |

> **Nota de despliegue.** En producción el árbol Laravel vive fuera de `/html`; `public/` (o un `index.php` ajustado) apunta al bootstrap. Sin `storage:link`: la descarga pasa por controlador. Esta decisión es independiente del framework elegido.

---

## 5. Modelo técnico de datos

El DDL canónico está en `MODULO_M7_PROFUNDIZADO.md` §4.3. Aquí se describe el modelo conceptual y las decisiones de almacenamiento.

### 5.1 Entidades conceptuales

| ID | Entidad | Propósito | Atributos clave | Relaciones | Restricciones |
|---|---|---|---|---|---|
| **DAT-01** | Servicio | Servicio de Salud | `codigo`, `nombre`, `region`, `activo` | 1:N Establecimientos | `codigo` único |
| **DAT-02** | Establecimiento | Hospital/CDT/CECOSF | `codigo`, `nombre`, `servicio_id`, `comuna`, `dependencia`, `tipo`, `activo` | N:1 Servicio; N:M Usuarios; N:M Series; 1:N Envíos | `codigo` único; FK a Servicio |
| **DAT-03** | Serie | A, BS, BM, D, P, F, SAX | `codigo`, `nombre`, `orden`, `activo` | 1:N Envío_Archivo; N:M Establecimientos | `codigo` único |
| **DAT-04** | Período | Año + mes trabajable | `anio`, `mes`, `fecha_limite`, `estado` | 1:N Envíos | Único `(anio, mes)`; estado `abierto`/`en_revision`/`cerrado` |
| **DAT-05** | Usuario + asignación | Cuenta y alcance | `nombre`, `email`, `password`, `activo` | N:M Establecimientos; 1:N acciones | Email único; pivot `usuario_establecimiento` |
| **DAT-06** | Envío | Unidad mensual por establecimiento | `establecimiento_id`, `anio`, `mes`, `estado`, fechas | N:1 Establecimiento/Período; 1:N Archivos, Observaciones, Transiciones | Único `(establecimiento, anio, mes)` (RN-ENV-6) |
| **DAT-07** | Envío_Archivo | Versión de una serie en un envío | `envio_id`, `serie_id`, `version`, `es_vigente`, `sha256`, `ruta`, `estado`, conteos, `snapshot_id` | N:1 Envío, Serie, Plantilla, Snapshot | Único `(envio, serie, version)`; `sha256` único; una vigente por `(envio, serie)` |
| **DAT-08** | Validación | Hallazgo de una regla | `envio_archivo_id`, `etapa`, `codigo`, `severidad`, `mensaje`, `hoja`, `celda`, `valor` | N:1 Envío_Archivo | Se borran y regeneran al revalidar (idempotente) |
| **DAT-09** | Observación | Comentario del revisor | `envio_id`, `envio_archivo_id` (opcional), `autor_id`, `texto`, `resuelta` | N:1 Envío; opcional N:1 Archivo | Texto obligatorio; nivel envío o archivo |
| **DAT-10** | Envío_Transición | Auditoría de cambios de estado | `envio_id`, `estado_anterior`, `estado_nuevo`, `actor_id`, `motivo` | N:1 Envío | *Append-only* |
| **DAT-11** | Catálogo_Snapshot | Foto de catálogos usada para validar | `origen`, `hash_paquete`, conteos, `activo` | 1:N Plantillas, Diccionario, Excepciones | Un solo snapshot activo |
| **DAT-12** | Plantilla + hojas + cabecera | Definición del Excel por período/serie | `anio`, `mes`, `serie_id`, `version`, hojas, rangos, etiquetas | N:1 Serie, Snapshot | Única `(anio, mes, serie, version)` |
| **DAT-13** | Diccionario | Mapeo celdas → prestaciones | `snapshot_id`, `anio`, `serie`, `hoja`, `codigo_prestacion`, `linea`, `inicio`, `fin`, `posicion`, `tipo_dato` | N:1 Snapshot | Índices por búsqueda |
| **DAT-14** | Excepciones | Columnas no aplicables | `snapshot_id`, `anio`, `serie`, `hoja`, `codigo_prestacion`, `columna` | N:1 Snapshot | Estructura a confirmar (P6) |
| **DAT-15** | Establecimiento_Serie | Series exigidas por establecimiento | `establecimiento_id`, `serie_id`, `obligatoria`, vigencia | N:1 Establecimiento/Serie | Derivado del histórico + *override* (D5) |
| **DAT-16** | Auditoría | Eventos de seguridad | `usuario_id`, `evento`, `entidad`, `entidad_id`, `datos`, `ip`, `user_agent` | N:1 Usuario (nullable) | *Append-only*; sin UPDATE/DELETE |
| **DAT-17** | Notificación | Aviso en portal | `usuario_id`, tipo, datos, leído | N:1 Usuario | Fuente de verdad del aviso |

### 5.2 Estados

**Estado del envío** (M7 §5.1): `recibido`, `validado`, `con_errores`, `en_revision`, `observado`, `aprobado`, `cargado`, `anulado`.

> **Contradicción detectada (CONT-03).** `SPECS.md` RF-024 enumera `recibido → validado → en_revision → observado/aprobado → cargado/anulado` y omite `con_errores`, aunque RF-029 y el flujo de completitud lo usan. El plan asume el conjunto completo de M7 §5.1 y lo marca para confirmación.

**Estado del archivo** (M7 §5.2): `subido`, `validando`, `valido`, `con_errores`, `observado`, `aprobado`, `cargado`, `reemplazado`.

**Severidad de hallazgos:** `bloqueante`, `advertencia`, `info`.

### 5.3 Modelo conceptual vs. almacenamiento

| Decisión | Conceptual | Almacenamiento concreto |
|---|---|---|
| Envío | Unidad mensual por establecimiento | Fila en `envios` con unique `(establecimiento, anio, mes)` |
| Archivo | Versión de una serie | Fila en `envio_archivos`; `version` y `es_vigente` |
| Validación | Hallazgo de una regla | Filas en `validaciones`, regenerables |
| Catálogo | Espejo congelado | `catalogo_snapshots` + tablas espejo |
| Observación | Comentario humano | `observaciones` |
| Transición | Cambio de estado auditado | `envio_transiciones` |
| Auditoría | Evento de seguridad | `auditoria` (*append-only*) |
| Archivos físicos | Originales completos | `storage/app/private/envios/...`, nombre generado |

**Reglas de integridad que MySQL no puede imponer solo:**

| Regla | Implementación |
|---|---|
| Una sola versión vigente por `(envio, serie)` | Transacción con `lockForUpdate` sobre el envío + `UPDATE es_vigente=0` + `INSERT` |
| Hash duplicado dentro del mismo envío | Rechazo `ENV-029` |
| `version` consecutiva | `MAX(version)+1` dentro de la transacción |
| Envío aprobado no editable | Guarda en `EnvioService` + policy |
| Observación resuelta requiere nueva versión | Se marca al subir la versión siguiente de la serie |
| Un solo snapshot activo | Transacción en `CatalogoService::activarSnapshot` |

---

## 6. Módulos y componentes

### MOD-01 — Identidad y acceso

| Campo | Definición |
|---|---|
| **Responsabilidad** | Login, logout, sesión segura, throttle, recuperación de contraseña, roles/permisos, CRUD de usuarios, asignación usuario-establecimiento, perfil, reset por admin |
| **Requisitos** | RF-001, RF-002, RF-003, RF-004, RF-013, RF-014, RF-015, RF-016; RNF-008, RNF-009, RNF-012, RNF-013, RNF-014 |
| **Datos** | `users`, `usuario_establecimiento`, roles/permisos, `password_resets`, sesiones |
| **Entradas** | Credenciales, formularios de admin y perfil |
| **Salidas** | Sesión, redirecciones, correos |
| **Dependencias** | TEC-06, TEC-07, TEC-09; DEP-10 (D1), DEP-15 (D10) |
| **Verificación** | Tests de login/logout/throttle/perfiles; test positivo y negativo por permiso; CA-001 a CA-004, CA-013 a CA-016 |

### MOD-02 — Catálogos y estructura base

| Campo | Definición |
|---|---|
| **Responsabilidad** | Importar snapshot (ZIP/CSV), validar columnas/conteos/duplicados, activar snapshot (uno activo), CRUD de servicios/establecimientos/series/períodos, matriz establecimiento-serie, plantillas y mapeos |
| **Requisitos** | RF-005 a RF-012; RNF-015 |
| **Datos** | `servicios`, `establecimientos`, `series`, `periodos`, `establecimiento_serie`, `catalogo_snapshots`, `plantillas`, `plantilla_hojas`, `plantilla_cabecera`, `diccionario`, `excepciones` |
| **Entradas** | ZIP con CSV, formularios admin |
| **Salidas** | Snapshot inactivo/activo, tablas espejo pobladas, *diff* |
| **Dependencias** | DEP-03 (snapshot para validar), DEP-12 (D5), DEP-13 (D6); TEC-02 |
| **Verificación** | CA-005 a CA-012; conteos de referencia (30, 3.207, 939, 1.129, 185.526, 5.664, 2.977); unicidad; test de un solo snapshot activo |

### MOD-03 — Envíos, archivos y descargas

| Campo | Definición |
|---|---|
| **Responsabilidad** | Crear envío (unicidad), subir un archivo por petición con validación de tamaño/extensión/tipo, versionar, revalidar, descargar original y reporte, eliminar archivo no enviado, almacenamiento privado |
| **Requisitos** | RF-017, RF-018, RF-020, RF-021, RF-022, RF-023; RN-ENV-6, RN-ENV-8, RN-ENV-9, RN-ENV-12, RN-ENV-13; RNF-001, RNF-010 |
| **Datos** | `envios`, `envio_archivos`, `validaciones` (escritura vía MOD-04), `storage` |
| **Entradas** | POST de archivo; solicitudes de descarga |
| **Salidas** | 201 con resumen, descargas con `attachment` |
| **Dependencias** | MOD-04, MOD-05, MOD-08, MOD-11; TEC-04, TEC-08; DEP-03, DEP-04, DEP-16 |
| **Verificación** | CA-017, CA-018, CA-020, CA-021, CA-022, CA-023; tests de versionado, hash, unicidad, *path traversal* |

### MOD-04 — Motor de validación V0–V11

| Campo | Definición |
|---|---|
| **Responsabilidad** | Ejecutar la cadena de reglas por archivo (V0–V10) y la completitud por envío (V11); asignar código `ENV-###`, severidad y mensaje; producir hallazgos y conteos; generar el reporte |
| **Requisitos** | RF-018 (parte), RF-019, RF-021, RF-023, RF-025 (V11), RF-033 (parte); RN-ENV-2, RN-ENV-11; RNF-002, RNF-006, RNF-015 |
| **Datos** | `validaciones`, `plantillas`, `plantilla_hojas`, `plantilla_cabecera`, `diccionario`, `excepciones`, `establecimiento_serie`, `catalogo_snapshots` |
| **Entradas** | Archivo + `ContextoValidacion` (envío, snapshot, plantilla) |
| **Salidas** | `ResultadoValidacion` (hallazgos, conteos) |
| **Dependencias** | DEP-02 (paridad F0), DEP-03, DEP-04, DEP-16; TEC-04 |
| **Verificación** | Un test por regla (principio 6); *golden files* (principio 10); matriz mínima de *fixtures*; CA-019, CA-021, CA-023 |

### MOD-05 — Estados y transiciones

| Campo | Definición |
|---|---|
| **Responsabilidad** | Servicio único que escribe el estado del envío; valida guardas (período, bloqueantes, advertencias, segregación, inmutabilidad); registra `envio_transiciones`; dispara auditoría y notificaciones; recalcula el estado tras cada subida |
| **Requisitos** | RF-024, RF-025, RF-026 (estado), RF-027, RF-028, RF-029, RF-030; RN-ENV-2, RN-ENV-3, RN-ENV-7, RN-ENV-8, RN-ENV-10; RNF-002 |
| **Datos** | `envios.estado`, `envio_transiciones`, `observaciones` (cambios de estado) |
| **Entradas** | Intenciones: enviar, observar, aprobar, reabrir, anular, confirmar carga |
| **Salidas** | Estado nuevo + transición + notificación |
| **Dependencias** | MOD-04 (V11), MOD-06, MOD-08, MOD-09, MOD-10; DEP-06 |
| **Verificación** | Test por transición válida y prohibida (principio 7); CA-024 a CA-031 |

### MOD-06 — Revisión y observaciones

| Campo | Definición |
|---|---|
| **Responsabilidad** | Bandeja del revisor con filtros y contadores; detalle del envío; registrar observaciones por envío o archivo; *checklist* de advertencias revisadas; resolver observaciones al reenviar |
| **Requisitos** | RF-026, RF-031, RF-032; RNF-004, RNF-014 |
| **Datos** | `observaciones`, `envio_archivos`, `validaciones` |
| **Entradas** | Filtros, texto de observación, confirmación |
| **Salidas** | Listas, historial, resumen de aprobación |
| **Dependencias** | MOD-03, MOD-04, MOD-05 |
| **Verificación** | CA-027, CA-032, CA-033; tests de paginación y filtros; RN-ENV-7 (no autoaprobación) |

### MOD-07 — Completitud y tablero

| Campo | Definición |
|---|---|
| **Responsabilidad** | Matriz establecimientos × series por período/SS con semáforo; totales; exportación CSV/Excel; *drill-down*; panel por rol |
| **Requisitos** | RF-033, RF-035, RF-036; OBJ-07; RNF-005 |
| **Datos** | Consulta sobre `establecimientos`, `series`, `envios`, `envio_archivos`, `validaciones` |
| **Entradas** | Período + Servicio de Salud |
| **Salidas** | Matriz, totales, archivo exportado |
| **Dependencias** | MOD-02, MOD-03; DEP-12 (D5) |
| **Verificación** | CA-034, CA-035, CA-036; prueba de rendimiento 30 × 7 (p95 < 2 s) |

### MOD-08 — Auditoría

| Campo | Definición |
|---|---|
| **Responsabilidad** | Registrar eventos *append-only* (login, subida, descarga, validación, observación, aprobación, reapertura, carga, importación, cambio de permisos); consulta admin con filtros |
| **Requisitos** | RF-037; RNF-011; RN-ENV-3; RNF-016 |
| **Datos** | `auditoria` |
| **Entradas** | Eventos internos de los demás módulos |
| **Salidas** | Filas inmutables; listado con filtros |
| **Dependencias** | Todos los módulos (emisores) |
| **Verificación** | CA-037; test de inmutabilidad (sin UPDATE/DELETE); retención propuesta (D9) |

### MOD-09 — Notificaciones

| Campo | Definición |
|---|---|
| **Responsabilidad** | Notificación en BD siempre; correo síncrono tolerante a fallo; reenvío manual por admin |
| **Requisitos** | RF-038; D8; RNF-014 |
| **Datos** | `notifications`, correo |
| **Entradas** | Eventos de cambio de estado |
| **Salidas** | Notificación en portal + correo |
| **Dependencias** | MOD-05, MOD-06, TEC-09 |
| **Verificación** | CA-038; test de que el fallo de correo no revierte la operación |

### MOD-10 — Paquete y puente

| Campo | Definición |
|---|---|
| **Responsabilidad** | Generar ZIP con archivos aprobados + `manifiesto.json` (hash, conteos, snapshot, fechas, HMAC opcional); descarga por revisor/admin; confirmación manual de carga; API para la opción B (diferida) |
| **Requisitos** | RF-039, RF-030 (parte); OBJ-08; D7 |
| **Datos** | Manifiesto, ZIP, `auditoria` |
| **Entradas** | Envío `aprobado`/`cargado` |
| **Salidas** | ZIP verificable |
| **Dependencias** | MOD-03, MOD-05; DEP-14 |
| **Verificación** | CA-039; verificación de hash/HMAC; CA-031 |

### MOD-11 — Seguridad, operación y observabilidad (transversal)

| Campo | Definición |
|---|---|
| **Responsabilidad** | CSRF, escape, cabeceras, *rate limit*, HTTPS, `APP_DEBUG=false`, logs sin datos sensibles, *health check*, rotación de logs, respaldo/restauración, zona horaria, despliegue FTP |
| **Requisitos** | RNF-007 a RNF-013, RNF-016; RN-ENV-9; principios 18 a 22 |
| **Datos** | Configuración, logs, `storage` |
| **Entradas** | Peticiones y operaciones |
| **Salidas** | Respuestas seguras, logs, *health* |
| **Dependencias** | Todos los módulos |
| **Verificación** | Tests de seguridad; *health check*; prueba de respaldo/restauración |

---

## 7. Lógica y operaciones

Nombres propuestos en inglés para código, con dominio en español (C-005). No son implementación: son contratos de servicio.

| ID | Operación | Responsabilidad | Parámetros relevantes | Resultado | Errores previsibles | Dependencias |
|---|---|---|---|---|---|---|
| **OP-01** | `EnvioService::crear` | Crea el envío si no existe | `establecimientoId`, `anio`, `mes`, `usuarioId` | `Envio` en `recibido` | Período no abierto; envío duplicado; establecimiento no autorizado | MOD-01, MOD-02 |
| **OP-02** | `EnvioService::subirVersion` | Sube un archivo, versiona, valida y recalcula | `envioId`, `UploadedFile`, `usuarioId` | `EnvioArchivo` + resumen | `ENV-029` (hash duplicado), `ENV-030` (lock), período cerrado, estado no editable, `ENV-001`/`002`/`003`/`004` | MOD-04, MOD-05, MOD-08 |
| **OP-03** | `EnvioService::revalidar` | Revalida un archivo existente de forma idempotente | `envioArchivoId`, `usuarioId` | Hallazgos regenerados | Archivo no editable; lock | MOD-04, MOD-05 |
| **OP-04** | `ArchivoService::descargar` | Descarga el original con política | `envioArchivoId`, `usuarioId` | Archivo binario `attachment` | Policy denegada; archivo no encontrado | MOD-01, MOD-11 |
| **OP-05** | `ReporteService::descargar` | Genera el reporte de validación | `envioArchivoId`, `formato`, `usuarioId` | `.xlsx` o `.csv` | Sin hallazgos (reporte vacío válido) | MOD-04 |
| **OP-06** | `EstadoEnvioService::recalcular` | Deriva el estado del envío desde archivos vigentes + V11 | `Envio` | Estado persistido | Incoherencia de datos | MOD-04 |
| **OP-07** | `EstadoEnvioService::transicionar` | Aplica guardas y cambia el estado | `Envio`, `evento`, `usuarioId`, `payload` | Estado nuevo + transición + notificación | Transición prohibida; bloqueantes > 0; advertencias no revisadas; autoaprobación; período cerrado | MOD-05, MOD-06, MOD-08, MOD-09 |
| **OP-08** | `ObservacionService::registrar` |Registra observación por envío o archivo | `envioId`, `archivoId` (opcional), `texto`, `usuarioId` | Observación + notificación | Texto vacío; nivel inválido | MOD-05, MOD-09 |
| **OP-09** | `CompletitudService::matriz` | Arma la matriz con semáforo aplicando alcance | `anio`, `mes`, `servicioId`, `usuarioId` | Matriz + totales | Sin establecimientos activos | MOD-02, MOD-03 |
| **OP-10** | `CompletitudService::exportar` | Exporta CSV/Excel respetando filtros | `anio`, `mes`, `servicioId`, `formato` | Archivo descargable | Sin datos | MOD-07 |
| **OP-11** | `PaqueteService::generar` | Genera ZIP + manifiesto | `envioId`, `usuarioId` | ZIP con estructura y hashes | Envío no aprobado; archivo faltante | MOD-03, MOD-05 |
| **OP-12** | `CatalogoService::importarSnapshot` | Valida y carga el snapshot en transacción | `UploadedFile` (ZIP CSV), `usuarioId` | Snapshot inactivo | Columnas inválidas; conteos fuera de rango; duplicados | MOD-02 |
| **OP-13** | `CatalogoService::activarSnapshot` | Activa un snapshot y desactiva el anterior | `snapshotId`, `usuarioId` | Snapshot activo único | Snapshot inexistente | MOD-02 |
| **OP-14** | `ValidadorArchivo::validar` | Ejecuta la cadena V0–V10 | `ContextoValidacion` | `ResultadoValidacion` | Bloqueantes detienen la cadena | MOD-04 |
| **OP-15** | `ValidadorEnvio::completitud` | Evalúa V11 sobre series requeridas | `Envio` | Hallazgo `ENV-025` si faltan | Series requeridas no definidas (D5) | MOD-02, MOD-04 |
| **OP-16** | `AuditoriaService::registrar` | Inserta evento *append-only* | `usuarioId`, `evento`, `entidad`, `entidadId`, `datos` | Fila de auditoría | Fallo de BD (dentro de la transacción) | MOD-08 |
| **OP-17** | `Notificador::enviar` | Notificación BD + correo tolerante | Destinatario, evento, datos | Notificación + correo (opcional) | Correo falla: no revierte | MOD-09 |

---

## 8. Flujos técnicos

### FT-01 — Subida y validación inmediata (RF-017 a RF-021; CA-017 a CA-021)

1. El encargado entra a `/envios/crear`, selecciona período (año/mes) y establecimiento.
2. `EnvioService::crear` busca o crea el envío con estado `recibido` (RN-ENV-6).
3. El encargado selecciona serie y archivo (`.xls`, `.xlsx`, `.xlsm`, ≤ 2 MB) y hace POST a `/envios/{id}/archivos` (un archivo por petición).
4. `SubirArchivoRequest` valida autorización (policy), período abierto, extensión y tamaño.
5. `EnvioService::subirVersion` en transacción:
   - `lockForUpdate` sobre el envío.
   - Calcula `version = MAX(version)+1` y desmarca la vigente anterior.
   - Guarda el archivo en `storage/app/private/envios/...` con nombre generado (SHA-256).
   - Inserta `envio_archivos` con estado `subido`.
   - Ejecuta `ValidadorArchivo::validar` con el snapshot activo.
   - Inserta `validaciones` y actualiza el archivo (estado, conteos, `validado_en`).
   - `EstadoEnvioService::recalcular`.
   - Audita subida y validación.
6. Retorna 201 con resumen: "Serie A validada: 1.234 filas, 2 advertencias".
7. La UI muestra "Enviar a revisión" si todos los archivos vigentes están `validado` y V11 está OK.

### FT-02 — Revisión, observación y aprobación (RF-025 a RF-028; CA-026 a CA-029)

1. El encargado confirma "Enviar a revisión" → `EstadoEnvioService::transicionar('enviar')`: valida 0 bloqueantes + V11 OK → `en_revision` → notifica al revisor.
2. El revisor abre `/supervision` con filtros por SS, establecimiento, período, estado y antigüedad.
3. El revisor abre el detalle y ve la grilla de series (estado, versión, filas, bloqueantes, advertencias).
4. Si hay correcciones:
   - `ObservacionService::registrar` con *checklist* por serie y texto obligatorio → `observado` → notifica al encargado.
   - El encargado corrige y sube nueva versión (FT-01) → el estado vuelve a `recibido` y el ciclo se repite.
5. Si todo está conforme:
   - El revisor abre "Aprobar", confirma advertencias revisadas y comenta.
   - `transicionar('aprobar')` valida 0 bloqueantes + advertencias marcadas + RN-ENV-7 → `aprobado`, archivos `aprobado`, transición, notificación.

### FT-03 — Completitud mensual (RF-033, RF-035, RF-036; CA-034 a CA-036)

1. El usuario entra a `/completitud`, selecciona período y SS.
2. `CompletitudService::matriz` consulta establecimientos activos, series requeridas (`establecimiento_serie`) y archivos vigentes.
3. La UI renderiza el semáforo: 🟢 aprobado/cargado, 🟡 en_revision, 🔴 faltante/con errores, ⚪ no requerida.
4. Se muestran totales: con envío completo, con observaciones, sin envío.
5. `CompletitudService::exportar` genera CSV/Excel respetando filtros.
6. Click en celda → `/envios/{id}` (*drill-down*).

### FT-04 — Importación y activación de catálogo (RF-005, RF-006; CA-005, CA-006)

1. El admin sube un ZIP con CSV (servicios, establecimientos, series, plantillas, diccionario, excepciones).
2. `CatalogoService::importarSnapshot` valida columnas esperadas, codificación, conteos de referencia y ausencia de duplicados.
3. Carga las tablas espejo en una transacción y crea un `catalogo_snapshot` con `activo=0`.
4. El admin revisa el *diff* y ejecuta `activarSnapshot`.
5. La activación deja un solo snapshot activo; los envíos nuevos lo usan y los existentes conservan el suyo.

### FT-05 — Aprobación, paquete y carga (RF-027, RF-030, RF-039; CA-031, CA-039)

1. El revisor aprueba (FT-02) y el envío queda `aprobado`.
2. `PaqueteService::generar` crea el ZIP con `archivos/`, `validacion/` y `manifiesto.json` (hash, conteos, snapshot, fechas, HMAC opcional).
3. El revisor descarga el ZIP y carga los archivos manualmente en CARGAS REM (opción A).
4. El revisor/admin confirma la carga en el portal → `transicionar('confirmar_carga')` → `cargado` + `fecha_carga` + auditoría.

### FT-06 — Reapertura y anulación (RF-028, RF-029; CA-029, CA-030)

1. El admin selecciona un envío `aprobado`/`cargado` y ejecuta "Reabrir" con motivo obligatorio.
2. `transicionar('reabrir')` valida que solo admin lo hace y registra el motivo en auditoría → `observado`/`recibido` según la regla de M7 §5.3.
3. Para anular, el admin ejecuta "Anular" con motivo → `anulado`; no revierte archivos subidos.

---

## 9. Interfaz y navegación

### 9.1 Pantallas

Se toman de `SPECS.md` §7.1 y `MODULO_M7_PROFUNDIZADO.md` §10.3.

| Pantalla | Ruta | Rol | Componentes clave | Estados vacíos |
|---|---|---|---|---|
| Login | `/` | Público | Formulario email/password, throttle, "¿Olvidó su contraseña?" | — |
| Dashboard encargado | `/` (auth) | Encargado | Tarjetas de envíos en curso, pendientes de revisión y completitud | "Sin envíos en este período" |
| Crear/Ver envío | `/envios/{id}` | Encargado/Revisor/Admin | Cabecera (estado, período, establecimiento), grilla de series, historial, observaciones | "Sin archivos aún" |
| Subir archivo | Modal en `/envios/{id}` | Encargado | Input file, validación cliente (ext/tamaño), progreso | — |
| Resultado validación | `/envios/{id}/archivos/{archivoId}/validacion` | Encargado/Revisor/Admin | Resumen, filtros por severidad, tabla hoja/celda/mensaje, descarga | "Sin hallazgos" |
| Bandeja revisor | `/supervision` | Revisor/Admin | Filtros, contador por estado, paginación servidor | "No hay envíos pendientes" |
| Aprobar | Modal en `/envios/{id}` | Revisor/Admin | Resumen, bloqueantes (debe ser 0), *check* de advertencias, comentario | "No se puede aprobar con bloqueantes" |
| Observar | Modal en `/envios/{id}` | Revisor/Admin | *Checklist* por serie + texto obligatorio | "Escriba al menos una observación" |
| Completitud | `/completitud` | Todos | Matriz + totales + exportación; *drill-down* | "No hay establecimientos activos" |
| Catálogos admin | `/admin/catalogos` | Admin | Snapshot activo, conteos, importar ZIP/CSV, historial | "El archivo no tiene el formato esperado" |
| Usuarios admin | `/admin/usuarios` | Admin | CRUD + asignación de establecimientos + reset | — |
| Auditoría | `/admin/auditoria` | Admin | Filtros por usuario, evento, entidad, fecha | "Sin registros" |

### 9.2 Componentes reutilizables

| Componente | Responsabilidad |
|---|---|
| `EstadoBadge` | Muestra el estado con el mismo color y nombre en todas las pantallas |
| `SemáforoCelda` | Pinta la celda de la matriz según el estado del envío/archivo |
| `TablaServidor` | Paginación, orden y filtro en el servidor (Eloquent) |
| `ModalConfirmacion` | Confirmación explícita para aprobar, reabrir y anular |
| `DropzoneArchivo` | Validación cliente de extensión y tamaño, progreso de subida |
| `FiltrosBandeja` | Filtros por SS, establecimiento, período, estado y antigüedad |
| `TarjetaMetrica` | Totales del panel y de completitud |
| `AlertaError` | Mensaje accionable + código `ENV-###`/`APP-###` |

### 9.3 Estados de interfaz

- **Cargando:** spinners en tablas y subidas; no bloquear la navegación.
- **Vacío:** mensajes en español con acción sugerida (ej.: "Sin archivos aún. Suba el primer archivo de la serie A.").
- **Error:** nunca "error 500" pelado; mensaje accionable + código de referencia + acción.
- **Confirmación:** solo en acciones irreversibles (aprobar, reabrir, anular).
- **Éxito:** resumen claro (ej.: "Serie A validada: 1.234 filas, 2 advertencias").

### 9.4 Comunicaciones

- Formularios POST con `@csrf`.
- Descargas GET con `Content-Disposition: attachment`; sin `target="_blank"`.
- Exportación CSV/Excel como descarga.
- Sin `Inertia.js` ni React en el MVP: Blade + AdminLTE es más simple y no exige Node en producción. La referencia Zenith es solo visual.

---

## 10. Persistencia y consistencia

### 10.1 Transacciones

| Operación | Transacción | Razón |
|---|---|---|
| `subirVersion` | Sí | Versionado + archivo + hallazgos + estado + auditoría deben ser atómicos |
| `revalidar` | Sí | Borra hallazgos anteriores y regenera; debe ser idempotente |
| `transicionar` | Sí | Estado + transición + auditoría + notificación |
| `importarSnapshot` | Sí | Tablas espejo completas o nada |
| `activarSnapshot` | Sí | Un solo snapshot activo |
| `generar` (paquete) | No (lectura) | Solo lee archivos aprobados y genera ZIP |

### 10.2 Concurrencia

| Mecanismo | Uso |
|---|---|
| `lockForUpdate` sobre el envío | Versionado y transiciones: evita dos escrituras simultáneas sobre el mismo envío |
| `Cache::lock('validacion', N=2)` con espera 10 s | Límite de validaciones simultáneas (RNF-006); si no se obtiene, mensaje "El servidor está procesando otros archivos, reintente en unos segundos" |
| Unique keys | `(establecimiento, anio, mes)`, `(envio, serie, version)`, `sha256` |
| Transacción + `UPDATE es_vigente=0` | Una sola versión vigente por `(envio, serie)` |

### 10.3 Idempotencia

- `revalidar` borra los hallazgos del archivo y los regenera con los mismos parámetros.
- Subir el mismo SHA-256 en el mismo envío se rechaza con `ENV-029`.
- El envío se crea una sola vez; el reintento no duplica.
- Los formularios usan token CSRF; los POST no se repiten automáticamente.

### 10.4 Snapshot y repetición

- Al subir, el archivo registra el `snapshot_id` activo en ese momento (RN-ENV-11).
- Al revalidar, se usa el `snapshot_id` del archivo para que el resultado sea reproducible (P6).
- Si el snapshot original quedó inactivo, el archivo sigue apuntando a él; no se reinterpreta el pasado con el catálogo nuevo.
- **Aclaración solicitada (CONT-05):** M7 §6.2 RN-ENV-11 se lee como "snapshot activo al subir"; P6 como "snapshot guardado al revalidar". El plan adopta la segunda lectura por reproducibilidad y la somete a confirmación.

### 10.5 Recuperación

- El archivo se guarda antes de validar; si la validación falla, el archivo queda con estado `con_errores` y sus hallazgos. No se pierde el trabajo.
- El correo se envía después del *commit* (`afterCommit`); si falla, la operación no se revierte.
- Reapertura admin con motivo para corregir un envío `aprobado`/`cargado`.
- Respaldo: `mysqldump` + copia de `storage/` (D13 pendiente con TI); restauración documentada y probada.

---

## 11. Validaciones y errores

### 11.1 Puntos de aplicación técnica

| Capa | Qué valida | Ejemplos |
|---|---|---|
| FormRequest | Formato, autorización, período | Archivo requerido, extensión, ≤ 2 MB, ZIP con columnas esperadas |
| Policy | Autorización sobre el recurso | RN-ENV-1, RN-ENV-7, RN-ENV-8, RN-ENV-9 |
| Servicio de envío | Reglas de negocio | Unicidad, versión, hash, estado editable, período |
| Motor V0–V11 | Contenido del archivo | `ENV-001` a `ENV-032` |
| Base de datos | Integridad | Unique keys, FK, modo estricto |
| Middleware | Seguridad | CSRF, throttle, cabeceras |

### 11.2 Reglas de negocio → punto técnico

| Regla | Punto de aplicación |
|---|---|
| RN-ENV-1 (aislamiento) | Policy + *query scope* en cada consulta y descarga |
| RN-ENV-6 (unicidad) | Unique key + guarda en `EnvioService::crear` |
| RN-ENV-7 (segregación) | Policy: el revisor no aprueba envíos de sus establecimientos |
| RN-ENV-8 (inmutabilidad) | Policy + guarda en `transicionar` |
| RN-ENV-9 (descargas) | Controlador + policy + `attachment` + `nosniff` |
| RN-ENV-10 (período cerrado) | Guarda en `crear`/`subirVersion` |
| RN-ENV-11 (snapshot) | `envio_archivos.snapshot_id` |
| RN-ENV-12 (nombre) | Regla V1 |
| RN-ENV-13 (hash) | Guarda + unique key |
| V0–V11 | Reglas de validación (`ENV-###`) |
| Severidad | Catálogo de reglas; no se degrada una bloqueante |

### 11.3 Manejo de errores

- Todo error que llega al usuario lleva código (`ENV-###` o `APP-###`), severidad y acción sugerida, en español.
- Nunca se muestra un "error 500" sin código.
- Los logs no guardan contenido de celdas ni observaciones completas; solo IDs y códigos.

| Fallo | Comportamiento |
|---|---|
| BD inaccesible | Error 503 con mensaje "Servicio temporalmente no disponible"; el *health check* lo detecta |
| `storage` no escribible | Error 500 con código; log en `logs/php-error.log` |
| Correo falla | No revierte la operación; notificación en BD; log "No se pudo enviar correo"; admin puede reenviar |
| Lock de validación no obtenido | Mensaje "El servidor está procesando otros archivos, reintente en unos segundos" |
| Archivo > 2 MB | Rechazo en cliente y servidor (`ENV-001`) |
| Extensión no permitida | Rechazo (`ENV-002`) |
| Archivo corrupto/cifrado | Rechazo (`ENV-003`, `ENV-004`) |
| Hash duplicado | Rechazo (`ENV-029`) |
| Validación excede el tiempo | Rechazo con reintento (`ENV-030`) |

---

## 12. Seguridad y privacidad

### 12.1 Autenticación y sesión

- Login con email/usuario (D1 pendiente); contraseña robusta; `throttle:5,1`.
- `session.secure=1`, `httponly=1`, `samesite=lax`; expiración 8 h; regeneración de ID al autenticar.
- Recuperación de contraseña con token de un uso y expiración corta.
- 2FA: **pendiente** (D10). Si se habilita, Fortify lo soporta; mientras tanto, política de contraseñas y auditoría.

### 12.2 Autorización

- RBAC con roles `encargado`, `revisor`, `admin`, `consulta` (opcional).
- Policies por acción + *query scopes* para listados.
- Aislamiento por establecimiento en cada consulta y descarga (RN-ENV-1).
- Tests positivos y negativos por permiso (Constitución principio 8).

### 12.3 Archivos

- Fuera de `public/`, nombre generado, permisos restrictivos.
- Validación de tipo real (`finfo` + firma ZIP/OLE2); macros nunca se ejecutan (advertencia `ENV-028`).
- Protección contra ZIP bomb (ratio y número de entradas).
- Descarga autenticada con policy y `Content-Disposition: attachment`, `nosniff`.

### 12.4 Aplicación

- CSRF en todos los formularios; escape automático de Blade; prohibido `{!! !!}` con datos de usuario.
- Consultas con Eloquent/*Query Builder* y *bindings*.
- `APP_DEBUG=false` en producción; sin `phpinfo` ni páginas de diagnóstico.
- Cabeceras: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, CSP básica, HSTS si el servidor lo permite.
- *Rate limit* en login, subida y descarga.
- Secretos en `.env` (ignorado); nunca en el repositorio; FTP con TLS.

### 12.5 Auditoría y privacidad

- Auditoría *append-only* de login, subida, descarga, validación, observación, aprobación, reapertura, carga, importación y cambio de permisos.
- Datos REM son confidenciales (Ley 21.719): minimización, aviso en login, revisión legal antes de producción.
- Retención propuesta 5 años (D9 pendiente con jurídica).
- Antivirus (D12) y respaldo (D13) pendientes con TI.

---

## 13. Estrategia de pruebas

### 13.1 Niveles

| Nivel | Qué cubre | Herramienta |
|---|---|---|
| Unitario | Cada regla V0–V11, máquina de estados, policies, servicios | PHPUnit / Pest |
| Integración | Subida real, validación, persistencia, versionado, locks | PHPUnit + base de prueba |
| Paridad | *Golden files* contra la salida del escritorio | Script de comparación (tolerancia 0) |
| Autorización | Aislamiento, segregación, permisos positivos/negativos | PHPUnit + *factories* |
| Seguridad | Tipo real, doble extensión, ZIP bomb, *path traversal*, CSRF, XSS | Pruebas dirigidas + revisión |
| Rendimiento | 2 validaciones simultáneas, 100 envíos, 2 MB, 30 × 7 | JMeter/k6 o script |
| E2E / UAT | Ciclo completo con 3 roles y usuarios reales | Guion de tareas |

### 13.2 Criterios de aceptación → nivel de prueba

| CA | Requisito | Nivel | Qué prueba | Qué simula |
|---|---|---|---|---|
| CA-001 | RF-001 | Integración | Login válido crea sesión y redirige | — |
| CA-002 | RF-002 | Integración | Logout destruye sesión | — |
| CA-005 | RF-005 | Integración | Importa ZIP, pobla tablas, snapshot inactivo | Almacén real |
| CA-017 | RF-017 | Integración | Crea envío; segundo intento falla | — |
| CA-018 | RF-018 | Integración | Sube .xlsx, guarda y valida | Almacén real |
| CA-019 | RF-019 | Unitario/Integración | `ENV-016` y `ENV-017` con hoja faltante | Contexto simulado |
| CA-020 | RF-020 | Integración | Versiona; anterior no vigente; hash único | — |
| CA-024 | RF-024 | Unitario | Transición válida escribe estado; inválida lanza excepción | — |
| CA-026 | RF-025 | Integración | Envía a revisión, notifica | Correo simulado |
| CA-027 | RF-026 | Integración | Observa con texto, estado `observado` | — |
| CA-028 | RF-027 | Integración | Aprueba con 0 bloqueantes y advertencias revisadas | — |
| CA-029 | RF-028 | Integración | Reabre con motivo y audita | — |
| CA-034 | RF-033 | Integración | Matriz 7 × 7 con semáforo correcto | Datos semilla |
| CA-037 | RF-037 | Unitario | Auditoría inserta fila; no hay UPDATE/DELETE | — |
| CA-039 | RF-039 | Integración | ZIP con archivos y manifiesto verificable | — |

> Los CAs sin entrada en esta tabla (RF-003, RF-004, RF-006 a RF-016, RF-021 a RF-023, RF-030 a RF-032, RF-035, RF-036, RF-038) se prueban en el mismo nivel que su requisito hermano: unitario si es regla aislada, integración si toca persistencia o HTTP.

### 13.3 Automatización y barra

- Suite corta en SQLite para unitarias e integración rápida.
- Suite de paridad y concurrencia en MySQL 8 (Docker).
- Sin umbral de cobertura global; **obligatorio el test de la regla, transición o permiso que el cambio toca** (Constitución principios 6 a 9).
- Un cambio en V6–V8 exige comparación contra *golden files* (principio 10).
- Un cambio sin test no se fusiona (principio 9).

### 13.4 Criterios de aceptación por fase

Se conservan de `SPECS.md` §12.2:

| Fase | Criterio |
|---|---|
| **F0** | El lector reproduce el 100 % de los registros y errores de los *golden files*; el negocio firma la política de severidad |
| **F1** | El admin importa un snapshot, crea usuarios y asigna establecimientos; la auditoría registra todo |
| **F2** | El encargado sube 7 archivos, ve el resultado por serie y descarga el reporte; los reenvíos versionan |
| **F3** | El revisor observa y aprueba; el encargado corrige y reenvía; no se puede aprobar con bloqueantes |
| **F4** | La matriz muestra faltantes y estados del período; exporta correctamente |
| **F5** | El ZIP se verifica (hash/HMAC) y el importador/marcado confirma la carga |
| **F6** | Pruebas de autorización, carga y UAT aprobadas; respaldo/restauración probados |

---

## 14. Secuencia de implementación

Las fases siguen `MODULO_M7_PROFUNDIZADO.md` §16 y se alinean con los niveles L1–L3 de `SPECS.md`. Cada fase entrega algo verificable sin construir toda la solución.

| Fase | Objetivo | Componentes / capacidades | Requisitos | Dependencias previas | Entregable | Condiciones de verificación |
|---|---|---|---|---|---|---|
| **F0 — Descubrimiento y paridad** | Cerrar D-STACK, decisiones de negocio y paridad con el validador actual | Entorno local; *golden files*; mapa de cabecera; semántica de `Diccionario`/`Excep`/`colexcep`; política de severidad; matriz de series; documentación de SAX; fixtures de permisos | OBJ-01; RNF-002; D1–D7 | Ninguna (no es código de aplicación) | Documento de paridad + *golden outputs* + decisión de stack + fixtures | Criterio F0; paridad 100 %; negocio firma severidad |
| **F1 — Base (L1)** | Modelo, esqueleto, auth, catálogos y auditoría | MOD-01, MOD-02, MOD-08, DAT-01 a DAT-06, DAT-11 a DAT-16; validador *stub* | RF-001 a RF-016; RF-037; OBJ-02, OBJ-06 | F0 (stack cerrada) | Login, admin de catálogos, períodos, estructura de datos, auditoría funcionando | Criterio F1 |
| **F2 — Carga y validación (L2)** | Subida, versionado, V0–V11, reporte y estados iniciales | MOD-03, MOD-04, MOD-05 (parcial); DAT-07, DAT-08, DAT-10; FT-01 | RF-017 a RF-025; RF-033 (parte); OBJ-01, OBJ-04 | F1; golden files | Envío con archivos validados, reporte descargable, reenvíos versionados | Criterio F2 |
| **F3 — Flujo de revisión (L3)** | Observaciones, aprobación, bandeja y notificaciones | MOD-05 (completo), MOD-06, MOD-09; FT-02, FT-06 | RF-024 a RF-032; RF-038; OBJ-02, OBJ-05 | F2 | Ciclo completo encargado → revisor; no se aprueba con bloqueantes | Criterio F3 |
| **F4 — Completitud y tablero (L3)** | Matriz, totales, exportación y panel por rol | MOD-07; FT-03 | RF-033, RF-035, RF-036; OBJ-07 | F2, F3 | Matriz con semáforo, totales y exportación | Criterio F4 |
| **F5 — Puente (L3)** | Paquete aprobado y confirmación de carga | MOD-10; FT-05 | RF-039, RF-030; OBJ-08 | F3 | ZIP verificable + confirmación manual de carga | Criterio F5 |
| **F6 — Endurecimiento y salida** | Seguridad, rendimiento, UAT, respaldo y manuales | MOD-11; paridad final; pruebas de carga; documentación | RNF-001 a RNF-016; OBJ-08 | F1 a F5 | Informe de pruebas, manual de usuario y de operación; respaldo/restauración probado | Criterio F6 |

### 14.1 Orden de trabajo

```text
F0 (gate: stack + paridad)
 └─► F1 (base)
      ├─► F2 (carga y validación)
      │    ├─► F3 (revisión)
      │    │    └─► F5 (puente)
      │    └─► F4 (completitud)
      └─► F6 (endurecimiento, tras F3–F5)
```

Trabajo que puede avanzar en paralelo sin esperar resultados inexistentes:

- **F0**: la importación de catálogos (CSV) y el catálogo de errores `ENV-###` pueden empezar antes de cerrar D-STACK, porque no son código de aplicación.
- **F1**: auth/RBAC y catálogos son independientes entre sí; la auditoría transversal debe estar lista antes que los primeros escritores.
- **F2/F3**: la bandeja del revisor (MOD-06) puede construirse sobre datos semilla mientras la validación termina.
- **F4**: la consulta de completitud solo necesita `establecimiento_serie`, `envios` y `envio_archivos`; puede avanzar en paralelo de F3.
- **F6**: las pruebas de rendimiento y el respaldo no requieren funcionalidad nueva; pueden prepararse desde F2.

---

## 15. Dependencias y orden de trabajo

| ID | Necesita | Por qué | Condición de satisfacción | Trabajo independiente posible |
|---|---|---|---|---|
| **DEP-01** | Cierre de D-STACK (`PEND-001`) | La Constitución prohíbe introducir stack mientras D-STACK está abierta | TI responde y se elige A1/A2/B/C/D/E; se enmienda la constitución y se registra en `decisions.md` | Documentación, *fixtures*, catálogo de errores, máquina de estados |
| **DEP-02** | Paridad F0 con *golden files* | Principio 10: ningún cambio en V6–V8 se fusiona sin comparación | El lector reproduce el 100 % de registros y errores de los *golden files* | Definición de la máquina de estados, contratos y UI |
| **DEP-03** | Snapshot activo importado | La validación necesita catálogo (`Diccionario`, `Thojas`, `version`, `Excep`) | Existe un snapshot `activo=1` con conteos correctos | Importador, validaciones de columna y UI de catálogos |
| **DEP-04** | Plantillas y mapeos | V2, V3, V5 y V6 dependen de `plantillas`, `plantilla_hojas` y `plantilla_cabecera` | Plantillas cargadas para los períodos a validar | Modelo de datos y CRUD de series |
| **DEP-05** | Auth y RBAC | Toda ruta protegida requiere identidad y permisos | Login, roles y matriz de permisos funcionando con tests | Catálogos, definición de políticas |
| **DEP-06** | `EstadoEnvioService` | Solo este servicio escribe el estado | Servicio con guardas y transiciones testeadas | UI de bandeja y detalle |
| **DEP-07** | Versionado de archivos | Los reenvíos y observaciones por serie dependen de versiones | `envio_archivos` con `version`/`es_vigente` funcionando | Observaciones y completitud |
| **DEP-08** | Aprobación | El paquete solo se genera sobre envíos aprobados | Flujo de aprobación con guardas | Generación del manifiesto (sin datos reales) |
| **DEP-09** | Almacenamiento privado | Las descargas exigen archivos fuera de `public/` | Archivo guardado con nombre generado y descarga autenticada | Validación y estados |
| **DEP-10** | Decisión D1 (identidad) | Define el alta de usuarios | Negocio/TI confirma fuente de identidad | CRUD de usuarios con datos de prueba |
| **DEP-11** | Decisión D4 (plazo) | Define `fecha_limite` y cierre de período | Se confirma día 5/10/15/variable | Modelo de períodos con `fecha_limite` configurable |
| **DEP-12** | Decisión D5 (series obligatorias) | Define V11 y completitud | Se confirma histórico + *override* | CRUD de `establecimiento_serie` |
| **DEP-13** | Decisión D6 (SAX) | Define si SAX es serie normal o especial | Se confirma tratamiento y documentación de SAX | Plantillas de las demás series |
| **DEP-14** | Decisión D7 (puente) | Define el alcance del paquete | MVP = A (descarga manual) asumido; B queda fuera | Generación del ZIP con manifiesto (A) |
| **DEP-15** | Decisión D10 (2FA) | Define si se exige 2FA | Se confirma si aplica a admins/revisores/todos | Auth con Fortify (puerta abierta) |
| **DEP-16** | *Golden files* y paridad | Base de la validación real | 12–20 archivos reales anonimizados con salida esperada | Definición de reglas y tests unitarios |
| **DEP-17** | Respuestas de TI (SSH, `.htaccess`, respaldos, antivirus) | Despliegue y operación | TI confirma capacidades | Desarrollo local y pruebas |

**Ruta crítica:** DEP-01 → DEP-02 → DEP-05 → DEP-03 → DEP-04 → DEP-16 → F2 → F3 → F5 → F6.

**Dependencias circulares:** no se detectaron. La única tensión es DEP-01 (stack) condicionando TEC-04/05/06/07/10; se resuelve con el plan condicional por ramas, no moviendo límites de componentes.

---

## 16. Riesgos y decisiones pendientes

### 16.1 Riesgos

| ID | Riesgo | Prob. | Impacto | Mitigación |
|---|---|:--:|:--:|---|
| **RIES-01** | Paridad de validación incompleta | Alta | Alto | F0 con *golden files*; no avanzar a F3 sin paridad firmada |
| **RIES-02** | D-STACK abierta y componentes EOL (PHP 7.4, Laravel 8, MySQL 8.0) | Alta | Alto | Cierre F0 con TI; plan condicional; WAF/proxy mientras tanto; migración a 8.4 |
| **RIES-03** | Semántica de `Diccionario`/`Excep`/`colexcep` no confirmada | Alta | Alto | F0 produce *golden output*; no implementar por intuición |
| **RIES-04** | Catálogos no importables en Linux | Media | Alto | Exportación CSV desde Windows + importador con validación de conteos |
| **RIES-05** | Archivo cerca del límite de 2 MB (SAX y series grandes) | Media | Medio | Medir con archivos reales; `.htaccess` con `php_value`; rechazo claro |
| **RIES-06** | Sin colas ni cron confirmados | Alta | Medio | Validación síncrona; lock N=2; recordatorios manuales |
| **RIES-07** | Memoria de `mod_php` con validaciones simultáneas | Media | Alto | Límite N=2; lectura `setReadDataOnly`; monitoreo |
| **RIES-08** | Despliegue FTP no atómico | Media | Medio | Carpeta versionada + cambio de `index.php`; ventana de mantenimiento |
| **RIES-09** | Coordenadas de cabecera cambiantes | Media | Alto | Detección por etiqueta + `plantilla_cabecera` como respaldo |
| **RIES-10** | Decisiones de negocio pendientes (D1–D7) | Alta | Alto | Cierre en F0; no construir lo que dependa de ellas sin confirmar |
| **RIES-11** | Contradicción de stack entre `AGENT.md` y `SPECS.md` | Media | Alto | Documentada en CONT-01; se pide confirmación de la línea base |
| **RIES-12** | Contradicción de versión de AdminLTE | Baja | Medio | Documentada en CONT-02; se alinea con el stack elegido |
| **RIES-13** | Privacidad y Ley 21.719 | Media | Alto | Aviso en login, minimización, revisión legal, retención D9 |
| **RIES-14** | 2FA no decidido | Media | Medio | Fortify deja la puerta abierta; política de contraseñas mientras tanto |
| **RIES-15** | Doble carga (portal y correo) | Media | Medio | Comunicación de corte; el portal es la fuente oficial |
| **RIES-16** | Usuarios comparten credenciales | Media | Medio | 2FA, auditoría, política y capacitación |
| **RIES-17** | El revisor "aprueba por aprobar" | Media | Alto | *Check* de advertencias obligatorio; muestreo; auditoría |
| **RIES-18** | Respaldo/recuperación no confirmados | Alta | Alto | D13 con TI; `mysqldump` + copia de `storage/`; restauración probada en F6 |

### 16.2 Decisiones pendientes

| ID | Decisión | Estado | Bloquea |
|---|---|---|---|
| **PEND-001 (D-STACK)** | Stack final (A1/A2/B/C/D/E) | **Pendiente**; SPECS asume B | Todo código de aplicación |
| **PEND-002 (D1)** | Fuente de identidad | Pendiente | Alta de usuarios (RF-013) |
| **PEND-003 (D4)** | Plazo mensual | Pendiente | Cierre de período (RF-010) |
| **PEND-004 (D5)** | Series obligatorias | Pendiente | Completitud (RF-011, RF-033) |
| **PEND-005 (D6)** | Tratamiento SAX | Pendiente | Plantillas y validación |
| **PEND-006 (D7)** | Puente CARGAS REM | **MVP = A asumido**; B fuera | RF-039 |
| **PEND-007 (D10)** | 2FA | Pendiente | Auth (RF-001) |
| **PEND-008 (TI-1)** | Actualizar Ubuntu/PHP | Pregunta a TI | D-STACK → A1 |
| **PEND-009 (TI-2)** | PHP-FPM 8.3 para el vhost | Pregunta a TI | D-STACK → A2 |
| **PEND-010 (TI-3)** | Extensiones en PHP 7.4 | Pregunta a TI | Laravel moderno en 7.4 |
| **PEND-011 (TI-4)** | Acceso SSH | Pregunta a TI | Despliegue estándar |
| **PEND-012 (D2)** | Unidad de aprobación | SPECS asume "por envío" | Flujo de revisión |
| **PEND-013 (D3)** | Corrección tras aprobado | SPECS asume reapertura admin | Integridad de datos aprobados |
| **PEND-014 (D8)** | Notificaciones | SPECS asume portal + correo | Aviso a encargados |
| **PEND-015 (D9)** | Retención de archivos | Pendiente (propuesta 5 años) | Legal y almacenamiento |
| **PEND-016 (D11)** | PHP/Laravel a largo plazo | Reabierta con D-STACK | Deuda de seguridad |
| **PEND-017 (D12)** | Antivirus | Pendiente | Archivos maliciosos |
| **PEND-018 (D13)** | Respaldos | Pendiente | Continuidad |
| **PEND-019 (D14)** | Migración histórica | Fuera del MVP | Reportes históricos |

### 16.3 Contradicciones y aclaraciones solicitadas

| ID | Contradicción | Detalle | Acción requerida |
|---|---|---|---|
| **CONT-01** | **Stack** | `AGENT.md` §8 presenta Laravel 13 / PHP 8.3 / AdminLTE 4 como "stack elegido (pendiente D-STACK)". `SPECS.md` asume la Opción B (Laravel 8.83 / PHP 7.4.3 / AdminLTE 3.2). La Constitución v3.0.0 reabrió D-STACK con PHP 7.4.3 confirmado. | Confirmar si la línea base de trabajo es B (asumida en SPECS) o si se espera A1/A2. No se resuelve en silencio. |
| **CONT-02** | **Versión de AdminLTE** | `SPECS.md` encabezado dice 3.2; `SPECS.md` §7.2 y `AGENT.md` dicen 4.x. | Alinear a la versión del stack elegido. |
| **CONT-03** | **Estados del envío** | `SPECS.md` RF-024 omite `con_errores`; M7 §5.1 y RF-029 lo incluyen. | Confirmar el conjunto completo de estados. |
| **CONT-04** | **Identificadores RF** | `SPECS.md` §3.2 etiqueta la matriz de permisos como "(RF-032)", pero RF-032 es "Ver observaciones". Además RF-034 no existe y el resumen dice 39 RF cuando hay 38. | Corregir la etiqueta y el conteo; confirmar si falta un RF-034. |
| **CONT-05** | **Snapshot al revalidar** | RN-ENV-11 se lee como "snapshot activo al subir"; P6 como "snapshot guardado al revalidar". | Confirmar la lectura reproducible (propuesta del plan). |
| **CONT-06** | **2FA** | M7 §13.1 propone 2FA obligatorio para revisor/admin; `SPECS.md` §4.2 lo excluye del MVP por D10 pendiente. | Confirmar si el MVP lo incluye o se difiere. |
| **CONT-07** | **Despliegue** | `SPECS.md` menciona FTP en subdirectorio `/sist_envios/`; `AGENT.md` menciona `/sist_envios/`; M7 §11.5 menciona `/html` y `index.php` ajustado. | Confirmar la ruta de despliegue definitiva. |
| **CONT-08** | **Retención** | RF-037 menciona retención propuesta 5 años (D9); M7 §13.4 igual. | Confirmar con jurídica. |

---

## 17. Auditoría técnica y trazabilidad

### 17.1 Trazabilidad de requisitos → solución técnica

| REQ | Requisito | Módulo | CA | Verificación |
|---|---|---|---|---|
| RF-001 | Login | MOD-01 | CA-001 | Test de integración + throttle |
| RF-002 | Logout | MOD-01 | CA-002 | Test de integración |
| RF-003 | Cambio de período | MOD-01 | CA-003 | Test de integración |
| RF-004 | Recuperación de contraseña | MOD-01 | CA-004 | Test de correo simulado |
| RF-005 | Importar snapshot | MOD-02 | CA-005 | Test de importación con ZIP |
| RF-006 | Activar snapshot | MOD-02 | CA-006 | Test de un solo activo |
| RF-007 | CRUD Servicios | MOD-02 | CA-007 | Test CRUD |
| RF-008 | CRUD Establecimientos | MOD-02 | CA-008 | Test CRUD |
| RF-009 | CRUD Series | MOD-02 | CA-009 | Test CRUD |
| RF-010 | CRUD Períodos | MOD-02 | CA-010 | Test CRUD |
| RF-011 | Matriz establecimiento-serie | MOD-02 | CA-011 | Test CRUD + vigencia |
| RF-012 | Gestión de plantillas | MOD-02 | CA-012 | Test CRUD + unicidad |
| RF-013 | CRUD Usuarios | MOD-01 | CA-013 | Test CRUD + políticas de password |
| RF-014 | Asignar establecimientos | MOD-01 | CA-014 | Test de asignación |
| RF-015 | Reset por admin | MOD-01 | CA-015 | Test de reset + email |
| RF-016 | Perfil de usuario | MOD-01 | CA-016 | Test de perfil |
| RF-017 | Crear envío | MOD-03 | CA-017 | Test de unicidad |
| RF-018 | Subir archivo | MOD-03 | CA-018 | Test de subida + validación |
| RF-019 | Validación V0–V10 | MOD-04 | CA-019 | Test por regla + fixtures |
| RF-020 | Reenvío (versionado) | MOD-03 | CA-020 | Test de versionado |
| RF-021 | Revalidar | MOD-03/04 | CA-021 | Test de idempotencia |
| RF-022 | Descargar original | MOD-03 | CA-022 | Test de descarga + policy |
| RF-023 | Descargar reporte | MOD-04 | CA-023 | Test de reporte |
| RF-024 | Máquina de estados | MOD-05 | CA-024 | Test por transición |
| RF-025 | Enviar a revisión | MOD-05 | CA-026 | Test de guarda + notificación |
| RF-026 | Observar | MOD-06 | CA-027 | Test de observación |
| RF-027 | Aprobar | MOD-05 | CA-028 | Test de guardas |
| RF-028 | Reabrir | MOD-05 | CA-029 | Test de reapertura |
| RF-029 | Anular | MOD-05 | CA-030 | Test de anulación |
| RF-030 | Confirmar carga | MOD-05/10 | CA-031 | Test de carga |
| RF-031 | Registrar observación | MOD-06 | CA-032 | Test de registro |
| RF-032 | Ver observaciones | MOD-06 | CA-033 | Test de listado |
| RF-033 | Matriz de completitud | MOD-07 | CA-034 | Test de matriz |
| **RF-034** | **No existe en `SPECS.md`** | — | — | **Hueco de trazabilidad (CONT-04)** |
| RF-035 | Totales y exportación | MOD-07 | CA-035 | Test de exportación |
| RF-036 | Drill-down | MOD-07 | CA-036 | Test de navegación |
| RF-037 | Auditoría append-only | MOD-08 | CA-037 | Test de inmutabilidad |
| RF-038 | Notificaciones | MOD-09 | CA-038 | Test de notificación + fallo de correo |
| RF-039 | Paquete aprobado | MOD-10 | CA-039 | Test de ZIP + manifiesto |

### 17.2 Auditoría de cobertura de objetivos

| OBJ | Objetivo | Solución técnica | Verificación |
|---|---|---|---|
| OBJ-01 | Paridad exacta V0–V11 | MOD-04 + *golden files* | Paridad 100 %, tolerancia 0 |
| OBJ-02 | Máquina de estados auditada | MOD-05 | Tests de transiciones válidas/prohibidas |
| OBJ-03 | Aislamiento en la consulta | MOD-01 + *query scopes* | Tests positivos/negativos por permiso |
| OBJ-04 | Versionado de archivos | MOD-03 | Test de versionado y `es_vigente` |
| OBJ-05 | Flujo completo | MOD-03/05/06 | E2E con 3 roles |
| OBJ-06 | Auditoría append-only | MOD-08 | Test de inmutabilidad |
| OBJ-07 | Tablero de completitud | MOD-07 | Consulta SQL + vista funcional |
| OBJ-08 | Despliegue reproducible | MOD-11 + TEC-14 | Artefacto completo + migraciones SQL |

### 17.3 Checklist de la auditoría

- [x] Cada requisito relevante tiene una solución técnica prevista (§17.1; RF-034 ausente documentado).
- [x] Las decisiones respetan `specs/AGENT.md` y la Constitución v3.0.0, salvo las contradicciones explícitas (CONT-01 a CONT-08).
- [x] La arquitectura satisface `specs/SPECS.md` (39 RF consideradas; 38 existentes + 1 hueco).
- [x] No hay tecnologías sin justificación (§2; cada `TEC-` tiene propósito y restricción).
- [x] Los riesgos principales están documentados (RIES-01 a RIES-18).
- [x] Las fases tienen entregables verificables (§14; criterios F0–F6).
- [x] Las dependencias determinan un orden válido (§15; ruta crítica y trabajo paralelo).
- [x] La estrategia de pruebas permite verificar los criterios de aceptación (§13).
- [x] Las decisiones pendientes no se presentan como confirmadas (§16.2).
- [x] No se introduce código de aplicación ni tareas atómicas.

### 17.4 Verificación del documento

| Comprobación | Resultado |
|---|---|
| El plan no crea archivos de aplicación; solo `specs/PLAN.md` | Verificado |
| No contiene código de implementación ni lista atómica de tareas | Verificado |
| Los identificadores de `SPECS.md` se conservan | Verificado (RF-, OBJ-, RNF-, CA-, RN-ENV-, ENV-, PEND-) |
| Las contradicciones se documentan, no se resuelven en silencio | Verificado (CONT-01 a CONT-08) |

---

## Resumen de cambios

**Documento creado** `specs/PLAN.md` con:

1. Análisis y trazabilidad de los RF, OBJ, RNF y CA de `specs/SPECS.md`.
2. Decisiones y tecnologías condicionadas a D-STACK (TEC-01 a TEC-14).
3. Arquitectura por capas y 11 módulos con contratos y verificación.
4. Modelo de datos conceptual y decisiones de almacenamiento.
5. Operaciones, flujos técnicos, interfaz, persistencia, validaciones, seguridad y pruebas.
6. Secuencia F0–F6 con dependencias, riesgos y decisiones pendientes.
7. Auditoría de trazabilidad y contradicciones detectadas (CONT-01 a CONT-08).

---

**Archivo guardado en:** `C:\xampp\htdocs\www\Cargas\specs\PLAN.md`
