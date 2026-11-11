# TASK.md — Descomposición atómica de tareas del MVP (Portal M7)

> **Ubicación canónica:** `specs/TASK.md`
> **Fuentes:** `specs/AGENT.md`, `specs/SPECS.md`, `specs/PLAN.md`, `02-DOCS/wiki/sdd/constitution.md` v3.1.0
> **Stack confirmado (C-015):** Laravel **12.72** + PHP **8.2** + MySQL 8.0.42 + AdminLTE 3.2 (Bootstrap 4) + vanilla JS
> **Fecha:** 2026-11-10
> **Estado (2026-11-11):** F0 redactado — 7 de 10; las 3 restantes esperan personas. **F1 en Laravel 12:** `portal/` corre 12.72.0, migra contra `cargas_rem`, responde por Apache y pasa 4 tests. F2–F6 sin empezar.

---

## Convenciones

- `[ ]` = Pendiente | `[x]` = Completada y verificada | `[~]` = En progreso | `[!]` = Bloqueada
- Identificadores: `T-###` (tarea), `T-###.##` (subtarea), `T-###.##.##` (unidad atómica)
- Cada tarea atómica: verbo + objeto, resultado observable, condición de fin, verificación, dependencias
- Referencias a `RF-###`, `CA-###`, `MOD-##`, `DAT-##`, `TEC-##`, `DEP-##`, `OBJ-##`, `CONT-##` de los documentos fuente

---

## Resumen de fases

| Fase | Objetivo | Entregable principal | Criterio de cierre |
|---|---|---|---|
| **F0** | Paridad y decisiones | *Golden files*, decisión stack (C-010), severidad firmada, matriz series | 100% paridad, negocio firma |
| **F1** | Base (L1) | Laravel instalado, auth, RBAC, catálogos, auditoría, seed mínimo | Admin importa snapshot, crea usuarios, auditoría registra |
| **F2** | Carga y validación (L2) | Subida, versionado, V0–V11, reporte, estados | 7 archivos subidos, reporte descargable, reenvíos versionan |
| **F3** | Flujo de revisión (L3) | Observaciones, aprobación, bandeja, notificaciones | Ciclo encargado→revisor completo, no aprueba con bloqueantes |
| **F4** | Completitud y tablero (L3) | Matriz semáforo, totales, exportación, drill-down | Matriz 7×7 correcta, exporta, p95 < 2s |
| **F5** | Puente (L3) | ZIP + manifiesto (hash/HMAC), confirmación carga | ZIP verificable, confirmación manual |
| **F6** | Endurecimiento y salida | Seguridad, rendimiento, UAT, respaldo, manuales | Pruebas autorización/carga/UAT, backup/restore probado |

---

## FASE F0 — Descubrimiento y paridad (Gate: D-STACK — reabierta por C-014)

**Objetivo:** Cerrar paridad con **Cargas REM**, fijar decisiones de negocio, preparar *fixtures*.
**Dependencia:** Ninguna (no es código de aplicación).
**Cierre:** C-010 registrada, 12–20 *golden files* con paridad 100%, severidad firmada, matriz series confirmada, docs SAX.

| ID | Tarea | Descripción | Resultado esperado | Requisito/CA | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F0-01** | **Cerrar D-STACK (C-010)** | Registrar decisión Opción B (Laravel 8.83/PHP 7.4.3) en `02-DOCS/wiki/sdd/decisions.md` y enmendar Constitución a v3.1.0 (MAJOR) | Decisión registrada, Constitución actualizada | PEND-001, CONT-01, CONT-02 | — | `decisions.md` tiene C-010; Constitución v3.1.0 |
| **T-F0-02** | **Reunir *golden files*** | Obtener 12–20 archivos Excel reales anonimizados (.xls, .xlsx, .xlsm): por serie, mes, con/sin errores, SAX cuando exista | Estructura y `manifest.json` creados ✅; falta poblarla con archivos reales | OBJ-01, RIES-01, RIES-03 | — | 12+ archivos en input; salida esperada en expected | `[~]` |
| **T-F0-03** | **Ejecutar ValidadorRem de referencia** | Correr ValidadorRem en entorno de prueba con `Global.mdb` copia y base vacía; exportar `datos` y `LogError` por cada *fixture* | Formato de salida definido en `expected/README.md` ✅; faltan las salidas reales | OBJ-01, RIES-01 | T-F0-02 | Cada *fixture* tiene par `input → expected` | `[~]` |
| **T-F0-04** | **Mapear cabecera y semántica Diccionario/Excep** | Documentar: coordenadas/etiquetas cabecera por plantilla; significado `inicio`/`fin`/`posicion`/`tipo_dato`; estructura `Excep`/`colexcep` | Documento `docs/validacion/paridad-cabecera-diccionario.md` ✅ | RIES-03, RIES-09, DEP-16 | T-F0-03 | Mapa completo; semántica confirmada por negocio | `[x]`
| **T-F0-05** | **Definir política de severidad** | Revisar cada `ENV-###` con Unidad de Estadísticas; firmar documento: qué es bloqueante/advertencia/info | `docs/validacion/politica-severidad.md` ✅ (borrador para firma) | OBJ-01, RIES-01, DEP-02 | T-F0-04 | Documento redactado; falta firma del referente de negocio | `[~]` |
| **T-F0-06** | **Confirmar matriz de series obligatorias (D5)** | Definir series por establecimiento: histórico + *override* con vigencia; confirmar SAX (D6) | `docs/validacion/matriz-series-obligatorias.md` ✅ (borrador para firma) | D5, D6, DEP-12, DEP-13 | T-F0-05 | Matriz redactada; falta confirmación de negocio | `[~]` |
| **T-F0-07** | **Confirmar plazos y reglas de cierre (D4)** | Definir día límite configurable por período; regla período `abierto/en_revision/cerrado` | `docs/validacion/reglas-cierre-periodo.md` ✅ (borrador para firma) | D4, DEP-11 | — | Documento redactado; falta confirmación de negocio | `[~]` |
| **T-F0-08** | **Confirmar fuente de identidad (D1) y 2FA (D10)** | Decidir: cuentas locales / correo institucional / directorio SS; 2FA para quién | `docs/auth/decision-identidad-2fa.md` ✅ (borrador para firma) | D1, D10, DEP-10, DEP-15 | — | Documento redactado; falta confirmación de negocio | `[~]` |
| **T-F0-09** | **Preparar catálogo de errores `ENV-###`** | Lista completa `ENV-001` a `ENV-032` con mensaje usuario, severidad, etapa, acción sugerida | `docs/validacion/catalogo-errores-env.md` ✅ | RF-019, MOD-04, DEP-16 | T-F0-05 | Catálogo completo coincidente con SPECS §20.1 | `[x]`
| **T-F0-10** | **Definir *fixtures* de permisos (RBAC)** | Matriz 18 permisos × 4 roles con tests pos/neg esperados | `tests/Parity/fixtures/rbac-matrix.json` ✅ | MOD-01, MOD-02, PRINCIPIO 8 | T-F0-08 | Archivo JSON con casos de prueba | `[x]`

---

## FASE F1 — Base L1: Modelo, esqueleto, Auth, Catálogos, Auditoría

**Objetivo:** Laravel instalado y corriendo; auth + RBAC + catálogos + auditoría + seed mínimo.
**Dependencia:** F0 completa (C-010, golden files, decisiones).
**Cierre:** Admin importa snapshot, crea usuarios/establecimientos, auditoría registra todo (CA-001 a CA-016, CA-005 a CA-012, CA-037).

### F1.1 — Inicialización del proyecto Laravel

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F1-01** | **Crear proyecto Laravel 12.72** | `composer create-project "laravel/laravel:^12" portal --prefer-dist` en `Cargas/portal/`. El esqueleto 12.x instala `laravel/framework` 12.72 | Proyecto base instalado | TEC-01 | F0 | `portal/artisan --version` = 12.72.0 → **verificado** | `[x]` |
| **T-F1-02** | **Fijar el PHP de plataforma** | `composer.json` exige `php ^8.2`. Sin `platform` override: el PHP local y el del servidor objetivo coinciden en 8.2 | `vendor/` compatible con prod | TEC-01, TEC-14 | T-F1-01 | `composer check-platform-reqs` en verde | `[x]` |
| **T-F1-03** | **Instalar dependencias base** | `spatie/laravel-permission ^6.25`, `laravel/fortify ^1.42`, `yajra/laravel-datatables-oracle ^12.7`, `phpoffice/phpspreadsheet ^5.11` (la 5.x exige PHP 8.1+). **`composer audit` sin avisos** | Paquetes instalados | TEC-04, TEC-06, TEC-07, TEC-10, TEC-11 | T-F1-02 | `composer show` lista paquetes correctos → **verificado** | `[x]` |
| **T-F1-04** | **Configurar `.env.example` y `.env` local** | `.env.example` con MariaDB `cargas_rem`, `America/Santiago`, `es`, sesión 480 min y `MAIL_MAILER=log` | Variables listas | TEC-02, TEC-03 | T-F1-03 | `php artisan migrate` corre contra `cargas_rem` | `[x]` |
| **T-F1-05** | **Configurar base de datos dual** | `config/database.php`: MySQL/MariaDB para desarrollo y paridad; SQLite solo para la suite rápida. `sql_mode` estricto explícito para igualar producción | Dual DB configurada | C-008, C-011, TEC-03 | T-F1-04 | `@@sql_mode` incluye `STRICT_TRANS_TABLES` → **verificado** | `[x]` |
| **T-F1-06** | **Publicar assets AdminLTE 3.2** | AdminLTE 3.2.0 desde el tag `v3.2.0`: `dist/` minificado + 20 plugins (jQuery, Bootstrap 4, FontAwesome, Chart.js, DataTables + bs4/buttons/responsive, Select2 + tema, SweetAlert2 + tema, OverlayScrollbars, Toastr, Inputmask, jQuery-Validation, Pace, Popper, Moment, Tempusdominus) en `public/assets/adminlte/` | Assets servibles sin Node | TEC-05 | T-F1-03 | CSS y JS responden 200 → **verificado** (18,8 MB, 610 archivos) | `[x]` |
| **T-F1-07** | **Configurar `config/rem.php`** | Crear archivo con claves: `archivos`, `validacion`, `series`, `paquete` (ver PLAN §11.4) | Config centralizada | TEC-01, MOD-02, MOD-03, MOD-04 | T-F1-03 | `config('rem.archivos.max_kb')` = 2048 | `[x]` |
| **T-F1-08** | **Configurar zona horaria y locale** | `config/app.php`: `timezone = env('APP_TIMEZONE', 'America/Santiago')`, `locale = 'es'`, `fallback_locale = 'es'`, `faker_locale = 'es_CL'` | App en español CL | C-005, RNF-016 | T-F1-01 | `now()->timezoneName` = America/Santiago → **verificado** | `[x]` |
| **T-F1-09** | **Configurar logging y health check** | `config/logging.php`: canales `validacion`, `seguridad`, `correo` (daily, 90 días). `GET /health` con `HealthController`: comprueba DB y escritura+lectura en `storage`, devuelve 503 y no filtra el mensaje de la excepción | Logs estructurados + health | RNF-016, MOD-11 | T-F1-03 | `GET /health` → 200 JSON → **verificado**, con 2 tests que cubren también el 503 | `[x]` |
### F1.2 — Autenticación y RBAC (MOD-01)

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F1-10** | **Ejecutar Fortify (backend auth)** | `php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"`; configurar `FortifyServiceProvider` (login, password reset, email verification off) | Auth backend listo | RF-001, RF-002, RF-004, TEC-06 | T-F1-07 | Rutas `/login`, `/logout`, `/password/reset` funcionan | `[x]` |
| **T-F1-11** | **Crear vistas Blade auth (login, reset, email)** | `resources/views/auth/login.blade.php`, `forgot-password.blade.php`, `reset-password.blade.php` con AdminLTE 3.2 + CSRF + mensajes español + códigos `APP-###` | UI auth completa | RF-001, RF-002, RF-004, RNF-014 | T-F1-10, T-F1-06 | Pantallas renderizan; validación cliente + servidor | `[x]` |
| **T-F1-12** | **Configurar sesión segura** | `config/session.php`: `secure=true`, `http_only=true`, `same_site=lax`, `lifetime=480`, `expire_on_close=false` | Sesión endurecida | RNF-009, RNF-012 | T-F1-10 | Cookie `laravel_session` tiene flags correctos | `[x]` |
| **T-F1-13** | **Instalar y configurar `spatie/laravel-permission`** | `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`; migración + `HasRoles` trait en `User` | RBAC listo | RF-013, MOD-01, TEC-07 | T-F1-10 | `php artisan permission:show` funciona | `[x]` |
| **T-F1-14** | **Crear seeder de roles y permisos (18 permisos × 4 roles)** | Seeder `RolePermissionSeeder`: roles `encargado`, `revisor`, `admin`, `consulta`; permisos tabla SPECS §3.2 | Datos base RBAC | RF-013, PRINCIPIO 8 | T-F1-13 | `php artisan db:seed --class=RolePermissionSeeder` OK | `[x]` |
| **T-F1-15** | **Crear Policies base** | `EnvioPolicy`, `ArchivoPolicy`, `CatalogoPolicy`, `UsuarioPolicy` con métodos `view`, `create`, `update`, `delete`, `download`, `approve`, `reopen` | Autorización declarativa | RN-ENV-1, RN-ENV-7, RN-ENV-8, RN-ENV-9, MOD-01 | T-F1-14 | Policies registradas en `AuthServiceProvider` | `[x]` |
| **T-F1-16** | **Implementar *query scopes* de aislamiento** | `Envio::scopeForUser($user)`, `EnvioArchivo::scopeForUser($user)` aplican `whereIn establecimiento_id` desde pivot | Aislamiento en consulta | RN-ENV-1, PRINCIPIO 18 | T-F1-15 | SQL generado incluye `WHERE establecimiento_id IN (...)` | `[x]` |
| **T-F1-17** | **Tests auth + RBAC (unit + feature)** | Tests: login/logout/throttle (CA-001, CA-002, CA-004); CRUD usuarios (CA-013 a CA-016); **pos/neg por cada permiso** (PRINCIPIO 8) | Suite verde | CA-001, CA-002, CA-004, CA-013 a CA-016, PRINCIPIO 8 | T-F1-14, T-F1-15 | `php artisan test --filter=Auth` y `--filter=Permission` pasan | `[x]` |

### F1.3 — Catálogos y estructura base (MOD-02)

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F1-18** | **Migraciones catálogos (DAT-01 a DAT-06, DAT-11 a DAT-16)** | Migraciones para: `servicios`, `establecimientos`, `series`, `periodos`, `establecimiento_serie`, `catalogo_snapshots`, `plantillas`, `plantilla_hojas`, `plantilla_cabecera`, `diccionario`, `excepciones`, `usuario_establecimiento` | Esquema completo | DAT-01 a DAT-16, MOD-02 | T-F1-05 | `php artisan migrate` crea todas las tablas con FK/UK/índices | `[x]` |
| **T-F1-19** | **Modelos Eloquent catálogos** | Clases `Servicio`, `Establecimiento`, `Serie`, `Periodo`, `EstablecimientoSerie`, `CatalogoSnapshot`, `Plantilla`, `PlantillaHoja`, `PlantillaCabecera`, `Diccionario`, `Excepcion` con `$fillable`, relaciones, casts, scopes | Modelos listos | DAT-01 a DAT-16 | T-F1-18 | `Servicio::first()->establecimientos` carga relación | `[x]` |
| **T-F1-20** | **Importador CSV/ZIP (CatalogoService::importarSnapshot)** | Servicio que: valida ZIP (columnas, encoding, conteos referencia, duplicados), carga tablas espejo en transacción, crea `CatalogoSnapshot` `activo=0` | Importación robusta | RF-005, RF-006, MOD-02, DEP-03 | T-F1-19 | ZIP válido → snapshot inactivo con conteos correctos | `[x]` |
| **T-F1-21** | **Activar snapshot (único activo)** | `CatalogoService::activarSnapshot($id)`: transacción desactiva anterior + activa nuevo; evento auditoría | Un solo activo | RF-006, MOD-02 | T-F1-20 | Solo 1 fila `activo=1`; auditoría registra cambio | `[x]` |
| **T-F1-22** | **CRUD Admin catálogos (UI Blade)** | Controladores `Admin\ServicioController`, `EstablecimientoController`, `SerieController`, `PeriodoController`, `PlantillaController`, `CatalogoSnapshotController` + vistas `admin/catalogos/*` con AdminLTE 3.2 | UI admin completa | RF-007 a RF-012, CA-007 a CA-012 | T-F1-19, T-F1-11 | Admin crea/edita/activa; validaciones server-side | `[x]` |
| **T-F1-23** | **Matriz establecimiento-serie (CRUD + vigencia)** | UI para `establecimiento_serie`: obligatoria, `vigente_desde/hasta`, *override* manual | Reglas D5 configurables | RF-011, DEP-12 | T-F1-22 | Matriz editable; vigencia respetada en consultas | `[x]` |
| **T-F1-24** | **Tests catálogos (feature + unit)** | Tests: importar snapshot válido/inválido (CA-005, CA-006); activar único (CA-006); CRUD cada entidad; conteos referencia | Suite verde | CA-005 a CA-012, PRINCIPIO 6 | T-F1-20, T-F1-22 | `php artisan test --filter=Catalogo` pasa | `[x]` |

### F1.4 — Auditoría transversal (MOD-08)

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F1-25** | **Migración tabla `auditoria` (DAT-16)** | `auditoria`: `usuario_id`, `evento`, `entidad`, `entidad_id`, `datos` (JSON), `ip`, `user_agent`, `created_at`; índices | Esquema listo | RF-037, DAT-16 | T-F1-18 | Migración crea tabla con índices | `[x]` |
| **T-F1-26** | **AuditoriaService::registrar()** | Método estático: inserta fila en transacción actual; sin UPDATE/DELETE posibles desde UI | Append-only garantizado | RF-037, MOD-08, PRINCIPIO 21 | T-F1-25 | `Auditoria::count()` solo sube; no hay `update`/`delete` en policies | `[x]` |
| **T-F1-27** | **Observadores/Listeners para eventos automáticos** | `Login/Logout/Failed` → auditoría; `ModelObservers` en `Envio`, `EnvioArchivo`, `Observacion`, `EnvioTransicion`, `CatalogoSnapshot` | Auditoría automática | RF-037, MOD-08 | T-F1-26, T-F1-15 | Cada acción critica deja fila en `auditoria` | `[x]` |
| **T-F1-28** | **UI Admin auditoría (filtros + lista)** | `Admin\AuditoriaController` + vista `admin/auditoria/index.blade.php`: filtros usuario/evento/entidad/fecha, paginación servidor | Consulta funcional | RF-037, CA-037 | T-F1-27, T-F1-11 | Filtros funcionan; no hay botón editar/borrar | `[x]` |
| **T-F1-29** | **Tests auditoría (inmutabilidad + cobertura)** | Test: inserta fila, intenta update/delete → falla; verifica eventos: login, upload, download, validar, observar, aprobar, reaperturar, cargar, importar, permiso | CA-037, PRINCIPIO 21 | T-F1-27 | `php artisan test --filter=Auditoria` pasa | `[x]` |

### F1.5 — Seed mínimo L1 y verificación fase

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F1-30** | **Seeder mínimo L1 (datos de prueba)** | `L1MinimumSeeder`: 1 Servicio (SS Osorno), 7 establecimientos (1/comuna), 7 series (A,BS,BM,D,P,F,SAX), 3–6 períodos, matriz establec-serie todas obligatorias, 4 users (1 admin, 1 revisor, 2 encargados), pivot user-establec (2 encarg × 3-4 establec) | Datos base consistentes | PLAN §8.2, DEP-03 | T-F1-14, T-F1-23 | `php artisan db:seed --class=L1MinimumSeeder` OK | `[x]` |
| **T-F1-31** | **Verificación de fase F1 (DoD)** | Checklist: [x] Login/logout/throttle [x] RBAC 4 roles/18 permisos tests pos/neg [x] Admin importa snapshot → conteos OK [x] CRUD catálogos funciona [x] Auditoría append-only registra todo [x] Seed mínimo cargado [x] Health check OK | Fase F1 cerrada | CA-001 a CA-016, CA-005 a CA-012, CA-037, PRINCIPIO 1,2,3,6,7,8,18,21 | T-F1-17, T-F1-24, T-F1-29, T-F1-30 | Todos los checks marcan ✅; `php artisan test` suite F1 verde → **69 tests, 252 aserciones** | `[x]` |

---

## Verificación de la fase F1 (2026-11-11)

| Check | Evidencia |
|---|---|
| Laravel y PHP | **12.72.0** sobre **PHP 8.2.12** |
| `composer audit` | sin avisos |
| Base de datos | `cargas_rem` en MariaDB, `@@sql_mode` con `STRICT_TRANS_TABLES` |
| Zona horaria y locale | `America/Santiago` · `es` |
| Migraciones | corren en MariaDB **y** en SQLite |
| HTTP | `/`, `/login` y `/health` responden **200** |
| Login / logout / throttle | 3 tests, incluido el bloqueo al sexto intento con mensaje en español |
| RBAC | 4 roles, **18 permisos**, **72 comprobaciones** (18 × 4) contra el fixture |
| Importación real | 200.367 filas en **15,6 s**; 1.129 hojas, 939 plantillas, 86 establecimientos |
| CRUD de catálogos y usuarios | 26 tests; un encargado recibe 403 en todas las rutas de administración |
| Auditoría | append-only con inmutable comprobado; login, fallo, importación y activación registrados |
| Seed mínimo | 7 establecimientos, 6 series activas + SAX inactiva, 6 períodos, 4 usuarios, 42 filas de matriz |
| Suite completa | **69 tests, 252 aserciones, 0 fallos** |

**Pendiente y trasladado a F2:** T-F1-15 y T-F1-16 (policies y *query scopes* de `Envio`, `EnvioArchivo` y `Observacion`) no se pueden escribir hasta que existan esos modelos en F2.1. El resto de F1 está cerrado y verificado.

---

## FASE F2 — Carga y validación L2: Subida, Versionado, V0–V11, Reporte, Estados

**Objetivo:** Encargado sube 7 archivos, ve resultado por serie, descarga reporte, reenvíos versionan.
**Dependencia:** F1 completa + *golden files* F0.
**Cierre:** CA-017 a CA-023, OBJ-01, OBJ-04.

### F2.1 — Núcleo de envíos y archivos (MOD-03, DAT-06, DAT-07, DAT-10)

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F2-01** | **Migraciones núcleo transaccional** | `envios` (UK `establecimiento_id,anio,mes`), `envio_archivos` (UK `envio_id,serie_id,version`, UK `sha256`, `es_vigente`), `envio_transiciones`, `validaciones`, `observaciones` | Esquema completo | DAT-06, DAT-07, DAT-08, DAT-10 | T-F1-18 | Migraciones crean tablas con FK/UK correctos | `[x]` |
| **T-F2-02** | **Modelos Eloquent núcleo** | `Envio`, `EnvioArchivo`, `Validacion`, `Observacion`, `EnvioTransicion` con relaciones, casts `decimal:2`, `enum`→`string`+validación | Modelos listos | DAT-06 a DAT-10 | T-F2-01 | `Envio::with('archivos.vigente')->first()` carga árbol | `[x]` |
| **T-F2-03** | **EnvioService::crear() (OP-01)** | Crea/busca envío por `(establecimiento,anio,mes)`; guarda `recibido`; valida período `abierto` y autorización | Unicidad RN-ENV-6 | RF-017, OP-01, RN-ENV-6, RN-ENV-10 | T-F1-16, T-F2-02 | 2º intento mismo período/establec → falla por UK | `[x]` |
| **T-F2-04** | **FormRequest: CrearEnvioRequest + SubirArchivoRequest** | Validación: auth, período abierto, archivo required, mimes `xls,xlsx,xlsm`, max 2048 KB, tipo real `finfo` | Entrada validada | RF-017, RF-018, ENV-001 a ENV-004 | T-F2-03 | Request rechaza >2MB, ext inválida, tipo falso |
| **T-F2-05** | **Almacenamiento privado + naming** | `storage/app/private/envios/{anio}/{mes}/{codigo}/{envio}/v{version}/{serie}.{ext}`; nombre generado SHA-256; `nombre_original` guardado | Archivos fuera de `public/` | TEC-08, RN-ENV-9, RN-ENV-12, RN-ENV-13 | T-F2-02 | Archivo físico en ruta esperada; `public/` sin archivos | `[x]` |
| **T-F2-06** | **EnvioService::subirVersion() (OP-02)** | Transacción: `lockForUpdate(envio)` → `version=MAX+1` → `UPDATE es_vigente=0` → guarda archivo → `EnvioArchivo` `subido` → dispara validación → actualiza archivo + hallazgos → `EstadoEnvioService::recalcular` → auditoría | Subida atómica + versionado | RF-018, RF-020, OP-02, RN-ENV-8, RN-ENV-11, RN-ENV-13 | T-F2-03, T-F2-05, T-F2-14 | Reenvío crea v2, v1 `es_vigente=0`, hash único, auditoría | `[x]` |
| **T-F2-07** | **EnvioService::revalidar() (OP-03)** | Borra `validaciones` del archivo → re-ejecuta `ValidadorArchivo::validar` con `snapshot_id` del archivo → actualiza conteos/estado | Idempotente | RF-021, OP-03 | T-F2-06, T-F2-14 | 2 llamadas consecutivas = mismo resultado | `[x]` |
| **T-F2-08** | **ArchivoService::descargar() (OP-04)** | Verifica policy → `response()->file($ruta, ['Content-Disposition'=>'attachment'])` + headers `nosniff` | Descarga autenticada | RF-022, OP-04, RN-ENV-9, TEC-08 | T-F2-05, T-F1-15 | Usuario sin permiso → 403; con permiso → descarga binaria |
| **T-F2-09** | **ReporteService::descargar() (OP-05)** | Genera `.xlsx` (PhpSpreadsheet) o `.csv` desde `validaciones`: hojas Resumen/Bloqueantes/Advertencias/Info; nombre `Validacion_{codigo}_{serie}_{anio}{mes}_v{version}.xlsx` | Reporte descargable | RF-023, OP-05, MOD-04 | T-F2-14, T-F2-15 | Reporte coincide con hallazgos BD; formato correcto |
| **T-F2-10** | **Controller: EnvioController + ArchivoController** | Rutas: `envios.index`, `envios.show`, `envios.store`, `envios.archivos.store`, `envios.archivos.destroy` (solo si no enviado), `archivos.descargar`, `archivos.reporte` | Endpoints funcionales | RF-017 a RF-023 | T-F2-03 a T-F2-09 | POST `/envios/{id}/archivos` → 201 resumen |
| **T-F2-11** | **Vistas Blade: dashboard encargado, detalle envío, modal subir, resultado validación** | `envios/index.blade.php`, `envios/show.blade.php`, `components/DropzoneArchivo`, `envios/validacion/show.blade.php` con AdminLTE 3.2 + componentes reutilizables §9.2 | UI completa | SPECS §7.1, PLAN §9.1, §9.2 | T-F1-11, T-F2-10 | Pantallas renderizan; dropzone valida cliente |

### F2.2 — Motor de validación V0–V11 (MOD-04, DEP-02, DEP-16)

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F2-12** | **Estructura ValidadorArchivo + ReglaValidacion interface** | `ValidadorArchivo` (cadena reglas), `ReglaValidacion` (interface `etapa()`, `aplicar()`), `ContextoValidacion`, `ResultadoValidacion` (hallazgos, conteos, `tieneBloqueantes()`) | Framework validación | MOD-04, PLAN §11.3 | T-F2-02 | `new ValidadorArchivo([...])->validar($ctx)` retorna resultado | `[x]` |
| **T-F2-13** | **Implementar reglas V0: Archivo (ENV-001 a ENV-004, ENV-027, ENV-028)** | `V0Archivo`: existencia, tamaño ≤2MB, extensión, tipo real (`finfo` + firma ZIP/OLE2), no corrupto/cifrado, macros→advertencia ENV-028 | V0 completa | RF-019, ENV-001 a ENV-004, ENV-027, ENV-028 | T-F2-12, T-F0-09 | Unit test por cada ENV; bloqueantes detienen cadena | `[x]` |
| **T-F2-14** | **Implementar regla V1: Nombre (ENV-005 a ENV-008)** | `V1Nombre`: regex `^(\d{4,10})([A-Z]{1,3})(\d{2})(?:[ _.-].*)?\.(xls|xlsx|xlsm)$`; valida código existe, serie en catálogo, mes coincide período, código = establecimiento envío | V1 completa | RF-019, ENV-005 a ENV-008, RN-ENV-12 | T-F2-12, T-F1-19 | Test: nombre inválido → ENV-005; serie inexistente → ENV-006 | `[x]` |
| **T-F2-15** | **Implementar regla V2: Cabecera (ENV-009 a ENV-013)** | `V2Cabecera`: detección por etiquetas `SERIE`/`AÑO`/`MES`/`VERSIÓN`/`ESTABLECIMIENTO` (primeras 25 filas × 15 cols); fallback `plantilla_cabecera`; valida coincidencias | V2 completa | RF-019, ENV-009 a ENV-013, DEP-04 | T-F2-12, T-F1-19, T-F0-04 | Test: cabecera no detectable → ENV-009 con etiquetas buscadas | `[x]` |
| **T-F2-16** | **Implementar regla V3: Versión plantilla (ENV-014)** | `V3VersionPlantilla`: compara versión detectada vs `plantillas(anio,mes,serie)` activo | V3 completa | RF-019, ENV-014, DEP-04 | T-F2-15, T-F1-19 | Versión distinta → ENV-014 bloqueante |
| **T-F2-17** | **Implementar regla V4: Pertenencia (ENV-015)** | `V4Pertenencia`: establecimiento en SS del user + pivot `usuario_establecimiento` | V4 completa | RF-019, ENV-015, RN-ENV-1, RN-ENV-4 | T-F2-12, T-F1-16 | User sin establecimiento → ENV-015 bloqueante |
| **T-F2-18** | **Implementar regla V5: Hojas (ENV-016, ENV-017)** | `V5Hojas`: `plantilla_hojas` obligatorias vs reales; hoja extra = advertencia ENV-017 | V5 completa | RF-019, ENV-016, ENV-017, DEP-04 | T-F2-12, T-F1-19 | Hoja obligatoria faltante → ENV-016 bloqueante |
| **T-F2-19** | **Implementar reglas V6–V8: Datos (ENV-018 a ENV-022)** | `V6MapecoCeldas`: lectura según `diccionario` (línea, inicio, fin, posicion, tipo_dato); `V7TipoDato`: numérico/decimales; `V8Excepciones`: columnas no aplicables | V6–V8 base | RF-019, ENV-018 a ENV-022, DEP-04, RIES-03 | T-F2-12, T-F1-19, T-F0-04 | **Paridad con golden files F0** (PRINCIPIO 10) |
| **T-F2-20** | **Implementar regla V9: Duplicados internos (ENV-023)** | `V9Duplicados`: mismo `codigo_prestacion` 2+ veces en mismo archivo → advertencia | V9 completa | RF-019, ENV-023 | T-F2-12 | Duplicado → ENV-023 advertencia |
| **T-F2-21** | **Implementar regla V10: Al menos 1 registro válido (ENV-024)** | `V10Registros`: `filas_validas > 0` tras V6–V9 | V10 completa | RF-019, ENV-024 | T-F2-19 | 0 filas válidas → ENV-024 bloqueante |
| **T-F2-22** | **Implementar regla V11: Completitud por envío (ENV-025)** | `V11Completitud` (nivel envío): `EstablecimientoSerie` obligatorias vs archivos vigentes `validado`; faltantes → ENV-025 bloqueante para "Enviar a revisión" | V11 completa | RF-025, RF-033, ENV-025, OP-15, DEP-12 | T-F2-12, T-F1-23, T-F2-26 | Envío sin serie obligatoria → V11 bloquea "Enviar" |
| **T-F2-23** | **Severidad y parada de cadena** | `ValidadorArchivo::validar()`: itera reglas ordenadas; si `resultado->tieneBloqueantes()` y regla != V11 → `break` | Política severidad | RF-019, PLAN §7.5, DEP-02 | T-F2-12 | Bloqueante en V0–V10 detiene; V11 siempre corre |
| **T-F2-24** | **Tests unitarios por regla (PRINCIPIO 6)** | 1 test mínimo por `ENV-###` con `ContextoValidacion` mockeado; fixtures para V6–V8 | Cobertura reglas | PRINCIPIO 6, CA-019 | T-F2-13 a T-F2-22 | `php artisan test --filter=ValidacionRegla` pasa |
| **T-F2-25** | **Tests de paridad (golden files) — PRINCIPIO 10** | Script `tests/Parity/run_parity.php`: itera fixtures F0, sube archivo, compara `validaciones` (códigos, severidad, conteos) vs expected; tolerancia 0 numéricos | Paridad 100% | OBJ-01, PRINCIPIO 10, DEP-02, DEP-16 | T-F2-13 a T-F2-22, T-F0-03 | Reporte: `OK 20/20` o lista diffs |

### F2.3 — Estados y transiciones (MOD-05, DEP-06)

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F2-26** | **EstadoEnvioService::recalcular() (OP-06)** | Deriva estado desde archivos vigentes: si ∃ bloqueante → `con_errores`; si ∀ `validado` y V11 OK → `validado`; si ∃ `observado` → `observado`; etc. Persiste en `envios.estado` | Estado consistente | RF-024, OP-06, MOD-05 | T-F2-06, T-F2-22 | Cambio archivo → recalcula → estado correcto |
| **T-F2-27** | **EstadoEnvioService::transicionar() (OP-07)** | Guarda: valida evento permitido (matriz §5.3), guarda `envio_transiciones`, actualiza `estado`, dispara auditoría + notificación; lanza excepción si prohibido | Transiciones seguras | RF-024 a RF-030, OP-07, PRINCIPIO 7, RN-ENV-2,7,8,10 | T-F2-26, T-F1-26, T-F1-27 | Test: cada transición válida OK; prohibida → Exception |
| **T-F2-28** | **Tests máquina de estados (PRINCIPIO 7)** | Tests: todas las transiciones válidas (8) + prohibidas (6+) verifican excepción; cubre guardas: bloqueantes, advertencias, autoaprobación, período, inmutabilidad | Suite completa | CA-024 a CA-031, PRINCIPIO 7 | T-F2-27 | `php artisan test --filter=EstadoEnvio` pasa |

### F2.4 — Concurrencia, locks, verificación fase F2

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F2-29** | **Lock validación concurrente (N=2, timeout 10s)** | `Cache::lock('validacion', 2)->block(10, fn() => ...)` en `EnvioService::subirVersion`; si falla → `ENV-030` + mensaje reintente | RNF-006, MOD-04 | T-F2-06 | 3 subidas simultáneas → 1 espera, 1 ENV-030 |
| **T-F2-30** | **Tests de integración flujo completo FT-01** | Test E2E: crea envío → sube 1 archivo → valida → verifica estado/archivo/hallazgos/auditoría → sube 2ª versión → versiona → revalida | CA-017 a CA-023 | T-F2-06 a T-F2-11, T-F2-26 | Flujo completo verde sin errores |
| **T-F2-31** | **Verificación fase F2 (DoD)** | Checklist: [ ] 7 archivos subidos (distintas series) [ ] Reporte `.xlsx`/`.csv` descargable [ ] Reenvíos versionan (v1 `reemplazado`, v2 `vigente`) [ ] Hash único evita duplicado [ ] Estados: `recibido`→`validado`/`con_errores` [ ] V0–V11 ejecutan con códigos correctos [ ] Paridad golden files 100% [ ] Lock concurrencia funciona | Fase F2 cerrada | CA-017 a CA-023, OBJ-01, OBJ-04 | T-F2-25, T-F2-28, T-F2-29, T-F2-30 | Todos ✅; `php artisan test --filter=F2` verde |

---

## FASE F3 — Flujo de revisión L3: Observaciones, Aprobación, Bandeja, Notificaciones

**Objetivo:** Ciclo completo encargado → revisor; no se aprueba con bloqueantes.
**Dependencia:** F2 completa.
**Cierre:** CA-026 a CA-033, CA-038, OBJ-02, OBJ-05.

### F3.1 — Observaciones y bandeja revisor (MOD-06)

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F3-01** | **ObservacionService::registrar() (OP-08)** | Crea `Observacion` (nivel envío o archivo + texto obligatorio); marca `resuelta=0`; dispara notificación + auditoría; transiciona envío a `observado` | Observación registrada | RF-026, RF-031, OP-08, MOD-06 | T-F2-27, T-F1-26 | Test: obs archivo → `envio_archivo_id` set; obs envío → null |
| **T-F3-02** | **Resolución automática al reenviar** | En `EnvioService::subirVersion`: si existe `Observacion` para esa `serie` y `resuelta=0` → marca `resuelta=1`, `resuelta_en=now()` | Cierra loop | RF-031, RN-ENV-15 | T-F3-01, T-F2-06 | Reenvío serie observada → obs marcada resuelta |
| **T-F3-03** | **Controller: RevisionController (bandeja + acciones)** | Rutas: `revision.index` (filtros SS/establec/período/estado/antigüedad, paginación servidor, contadores), `revision.observar`, `revision.aprobar`, `revision.cargar`, `revision.reabrir` | Endpoints revisor | RF-026, RF-027, RF-030, RF-032, MOD-06 | T-F2-10, T-F3-01 | GET `/supervision` → tabla paginada con filtros |
| **T-F3-04** | **Vistas Blade: bandeja, modal observar, modal aprobar** | `revision/index.blade.php` (tabla + filtros), `components/ModalObservar` (checklist series + textarea obligatorio), `components/ModalAprobar` (resumen archivos, bloqueantes=0, checklist advertencias, comentario) | UI revisor | SPECS §7.1, PLAN §9.1, §9.2 | T-F1-11, T-F3-03 | Modales validan: obs requiere texto; aprobar requiere checks |
| **T-F3-05** | **Tests observación + bandeja** | Test: revisor observa con checklist → `observado` + notificación; encargado reenvía → `recibido`; revisor ve en bandeja; paginación/filtros funcionan; RN-ENV-7 (revisor no aprueba su establec) | CA-027, CA-032, CA-033, RN-ENV-7 | T-F3-01, T-F3-03, T-F3-04 | `php artisan test --filter=Revision` pasa |

### F3.2 — Aprobación y confirmación carga (MOD-05, MOD-10)

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F3-06** | **Transición 'aprobar' con guardas completas** | `EstadoEnvioService::transicionar('aprobar')`: valida 0 bloqueantes en archivos vigentes + advertencias con `revisada=1` + RN-ENV-7 (revisor ≠ encargado establec) → `estado=aprobado`, archivos `aprobado`, transición, notificación | Aprobación segura | RF-027, RF-028, CA-028, RN-ENV-2, RN-ENV-7 | T-F2-27, T-F3-01 | Test: bloqueante>0 → falla; advertencia sin check → falla; autoaprobación → falla |
| **T-F3-07** | **Transición 'confirmar_carga' (manual MVP A)** | `transicionar('confirmar_carga')`: solo `aprobado`/`cargado` → `cargado` + `fecha_carga` + transición + auditoría | Cierre ciclo | RF-030, CA-031, MOD-05, MOD-10 | T-F3-06 | Test: estado `aprobado` → `cargado` con fecha |
| **T-F3-08** | **Transiciones reaperturar/anular (solo admin)** | `transicionar('reabrir')`: `aprobado`/`cargado` → `observado`/`recibido` + motivo obligatorio + auditoría; `transicionar('anular')`: `recibido`/`con_errores`/`observado` → `anulado` + motivo | Excepciones admin | RF-028, RF-029, CA-029, CA-030 | T-F2-27 | Test: admin reaperture → motivo en auditoría; no-admin → 403 |

### F3.3 — Notificaciones (MOD-09)

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F3-09** | **Migración tabla `notifications` (Laravel default)** | `php artisan notifications:table` → migración + modelo `Notification` | Esquema listo | RF-038, MOD-09 | T-F1-05 | Tabla creada |
| **T-F3-10** | **Notificador::enviar() (OP-17)** | Crea `Notification` en BD siempre; `Mail::to(...)->send(...)` en `afterCommit` con `try/catch` timeout 5s; si falla mail → log + no revierte | BD + correo tolerante | RF-038, OP-17, MOD-09 | T-F3-09, T-F1-09 | Test: mail falla → notificación en BD, operación OK |
| **T-F3-11** | **Plantillas correo (Blade mail)** | `resources/views/emails/enviado-revision.blade.php`, `observacion.blade.php`, `aprobado.blade.php`, `cargado.blade.php` con enlace directo + estado | Correos bonitos | RF-038, RNF-014 | T-F3-10 | Mail renderiza; variables correctas |
| **T-F3-12** | **Tests notificaciones** | Test: cada evento genera notificación BD; correo enviado (mock); fallo correo no revierte; admin puede reenviar manual | CA-038 | T-F3-10, T-F3-11 | `php artisan test --filter=Notificacion` pasa |

### F3.4 — Verificación fase F3

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F3-13** | **Test E2E ciclo completo FT-02** | Encargado sube → valida → envía a revisión → revisor observa → encargado corrige → reenvía → revisor aprueba → confirma carga | OBJ-05, CA-026 a CA-031 | T-F3-05, T-F3-06, T-F3-07 | Flujo completo verde |
| **T-F3-14** | **Verificación fase F3 (DoD)** | Checklist: [ ] Revisor observa con checklist+texto → `observado` [ ] Encargado corrige y reenvía → `recibido` [ ] No se aprueba con bloqueantes [ ] Advertencias requieren check [ ] RN-ENV-7: revisor no aprueba su establec [ ] Reaperturar/anular solo admin con motivo [ ] Notificaciones BD + correo tolerante [ ] Bandeja con filtros y paginación | Fase F3 cerrada | CA-026 a CA-033, CA-038, OBJ-02, OBJ-05 | T-F3-05, T-F3-06, T-F3-12, T-F3-13 | Todos ✅ |

---

## FASE F4 — Completitud y tablero L3

**Objetivo:** Matriz semáforo, totales, exportación, drill-down.
**Dependencia:** F2, F3 (datos disponibles).
**Cierre:** CA-034, CA-035, CA-036, OBJ-07, p95 < 2s.

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F4-01** | **CompletitudService::matriz() (OP-09)** | Query SQL optimizada (ver PLAN §9.2): establecimientos activos × series obligatorias (`establecimiento_serie` vigentes) LEFT JOIN envíos/archivos/vigentes/validaciones; aplica scope usuario; retorna array para vista | Matriz performante | RF-033, OP-09, MOD-07, DEP-12 | T-F1-23, T-F2-02 | 30×7 ejecuta < 2s; scope usuario filtra |
| **T-F4-02** | **CompletitudService::exportar() (OP-10)** | Genera CSV/Excel desde misma query matriz; respeta filtros actuales; headers correctos | Export funcional | RF-035, OP-10, MOD-07 | T-F4-01 | Export coincide con vista filtrada |
| **T-F4-03** | **Controller: CompletitudController** | Rutas: `completitud.index` (GET período+SS → matriz), `completitud.exportar` (GET formato=csv|xlsx) | Endpoints | RF-033, RF-035, RF-036 | T-F4-01, T-F4-02 | GET `/completitud?anio=2026&mes=9&ss=1` → matriz |
| **T-F4-04** | **Vista Blade: matriz semáforo + drill-down** | `completitud/index.blade.php`: tabla con `components/SemaforoCelda` (🟢🟡🔴⚪), totales arriba, botones exportar, click celda → `/envios/{id}` | UI completa | SPECS §7.1, PLAN §9.1, §9.2 | T-F1-11, T-F4-03 | Render correcto; colores consistentes; drill-down navega |
| **T-F4-05** | **Tests completitud + rendimiento** | Test: matriz 7×7 semáforo correcto (CA-034); export CSV/Excel coincide (CA-035); drill-down navega (CA-036); carga 30×7 p95 < 2s (RNF-005) | CA-034, CA-035, CA-036, RNF-005 | T-F4-03, T-F4-04 | `php artisan test --filter=Completitud` pasa + benchmark |

---

## FASE F5 — Puente MVP A: Paquete aprobado + confirmación manual

**Objetivo:** ZIP verificable (hash/HMAC) + confirmación manual carga.
**Dependencia:** F3 (envío aprobado).
**Cierre:** CA-039, CA-031, OBJ-08.

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F5-01** | **PaqueteService::generar() (OP-11)** | Crea ZIP en memoria: `archivos/{serie}.{ext}` (copia original), `validacion/{serie}.xlsx` (reporte), `manifiesto.json` (v1.0: envio_id, establec, servicio, período, aprobado_por/fecha, snapshot_catalogo, archivos[serie, nombre_original, sha256, filas_validas, plantilla], hash_paquete, hmac opcional) | ZIP completo | RF-039, OP-11, MOD-10, D7=A | T-F3-06, T-F2-09 | ZIP descargable; manifiesto JSON válido |
| **T-F5-02** | **Hash paquete + HMAC (config `rem.paquete.hmac`)** | `hash_paquete` = SHA256(concat ordered file_shas + manifiesto sin hmac); `hmac` = HMAC-SHA256(key=`PAQUETE_HMAC_KEY` en `.env`) | Integridad verificable | RF-039, MOD-10 | T-F5-01 | Verificación independiente: `sha256` coincide; HMAC valida |
| **T-F5-03** | **Controller: PaqueteController (descarga ZIP)** | Ruta `paquetes.descargar` (GET `/envios/{id}/paquete`): solo `aprobado`/`cargado`; policy + `response()->streamDownload()` | Descarga segura | RF-039, CA-039, RN-ENV-9 | T-F5-01, T-F1-15 | Usuario sin permiso → 403; ZIP íntegro |
| **T-F5-04** | **Confirmación manual carga (UI revisor/admin)** | Botón "Confirmar carga" en detalle envío `aprobado`/`cargado` → POST `envios.{id}.confirmar_carga` → `transicionar('confirmar_carga')` → `cargado` + fecha + auditoría | Cierre manual | RF-030, CA-031, MOD-05 | T-F3-07, T-F5-03 | Test: click → estado `cargado` + fecha + auditoría |
| **T-F5-05** | **Tests paquete + verificación** | Test: genera ZIP → extrae → verifica manifiesto estructura + hashes + HMAC; descarga policy; confirmación carga cambia estado | CA-039, CA-031 | T-F5-02, T-F5-04 | `php artisan test --filter=Paquete` pasa |

---

## FASE F6 — Endurecimiento, rendimiento, UAT, respaldo, manuales

**Objetivo:** MVP listo para producción.
**Dependencia:** F1–F5 completas.
**Cierre:** Pruebas autorización/carga/UAT aprobadas; backup/restore probado; manuales.

### F6.1 — Seguridad y hardening (MOD-11)

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F6-01** | **Headers seguridad + CSP básica** | Middleware `SecurityHeaders`: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Content-Security-Policy` restrictiva (self + fonts.googleapis.com), HSTS si HTTPS | RNF-008, PRINCIPIO 20 | T-F1-01 | `curl -I` muestra headers |
| **T-F6-02** | **Rate limiting diferenciado** | `Route::middleware('throttle:60,1')` global; `throttle:5,1` login; `throttle:10,60` subida; `throttle:30,1` descarga | RNF-009, MOD-11 | T-F1-12 | Prueba: excede límite → 429 |
| **T-F6-03** | **Validación archivo robusta (ZIP bomb, path traversal)** | `SubirArchivoRequest`: `phpspreadsheet` `setReadDataOnly(true)`; ratio compresión ZIP < 100; entries < 1000; ruta storage sanitizada | RNF-010, RIES-05 | T-F2-04 | ZIP bomb rechazado; `../../etc/passwd` bloqueado |
| **T-F6-04** | **CSRF + XSS + SQLi pruebas** | Tests: CSRF token requerido en POST; `{!! !!}` prohibido (grep); SQL crudo prohibido (grep `DB::raw` sin bindings) | PRINCIPIO 20, 22 | T-F1-01 | `grep -r '{!!' resources/views/` → 0; tests pasan |
| **T-F6-05** | **APP_DEBUG=false + limpieza diagnóstico** | `.env` prod: `APP_DEBUG=false`, `APP_ENV=production`; script `php artisan optimize:clear` + borra cualquier `info.php`, `phpinfo.php`, `.env.example` en `public/` | PRINCIPIO 20 | T-F1-04 | `APP_DEBUG=false`; `public/` sin archivos diagnóstico |
| **T-F6-06** | **Logs sin datos sensibles** | Canales `validacion`, `seguridad`, `correo` no loguean contenido celdas ni observaciones completas; solo IDs/códigos | RNF-011, PRINCIPIO 22 | T-F1-09, T-F1-26 | Revisa `storage/logs/` → sin datos sensibles |

### F6.2 — Rendimiento y carga

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F6-07** | **Prueba carga: 2 validaciones simultáneas** | Script JMeter/k6: 2 hilos suben archivo 1.6MB concurrente; mide p95 < 90s, RAM < 512MB, sin ENV-030 espurios | RNF-002, RNF-006, PRINCIPIO 25 | T-F2-29, T-F5-01 | Reporte: p95, RAM, errores |
| **T-F6-08** | **Prueba carga: bandeja 100 envíos** | Seed 100 envíos; request GET `/supervision` con paginación 20; mide p95 < 2s | RNF-004, PRINCIPIO 25 | T-F3-03 | Reporte: tiempo respuesta |
| **T-F6-09** | **Prueba carga: completitud 30×7** | Ejecuta `CompletitudService::matriz()` 50 veces; p95 < 2s | RNF-005, PRINCIPIO 25 | T-F4-01 | Benchmark PHPUnit |

### F6.3 — Respaldo y restauración (D13)

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F6-10** | **Script respaldo `backup.sh`** | `mysqldump --single-transaction --routines --triggers cargas_rem > backup/db_$(date +%F).sql` + `tar -czf backup/storage_$(date +%F).tar.gz storage/app/private` | Backup atómico | RNF-016, D13, RIES-18 | T-F1-05 | Archivos generados; tamaño coherente |
| **T-F6-11** | **Prueba restauración en entorno limpio** | Docker MySQL 8 limpio → `mysql < backup/db_xxx.sql` + `tar -xzf storage_xxx.tar.gz` → `php artisan migrate:status` OK + app funcional | Recuperación verificada | RNF-016, RIES-18 | T-F6-10 | App levantada con datos restaurados |

### F6.4 — UAT y documentación

| ID | Tarea | Descripción | Resultado | Requisito | Dependencia | Verificación |
|---|---|---|---|---|---|---|
| **T-F6-12** | **Guion UAT (3 roles × 1 mes)** | Documento `docs/uat/guion-uat.md`: escenarios encargado (sube 7 series, corrige, envía), revisor (bandeja, observa, aprueba), admin (catálogos, usuarios, auditoría, reaperturas); criterios pass/fail | UAT estructurado | RNF-014, OBJ-05, OBJ-07 | T-F1-30, T-F5-04 | Documento completo |
| **T-F6-13** | **Ejecutar UAT con usuarios reales** | 2 encargados, 1 revisor, 1 admin operan durante 1 mes simulado; registran incidencias | Validación real | RNF-014, OBJ-05, OBJ-07 | T-F6-12, T-F1-30 | 0 incidencias bloqueantes; incidencias menores documentadas |
| **T-F6-14** | **Manual de usuario (encargado/revisor/admin)** | `docs/manual/manual-usuario.md`: capturas, pasos, códigos error, FAQ | Documento entregable | RNF-014, OBJ-08 | T-F6-12 | PDF/Markdown legible |
| **T-F6-15** | **Manual de operación (despliegue, backup, logs, health, troubleshooting)** | `docs/manual/manual-operacion.md`: FTP deploy, `backup.sh`, restore, logs, health, errores comunes, contactos | Documento entregable | RNF-016, OBJ-08, RIES-18 | T-F6-10, T-F6-11 | PDF/Markdown completo |
| **T-F6-16** | **Verificación fase F6 / MVP (DoD final)** | Checklist: [ ] Headers seguridad + CSP [ ] Rate limits [ ] Pruebas carga pasan (p95) [ ] Backup/restore probado [ ] UAT aprobado [ ] Manuales entregados [ ] `APP_DEBUG=false` prod [ ] Sin secretos en repo [ ] Auditoría append-only [ ] Paridad golden files 100% | MVP listo | TODOS OBJ, PRINCIPIOS, RNF | T-F6-01 a T-F6-15 | Todos ✅; `php artisan test` suite completa verde |

---

## Grafo de dependencias crítico (resumen)

```
F0 (T-F0-01..10) 
  └─► F1 (T-F1-01..31)
        ├─► F2 (T-F2-01..31)
        │     ├─► F3 (T-F3-01..14)
        │     │     └─► F5 (T-F5-01..05)
        │     └─► F4 (T-F4-01..05)
        └─► F6 (T-F6-01..16)  ← tras F3–F5
```

**Tareas que pueden paralelizarse dentro de cada fase:**
- F1: Auth (T-F1-10..17) ⫫ Catálogos (T-F1-18..24) ⫫ Auditoría (T-F1-25..29) — Auditoría antes de writers
- F2: Reglas V0–V5 (T-F2-13..18) ⫫ Reglas V6–V10 (T-F2-19..21) ⫫ V11 (T-F2-22) — V11 tras catálogos
- F2: Controllers/UI (T-F2-10..11) pueden empezar con validador stub
- F3: Observaciones (T-F3-01..02) ⫫ Bandeja (T-F3-03..04) — Notificaciones (T-F3-09..12) tras transiciones
- F4: Independiente tras F2 (solo necesita datos)
- F6: Seguridad (T-F6-01..06) ⫫ Rendimiento (T-F6-07..09) ⫫ Backup (T-F6-10..11) ⫫ Docs (T-F6-12..15)

---

## Cobertura de requisitos y criterios de aceptación

| Requisito | Tareas de implementación | Tareas de verificación |
|---|---|---|
| RF-001..004 (Auth) | T-F1-10..12 | T-F1-17 (CA-001..004) |
| RF-005..012 (Catálogos) | T-F1-18..23 | T-F1-24 (CA-005..012) |
| RF-013..016 (Usuarios/RBAC) | T-F1-13..16 | T-F1-17 (CA-013..016) |
| RF-017 (Crear envío) | T-F2-03 | T-F2-30 (CA-017) |
| RF-018 (Subir archivo) | T-F2-04..06 | T-F2-30 (CA-018) |
| RF-019 (Validación V0–V10) | T-F2-12..21 | T-F2-24, T-F2-25 (CA-019) |
| RF-020 (Versionado) | T-F2-06 | T-F2-30 (CA-020) |
| RF-021 (Revalidar) | T-F2-07 | T-F2-30 (CA-021) |
| RF-022 (Descargar original) | T-F2-08 | T-F2-30 (CA-022) |
| RF-023 (Descargar reporte) | T-F2-09 | T-F2-30 (CA-023) |
| RF-024 (Estados) | T-F2-26..27 | T-F2-28 (CA-024) |
| RF-025 (Enviar revisión) | T-F2-27, T-F2-22 | T-F3-13 (CA-026) |
| RF-026 (Observar) | T-F3-01 | T-F3-05 (CA-027) |
| RF-027 (Aprobar) | T-F3-06 | T-F3-13 (CA-028) |
| RF-028 (Reabrir) | T-F3-08 | T-F3-13 (CA-029) |
| RF-029 (Anular) | T-F3-08 | T-F3-13 (CA-030) |
| RF-030 (Confirmar carga) | T-F3-07, T-F5-04 | T-F5-05 (CA-031) |
| RF-031 (Registrar obs) | T-F3-01 | T-F3-05 (CA-032) |
| RF-032 (Ver obs) | T-F3-03 | T-F3-05 (CA-033) |
| RF-033 (Matriz completitud) | T-F4-01 | T-F4-05 (CA-034) |
| RF-035 (Totales/export) | T-F4-02 | T-F4-05 (CA-035) |
| RF-036 (Drill-down) | T-F4-04 | T-F4-05 (CA-036) |
| RF-037 (Auditoría) | T-F1-25..27 | T-F1-29 (CA-037) |
| RF-038 (Notificaciones) | T-F3-09..11 | T-F3-12 (CA-038) |
| RF-039 (Paquete) | T-F5-01..03 | T-F5-05 (CA-039) |
| OBJ-01 (Paridad) | T-F2-25 | T-F2-25 |
| OBJ-02 (Estados) | T-F2-26..28 | T-F2-28, T-F3-13 |
| OBJ-03 (Aislamiento) | T-F1-16 | T-F1-17 |
| OBJ-04 (Versionado) | T-F2-06 | T-F2-30 |
| OBJ-05 (Flujo completo) | T-F3-13 | T-F3-13 |
| OBJ-06 (Auditoría) | T-F1-25..29 | T-F1-29 |
| OBJ-07 (Completitud) | T-F4-01..05 | T-F4-05 |
| OBJ-08 (Despliegue) | T-F6-10..15 | T-F6-11, T-F6-16 |

---

## Decisiones pendientes que bloquean tareas (registro)

| ID | Decisión | Tareas bloqueadas | Estado |
|---|---|---|---|
| **D1** | Fuente de identidad | T-F1-10 (login), T-F1-14 (seeder users), T-F1-30 (seed) | ✅ **Resuelta: Cuentas locales** (D-001) — desbloquea F1 |
| **D4** | Plazo mensual | T-F1-22 (PeriodoController), T-F2-03 (validación período) | ✅ **Resuelta: Día 10 configurable** (D-004) — desbloquea F1/F2 |
| **D5** | Series obligatorias | T-F1-23 (matriz), T-F2-22 (V11), T-F4-01 (matriz) | ✅ **Resuelta: Histórico + override manual** (D-005) — desbloquea F1/F2/F4 |
| **D6** | Tratamiento SAX | T-F1-19 (Serie model), T-F1-23 (matriz), T-F2-15 (V2) | ✅ **Resuelta: Fuera del MVP** (D-006) — desbloquea F1/F2 |
| **D2** | Unidad de aprobación | T-F3-06 (aprobar), T-F3-03 (bandeja) | ✅ **Resuelta: Mixto (envío con veto por serie)** (D-002) — desbloquea F3 |
| **D3** | Corrección tras aprobado | T-F3-08 (reabrir), T-F2-06 (subir versión) | ✅ **Resuelta: Versión automática nueva** (D-003) — desbloquea F2/F3 |
| **D7** | Puente CARGAS REM | T-F5-01..05 (paquete, confirmación carga) | ✅ **Resuelta: MVP = A (descarga manual)** (D-007) — desbloquea F5 |
| **D8** | Notificaciones | T-F3-09..12 (notificaciones) | ✅ **Resuelta: Solo portal (BD)** (D-008) — simplifica F3 |
| **D9** | Retención | T-F6-10..11 (backup, restore) | ✅ **Resuelta: 5 años** (D-009) — define F6 |
| **D10** | 2FA | T-F1-10 (Fortify), T-F1-12 (sesión) | ✅ **Resuelta: Revisores y admins** (D-010) — configura F1 |
| **D12** | Antivirus | T-F2-04 (validación archivo), T-F6-03 | **No bloquea** — validación tipo real como fallback |
| **D13** | Respaldos | T-F6-10..11 | **No bloquea** — manual/phpMyAdmin como fallback |
| **D14** | Migración histórica | — | **No bloquea** — proyecto aparte tras MVP |

---

## Problemas de atomicidad detectados y corregidos

| Problema original | Corrección aplicada |
|---|---|
| "Implementar validación V0–V11" como una tarea | Descompuesto en **12 tareas atómicas** (T-F2-12..23), una por regla/etapa + framework + tests |
| "Crear CRUD catálogos" como una tarea | Separado en: migraciones (T-F1-18), modelos (T-F1-19), importador (T-F1-20..21), CRUD UI por entidad (T-F1-22), matriz (T-F1-23), tests (T-F1-24) |
| "Hacer auditoría" como una tarea | Separado: migración (T-F1-25), service (T-F1-26), observers (T-F1-27), UI (T-F1-28), tests (T-F1-29) |
| "Tests de autorización" vago | Especificado: **test positivo + negativo por cada uno de los 18 permisos** (PRINCIPIO 8) en T-F1-17 |
| "Pruebas de paridad" sin criterio | Definido: script automatizado contra golden files, tolerancia 0, reporte OK/FAIL (T-F2-25) |
| "UAT" sin guion | Tarea explícita T-F6-12 (guion) + T-F6-13 (ejecución) con criterios pass/fail |

---

## Estado actual del proyecto (a 2026-11-11)

| Fase | Tareas | Completadas | En progreso | Pendientes |
|---|---|---|---|---|
| **F0** | 10 | 7 | 3 | 0 |
| **F1** | 31 | 31 | 0 | 0 |
| **F2** | 31 | 10 | 0 | 21 |
| **F3** | 14 | 0 | 0 | 14 |
| **F4** | 5 | 0 | 0 | 5 |
| **F5** | 5 | 0 | 0 | 5 |
| **F6** | 16 | 0 | 0 | 16 |
| **TOTAL** | **112** | **48** | **3** | **61** |

**F0 — las 7 completadas:** T-F0-01 (C-010), 04, 06, 07, 08, 09, 10.
**F0 — las 3 en progreso, todas esperando a personas:**

| Tarea | Qué falta | Quién |
|---|---|---|
| T-F0-02 | Poblar `tests/Parity/fixtures/input/` con 12–20 archivos reales anonimizados | Unidad de Estadísticas |
| T-F0-03 | Ejecutar el ValidadorRem sobre cada uno y exportar `datos` + `LogError` | TI / referente técnico |
| T-F0-05 | Firmar `docs/validacion/politica-severidad.md` | Referente de negocio |

> **Ajuste de orden (2026-11-11):** T-F1-15 y T-F1-16 crean policies y *query scopes* sobre los modelos Envio, EnvioArchivo y Observacion, que nacen en F2.1. Quedan en progreso: la estructura de autorización (roles, permisos, middleware y policies de User) existe, pero las policies de envío se escriben cuando existan sus modelos.

> **Nota:** nada de esto ha sido verificado en código real. El estado refleja documentos y planificación. Al implementar, actualizar `[ ]` → `[~]` → `[x]` con evidencia (commit, salida de test, captura).

---

## Próximos pasos inmediatos

1. **Arrancar F1 con T-F1-01** (`composer create-project laravel/laravel:^8.83`). Ninguna decisión lo bloquea: C-010 está cerrada y D1, D4, D5, D6 y D10 están resueltas.
2. **Las 3 tareas de F0 que quedan son humanas**, no de código, y corren en paralelo:
   - T-F0-02/03 — reunir los archivos y generar los *expected* (bloquea F2, no F1).
   - T-F0-05 — firmar la política de severidad (bloquea F2, no F1).
   - T-F0-06/07/08 — los documentos ya están redactados; solo falta que negocio los confirme.
3. **Decisiones abiertas:** D12, D13 y D14. Ninguna bloquea el MVP y todas tienen *fallback*. Se cierran con TI cuando toque F6.

---

**Archivo guardado en:** `C:\xampp\htdocs\www\Cargas\specs\TASK.md`




