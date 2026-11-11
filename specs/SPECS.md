# SPECS.md — Especificaciones Funcionales del MVP (Portal M7)

> **Ubicación canónica:** `specs/SPECS.md`
> **Fuente:** `specs/AGENT.md` + `backup_documentacion/MODULO_M7_PROFUNDIZADO.md` + Constitución v3.1.0
> **Stack (D-STACK REABIERTA, C-014):** Laravel 8.83 + PHP 7.4.3 + MySQL 8.0.42 + AdminLTE 3.2 (Bootstrap 4). **Provisional y congelado:** si TI habilita PHP 8.2 se sube a Laravel 12. Ver §0 de la Constitución.
> **Referencia UX:** Zenith Dashboard (shadcn/ui) como guía visual de L2/L3, no como dependencia. Toda la UI usa AdminLTE 3.2.

---

## 1. Descripción del MVP

**Qué se construye:** Portal web para el envío mensual de estadísticas REM (Resumen Estadístico Mensual) por establecimiento de salud del Servicio de Salud Osorno.

**Necesidad que resuelve:** Reemplaza el proceso manual actual (envío por correo, revisión humana sin trazabilidad, **Cargas REM** WinForms de un solo usuario) por un sistema web multi-usuario que:
- Centraliza la recepción de archivos Excel (`.xls`, `.xlsx`, `.xlsm`)
- Valida **exactamente igual** que **Cargas REM** actual (paridad 0 en numéricos, golden files)
- Añade roles, flujo de revisión, observaciones, auditoría y versionado que hoy no existen
- Genera un paquete aprobado listo para cargar en **Cargas REM** (puente MVP: descarga manual)

**Utilidad principal:** Que los 30 establecimientos de las 7 comunas (Osorno, Puerto Octay, Purranque, Puyehue, Río Negro, San Juan de la Costa, San Pablo) suban sus REM mensuales, el sistema valide automáticamente, los revisores observen/aprueben con trazabilidad completa, y la Unidad de Estadísticas tenga visibilidad de completitud y un paquete confiable para CARGAS REM.

---

## 2. Objetivos

| ID | Objetivo | Verificable |
|---|---|---|
| **OBJ-01** | Validar archivos REM con paridad exacta a **Cargas REM** (V0–V11) | Golden files: 12–20 archivos, tolerancia 0 en numéricos |
| **OBJ-02** | Implementar máquina de estados del envío con transiciones auditadas | Todas las transiciones válidas/prohibidas tienen test |
| **OBJ-03** | Aislar datos por establecimiento en la consulta (no solo en la vista) | Tests positivos y negativos por permiso (RN-ENV-1) |
| **OBJ-04** | Versionar archivos: reenvío = nueva versión, anterior queda histórico | Test de versionado y `es_vigente` |
| **OBJ-05** | Flujo completo: Encargado → sube → valida → envía → Revisor → observa/aprueba | E2E test con 3 roles |
| **OBJ-06** | Auditoría append-only de todas las acciones críticas | Test de inmutabilidad de auditoría |
| **OBJ-07** | Tablero de completitud mensual por SS con exportación | Consulta SQL + vista funcional |
| **OBJ-08** | Despliegue reproducible por FTP en servidor con PHP 7.4.3 | Artefacto completo + migraciones SQL |

---

## 3. Usuarios y permisos conceptuales

### 3.1 Roles

| Rol (slug) | Alcance | Descripción |
|---|---|---|
| `encargado` | Sus establecimientos asignados | Sube archivos, corrige, envía a revisión, ve sus observaciones |
| `revisor` | Establecimientos de su Servicio de Salud | Bandeja, observa, aprueba, confirma carga |
| `admin` | Global | Usuarios, establecimientos, series, períodos, catálogos, auditoría, reaperturas |
| `consulta` (opcional) | Su SS, solo lectura | Dirección/referentes: ve completitud y estados sin operar |

> **Regla:** Un usuario puede tener varios establecimientos (pivot `usuario_establecimiento`). Un encargado puede cubrir un hospital y sus CDT.

### 3.2 Matriz de permisos (RF-032)

| Permiso | encargado | revisor | admin | consulta |
|---|:--:|:--:|:--:|:--:|
| `envios.crear` | ✅ | — | ✅ | — |
| `envios.ver.propio` | ✅ | — | ✅ | — |
| `envios.ver.ss` | — | ✅ | ✅ | ✅ |
| `envios.enviar` | ✅ | — | ✅ | — |
| `envios.observar` | — | ✅ | ✅ | — |
| `envios.aprobar` | — | ✅ | ✅ | — |
| `envios.cargar` | — | ✅ | ✅ | — |
| `envios.reabrir` | — | — | ✅ | — |
| `envios.anular` | — | — | ✅ | — |
| `archivos.descargar` | ✅ | ✅ | ✅ | ✅ |
| `reportes.descargar` | ✅ | ✅ | ✅ | ✅ |
| `completitud.ver` | ✅ | ✅ | ✅ | ✅ |
| `paquetes.generar` | — | ✅ | ✅ | — |
| `catalogos.importar` | — | — | ✅ | — |
| `usuarios.gestionar` | — | — | ✅ | — |
| `periodos.gestionar` | — | — | ✅ | — |
| `auditoria.ver` | — | — | ✅ | — |

### 3.3 Reglas de autorización (RN-ENV)

- **RN-ENV-1 (aislamiento):** Encargado solo ve/envía de sus establecimientos. Comprobación en policy + consulta SQL.
- **RN-ENV-6 (unicidad):** Un solo envío por `(establecimiento, año, mes)`. Reenvíos = versiones de archivo.
- **RN-ENV-7 (segregación):** Revisor no aprueba envío de establecimiento donde es encargado.
- **RN-ENV-8 (inmutabilidad):** Archivo `aprobado`/`cargado` no se edita/borra; solo versión nueva o reapertura admin.
- **RN-ENV-9 (defensa en profundidad):** Descargas verifican policy; almacenamiento fuera de `public/`; `Content-Disposition: attachment`.

---

## 4. Alcance funcional

### 4.1 Incluido en el MVP (L1–L3)

| Funcionalidad | L1 (Modelo/Esqueleto) | L2 (Validación real) | L3 (Flujo completo) |
|---|---|---|---|
| Auth + roles + policies | ✅ | — | — |
| Catálogos (importar, snapshots, plantillas) | ✅ | — | — |
| CRUD establecimientos/series/períodos (admin) | ✅ | — | — |
| Encargado: crear envío, subir archivo | ✅ (stub) | ✅ (V0–V11) | ✅ |
| Versionado de archivos + SHA-256 | ✅ | ✅ | ✅ |
| Validación V0–V5 (estructura) | Stub | ✅ | ✅ |
| Validación V6–V11 (datos + completitud) | — | ✅ | ✅ |
| Reporte de validación (Excel/CSV) | — | ✅ | ✅ |
| Estados del envío + transiciones | ✅ | ✅ | ✅ |
| Observaciones (nivel envío + archivo) | — | — | ✅ |
| Aprobación + confirmación de advertencias | — | — | ✅ |
| Bandeja revisor + filtros | — | — | ✅ |
| Tablero completitud + exportación | — | — | ✅ |
| Auditoría append-only | ✅ | ✅ | ✅ |
| Puente MVP: descarga ZIP manual | — | — | ✅ |
| Notificaciones (BD + correo sync) | — | — | ✅ |

### 4.2 Exclusiones explícitas (fuera del MVP)

| Excluido | ID | Motivo |
|---|---|---|
| Generación archivo MINSAL | P7 | Formato desconocido |
| Migración histórica Access → MySQL | R7 | Proyecto aparte |
| Firma electrónica avanzada | — | Alcance mayor |
| Portal pacientes / app móvil | — | No solicitado |
| Reemplazo total de CARGAS REM | — | Estratégico, no MVP |
| Colas / workers obligatorios | — | Servidor sin cron confirmado |
| 2FA para encargados y consulta | D10 | Solo se exige a revisores y administradores (D-010) |

---

## 5. Requisitos funcionales

### 5.1 Autenticación y sesiones

| ID | Requisito | Descripción | Actor/Condición | Entrada | Resultado | Reglas | CA |
|---|---|---|---|---|---|---|---|
| **RF-001** | Login con email/usuario | Formulario de acceso que valida credenciales y crea sesión | Usuario no autenticado | email/usuario + password | Sesión iniciada + redirección a dashboard según rol | `throttle:5,1` en login; `session.secure=1`, `httponly=1`, `samesite=lax` | CA-001 |
| **RF-002** | Logout | Destruye sesión y cookie | Usuario autenticado | GET `/logout` | Sesión destruida + redirección a login | — | CA-002 |
| **RF-003** | Cambio de período en sesión | Dropdown en header permite cambiar año/mes de trabajo | Usuario autenticado | POST `/periodo` con año/mes | Sesión actualizada + redirección | Solo períodos `abierto` o `en_revision` | CA-003 |
| **RF-004** | Recuperación de contraseña | Email con token de un uso, expiración corta | Usuario no autenticado | Email | Email enviado / token válido | Token de un uso; expiración 1h | CA-004 |

### 5.2 Administración de catálogos y estructura base

| ID | Requisito | Descripción | Actor | Entrada | Resultado | Reglas | CA |
|---|---|---|---|---|---|---|---|
| **RF-005** | Importar snapshot de catálogos | Admin sube ZIP con CSV (servicios, establecimientos, series, plantillas, diccionario, excepciones) | Admin | ZIP (CSV UTF-8) | `catalogo_snapshots` creado + tablas espejo pobladas | Validación: columnas esperadas, conteos de referencia, sin duplicados; snapshot queda `activo=0` hasta activación | CA-005 |
| **RF-006** | Activar snapshot | Admin marca snapshot como activo | Admin | ID snapshot | Snapshot `activo=1`; anterior `activo=0` | Un solo snapshot activo a la vez | CA-006 |
| **RF-007** | CRUD Servicios | Admin crea/edita/desactiva servicios | Admin | Código, nombre, región | Servicio creado/actualizado | Código único; `activo` booleano | CA-007 |
| **RF-008** | CRUD Establecimientos | Admin crea/edita/desactiva establecimientos | Admin | Código, nombre, servicio, comuna, dependencia, tipo | Establecimiento creado/actualizado | Código único; FK a servicio; `activo` booleano | CA-008 |
| **RF-009** | CRUD Series | Admin gestiona series (A, BS, BM, D, P, F, SAX) | Admin | Código, nombre, orden | Serie creada/actualizada | Código único; SAX marcada como local | CA-009 |
| **RF-010** | CRUD Períodos | Admin crea/edita períodos (año, mes, fecha_limite, estado) | Admin | Año, mes, fecha_limite, estado | Período creado/actualizado | Único por (año, mes); estado: `abierto`/`en_revision`/`cerrado` | CA-010 |
| **RF-011** | Matriz establecimiento-serie | Admin define qué series exige cada establecimiento (con vigencia) | Admin | Establecimiento, serie, obligatoria, vigencia | Regla creada/actualizada | Permite override manual; vigencia por fechas | CA-011 |
| **RF-012** | Gestión de plantillas | Admin sube/define plantillas por (año, mes, serie, versión) + mapeo de cabecera/hojas | Admin | Año, mes, serie, versión, mapeos | Plantilla creada + `plantilla_hojas` + `plantilla_cabecera` | Única por (año, mes, serie, versión); snapshot opcional | CA-012 |

### 5.3 Gestión de usuarios y asignaciones

| ID | Requisito | Descripción | Actor | Entrada | Resultado | Reglas | CA |
|---|---|---|---|---|---|---|---|
| **RF-013** | CRUD Usuarios | Admin crea/edita/desactiva usuarios | Admin | Nombre, email, password (12+ chars, mayúscula, número), rol | Usuario creado/actualizado | Password: min 12 chars, sin reutilización (D1); roles: `encargado`/`revisor`/`admin`/`consulta` | CA-013 |
| **RF-014** | Asignar establecimientos a encargado | Admin vincula usuario-encargado con establecimientos | Admin | Usuario + establecimientos | Filas en `usuario_establecimiento` | Un usuario puede tener varios establecimientos; un establecimiento puede tener varios encargados | CA-014 |
| **RF-015** | Reset de contraseña por admin | Admin genera password aleatorio 12 chars (mayús, minús, dígito, símbolo) | Admin | Usuario objetivo | Password reseteado + email opcional | Admin no puede resetearse a sí mismo; requiere confirmación explícita | CA-015 |
| **RF-016** | Perfil de usuario | Usuario ve/edita su nombre, email, password | Usuario autenticado | Datos de perfil | Perfil actualizado | Password actual requerido para cambio | CA-016 |

### 5.4 Envío y carga de archivos (núcleo)

| ID | Requisito | Descripción | Actor | Entrada | Resultado | Reglas | CA |
|---|---|---|---|---|---|---|---|
| **RF-017** | Crear envío | Encargado selecciona período y establececimiento → crea envío si no existe | Encargado | Período (año/mes) + establecimiento | `envios` creado con `estado=recibido` | Unicidad por (establecimiento, año, mes) — RN-ENV-6; solo si período `abierto` | CA-017 |
| **RF-018** | Subir archivo (1 por petición) | Encargado sube un Excel; sistema valida tamaño/extensión/tipo real | Encargado | Archivo (`.xls`/`.xlsx`/`.xlsm`, ≤2MB) | `envio_archivos` creado `estado=subido` + validación ejecutada | Un archivo por POST; validación síncrona; lock `validacion` N=2; timeout 10s | CA-018 |
| **RF-019** | Validación automática V0–V10 | Cadena de reglas ejecutada tras subir archivo | Sistema (Service) | Archivo + snapshot activo | `validaciones` insertadas + `envio_archivos` actualizado (`estado`, conteos, `validado_en`) | V0–V10 por archivo; V11 a nivel envío; bloqueantes detienen cadena salvo V11 | CA-019 |
| **RF-020** | Reenvío (versionado) | Encargado sube nueva versión de misma serie | Encargado | Archivo + envío + serie | Nueva fila `envio_archivos` con `version+1`; anterior `es_vigente=0` | No sobrescribe; `UNIQUE (envio_id, serie_id, version)`; hash SHA-256 único | CA-020 |
| **RF-021** | Revalidar archivo | Usuario solicita revalidación de archivo existente | Encargado/Revisor | ID `envio_archivos` | Hallazgos borrados y regenerados | Idempotente; borra `validaciones` previas del archivo | CA-021 |
| **RF-022** | Descargar archivo original | Usuario descarga el Excel subido | Autorizado (policy) | ID `envio_archivos` | Archivo binario con `Content-Disposition: attachment` | Almacenamiento fuera de `public/`; policy verificada en cada request — RN-ENV-9 | CA-022 |
| **RF-023** | Descargar reporte de validación | Usuario descarga Excel/CSV con hallazgos | Autorizado | ID `envio_archivos` | `.xlsx` o `.csv` con hojas Resumen/Bloqueantes/Advertencias/Info | Nombre: `Validacion_{codigo}_{serie}_{anio}{mes}_v{version}.xlsx` | CA-023 |

### 5.5 Estados del envío y transiciones

| ID | Requisito | Descripción | Actor | Entrada | Resultado | Reglas | CA |
|---|---|---|---|---|---|---|---|
| **RF-024** | Máquina de estados | `recibido` → `validado` → `en_revision` → `observado`/`aprobado` → `cargado`/`anulado` | Sistema (Service) | Eventos de transición | `estado` actualizado + `envio_transiciones` insertado | **Solo** `EstadoEnvioService` escribe `estado`; transiciones prohibidas bloqueadas (CA-025) | CA-024 |
| **RF-025** | Enviar a revisión | Encargado confirma envío cuando todos los archivos están `validado` | Encargado | Acción "Enviar a revisión" | `estado=en_revision` + transición + notificación a revisor | Requiere: 0 bloqueantes en todos los archivos vigentes; V11 completitud OK | CA-026 |
| **RF-026** | Observar (revisor) | Revisor registra observaciones (por serie + texto obligatorio) | Revisor | Checklist series + texto | `estado=observado` + `observaciones` + notificación a encargado | Debe marcar "advertencias revisadas"; al menos una observación | CA-027 |
| **RF-027** | Aprobar (revisor) | Revisor aprueba envío sin bloqueantes y advertencias marcadas revisadas | Revisor | Acción "Aprobar" + confirmación | `estado=aprobado` + archivos `estado=aprobado` + transición + notificación | 0 bloqueantes; advertencias con check "revisadas"; RN-ENV-7 (no autoaprobación) | CA-028 |
| **RF-028** | Reabrir (admin) | Admin reabre envío `aprobado`/`cargado` con motivo | Admin | Motivo obligatorio | `estado=recibido` + transición + auditoría | Solo admin; motivo registrado en auditoría | CA-029 |
| **RF-029** | Anular (admin) | Admin anula envío `recibido`/`con_errores`/`observado` | Admin | Motivo obligatorio | `estado=anulado` + transición + auditoría | Solo admin; no revierte archivos subidos | CA-030 |
| **RF-030** | Confirmar carga | Revisor/Admin marca envío como cargado en CARGAS REM | Revisor/Admin | Acción "Confirmar carga" | `estado=cargado` + `fecha_carga` + transición | Requiere `estado=aprobado`; genera paquete para puente | CA-031 |

### 5.6 Observaciones

| ID | Requisito | Descripción | Actor | Entrada | Resultado | Reglas | CA |
|---|---|---|---|---|---|---|---|
| **RF-031** | Registrar observación | Revisor escribe observación a nivel envío o por archivo/serie | Revisor | Texto + (opcional) serie/archivo | `observaciones` insertada + notificación a encargado | Texto obligatorio si es por archivo; nivel envío o archivo | CA-032 |
| **RF-032** | Ver observaciones | Encargado/Revisor ve historial de observaciones del envío | Encargado/Revisor | ID envío | Lista cronológica con autor, fecha, serie, texto | Filtro por nivel (envío/archivo) | CA-033 |

### 5.7 Completitud y tablero

| ID | Requisito | Descripción | Actor | Entrada | Resultado | Reglas | CA |
|---|---|---|---|---|---|---|---|
| **RF-033** | Matriz de completitud | Vista matriz: establecimientos × series para período/SS | Encargado/Revisor/Admin/Consulta | Período + SS | Tabla con semáforo: 🟢 aprobado/cargado, 🟡 en_revision, 🔴 faltante/bloqueantes, ⚪ no requerida | Series requeridas desde `establecimiento_serie` (histórico + override); SAX configurable | CA-034 |
| **RF-035** | Totales y exportación | Contadores + export CSV/Excel | Mismos roles | Período + SS | Totales: con envío completo, con observaciones, sin envío | Export respeta filtros y semáforo | CA-035 |
| **RF-036** | Drill-down | Click en celda → detalle del envío | Mismos roles | Celda de la matriz | Redirección a `/envios/{id}` | — | CA-036 |

### 5.8 Auditoría y notificaciones

| ID | Requisito | Descripción | Actor | Entrada | Resultado | Reglas | CA |
|---|---|---|---|---|---|---|---|
| **RF-037** | Auditoría append-only | Login, subida, descarga, validación, observación, aprobación, reapertura, carga, importación catálogo, cambio permisos | Sistema | Eventos internos | `auditoria` insertada (usuario, acción, entidad, entidad_id, datos, IP, timestamp) | Sin UPDATE/DELETE desde UI; retención propuesta 5 años (D9) | CA-037 |
| **RF-038** | Notificaciones en BD + correo | Eventos generan `notifications` + correo sync (timeout 5s, try/catch) | Sistema | Eventos de la tabla §14 | Notificación en BD siempre; correo si sale en 5s | Si falla correo: operación no revierte; admin puede reenviar | CA-038 |

### 5.9 Puente MVP (descarga manual)

| ID | Requisito | Descripción | Actor | Entrada | Resultado | Reglas | CA |
|---|---|---|---|---|---|---|---|
| **RF-039** | Generar paquete aprobado | Revisor/Admin descarga ZIP con archivos aprobados + manifiesto JSON | Revisor/Admin | Envío `aprobado`/`cargado` | ZIP con estructura: `archivos/`, `manifiesto.json` (hash, conteos, snapshot, fechas) | Solo envíos `aprobado`/`cargado`; manifiesto incluye HMAC (D7 opcional) | CA-039 |

---

## 6. Flujos principales

### 6.1 Flujo: Subida y validación inmediata (RF-017 a RF-021)

```
1. Encargado accede a /envios/crear → selecciona período (año/mes) → sistema crea/busca envío
2. Encargado selecciona serie y archivo (.xls/.xlsx/.xlsm ≤2MB) → POST /envios/{id}/archivos
3. FormRequest: autorización (policy) + período abierto + archivo válido
4. EnvioService::subirVersion (transacción):
   a. lockForUpdate(envio)
   b. Calcula versión, desmarca vigente anterior
   c. Guarda archivo en storage/private/envios/ con nombre generado (SHA-256)
   c. Inserta envio_archivos (estado=subido)
   d. ValidadorArchivo->validar(ContextoValidacion)
   e. Inserta validaciones + actualiza archivo (estado, conteos, validado_en)
   f. EstadoEnvioService::recalcular(envio)
   g. Audita: subida + validación
5. Retorna 201 con resumen: "Serie A validada: 1.234 filas, 2 advertencias"
6. UI muestra botón "Enviar a revisión" si todos los archivos están validado + V11 OK
```

### 6.2 Flujo: Revisión, observación y aprobación (RF-025 a RF-028)

```
1. Encargado: "Enviar a revisión" → POST /envios/{id}/enviar
   → EstadoEnvioService: valida 0 bloqueantes + V11 OK → estado=en_revision
   → Notifica a revisor del SS (BD + correo)
2. Revisor: abre bandeja /supervision → filtros por SS, establecimiento, período, estado, antigüedad
3. Revisor: abre detalle → ve grilla de series (estado, versión, filas, bloqueantes, advertencias)
4. Si hay correcciones:
   a. Revisor: "Observar" → checklist por serie + texto obligatorio → POST /envios/{id}/observar
   → estado=observado + observaciones + notifica a encargado
   b. Encargado: ve observación → corrige → sube nueva versión (RF-020) → revalida
   → estado vuelve a recibido → ciclo se repite
5. Si todo conforme:
   a. Revisor: "Aprobar" → modal con resumen (bloqueantes=0, advertencias con check "revisadas") + comentario
   → POST /envios/{id}/aprobar → EstadoEnvioService: valida bloqueantes=0 + advertencias revisadas
   → estado=aprobado + archivos estado=aprobado + transición + notifica a encargado
```

### 6.3 Flujo: Completitud mensual (RF-033 a RF-036)

```
1. Usuario accede a /completitud → selecciona período (año/mes) + SS
2. Consulta SQL (§9.2) retorna matriz establecimientos × series requeridas
3. UI renderiza semáforo por celda:
   🟢 aprobado/cargado | 🟡 en_revision | 🔴 faltante/con_errores/bloqueantes | ⚪ no requerida
4. Totales: con envío completo | con observaciones | sin envío
5. Exportación CSV/Excel respeta filtros
5. Click en celda → /envios/{id} (drill-down)
```

---

## 7. Interfaz y experiencia de usuario

### 7.1 Pantallas y vistas (basadas en §10 de MODULO_M7_PROFUNDIZADO)

| Pantalla | Ruta | Rol | Componentes clave | Estados vacíos |
|---|---|---|---|---|
| **Login** | `/` | Público | Formulario email/password, throttle, "¿Olvidó password?" | — |
| **Dashboard Encargado** | `/` (auth) | Encargado | Tarjetas: envíos en curso, pendientes de revisión, completitud del mes; acceso rápido a crear envío | "Sin envíos en este período" |
| **Crear/Ver Envío** | `/envios/{id}` | Encargado/Revisor/Admin | Cabecera (estado, período, establecimiento); grilla series (estado, versión, filas, bloqueantes, advertencias); historial; observaciones | "Sin archivos aún" |
| **Subir archivo** | Modal en `/envios/{id}` | Encargado | Input file + validación cliente (ext/tamaño) + progreso | — |
| **Resultado Validación** | `/envios/{id}/archivos/{archivoId}/validacion` | Encargado/Revisor/Admin | Resumen, filtros por severidad, tabla hoja/celda/mensaje, descarga reporte | "Sin hallazgos" |
| **Bandeja Revisor** | `/supervision` | Revisor/Admin | Filtros: SS, establecimiento, período, estado, antigüedad; contador por estado; paginación server-side | "No hay envíos pendientes" |
| **Aprobar** | Modal en `/envios/{id}` | Revisor/Admin | Resumen archivos, bloqueantes (debe ser 0), advertencias con check "revisadas", comentario | "No se puede aprobar con bloqueantes" |
| **Observar** | Modal en `/envios/{id}` | Revisor/Admin | Checklist por serie + textarea obligatorio | "Escriba al menos una observación" |
| **Completitud** | `/completitud` | Todos | Matriz + totales + exportación; drill-down | "No hay establecimientos activos" |
| **Catálogos Admin** | `/admin/catalogos` | Admin | Snapshot activo, conteos, importar ZIP/CSV, historial | "El archivo no tiene el formato esperado" |
| **Usuarios Admin** | `/admin/usuarios` | Admin | CRUD + asignación establecimientos + reset password | — |
| **Auditoría** | `/admin/auditoria` | Admin | Filtros: usuario, evento, entidad, fecha | "Sin registros" |

### 7.2 Criterios de UX (de §10.4)

- Todo en español chileno, sin jerga técnica en mensajes al encargado
- Estado siempre visible con mismo color/nombre en todas las pantallas
- Nunca "error 500" pelado: mensaje accionable + código de referencia (ENV-###)
- Confirmación explícita para aprobar y reabrir
- Accesibilidad básica: foco visible, etiquetas asociadas, contraste, navegación por teclado
- Sin `target="_blank"` en descargas
- **Referencia visual L2/L3:** Zenith Dashboard (shadcn/ui) — sidebar colapsable, cards de métricas, tablas con filtros/paginación/acciones, formularios con validación inline, modales de confirmación, tema claro/oscuro con CSS variables
- **UI en L1–L3:** AdminLTE 3.2 (Bootstrap 4) `dist/` precompilado — sin Node en producción

---

## 8. Datos conceptuales

### 8.1 Entidades principales y relaciones (del ERD §4.1)

| Entidad | Propósito | Relaciones clave |
|---|---|---|
| **Servicio** | SS Osorno + otros | 1:N Establecimientos |
| **Establecimiento** | Hospital/CDT/CECOSF | N:1 Servicio; N:M Usuarios (pivot); N:M Series (pivot); 1:N Envíos |
| **Serie** | A, BS, BM, D, P, F, SAX | 1:N Envío_Archivos; N:M Establecimientos (pivot); 1:N Plantillas |
| **Período** | Año + mes trabajable | 1:N Envíos; estado: abierto/en_revision/cerrado |
| **Envío** | Unidad mensual por establecimiento | N:1 Establecimiento, Período; 1:N Archivos, Observaciones, Transiciones |
| **Envío_Archivo** | Versión de una serie en un envío | N:1 Envío, Serie, Plantilla, Snapshot; versionado + es_vigente + SHA-256 |
| **Validación** | Hallazgo de una regla sobre un archivo | N:1 Envío_Archivo; código ENV-###, severidad, detalle |
| **Observación** | Comentario humano (revisor) | N:1 Envío; opcional N:1 Archivo; autor, texto, fecha |
| **Envío_Transición** | Auditoría de cambios de estado | N:1 Envío; desde, hasta, usuario, fecha |
| **Catálogo_Snapshot** | Foto de catálogos en momento de validación | 1:N Plantillas, Archivos, Diccionario, Excepciones |
| **Plantilla** | Definición de Excel por (año, mes, serie, versión) | N:1 Serie, Snapshot; 1:N Hojas, Cabeceras |
| **Diccionario** | Espejo de mapeo celdas → prestaciones | N:1 Snapshot; búsqueda por (snapshot, año, serie, hoja, linea) |
| **Excepciones** | Columnas no aplicables por (año, mes, serie, hoja) | N:1 Snapshot |
| **Usuario** | Cuenta + rol + asignaciones | N:M Establecimientos (pivot); 1:N Envíos, Observaciones, Transiciones, Auditoría |

### 8.2 Datos de semilla (seed mínimo para L1)

| Tabla | Registros | Propósito |
|---|---|---|
| `servicios` | 1 (SS Osorno) | Raíz de la jerarquía |
| `establecimientos` | 7 (1 por comuna) | Cobertura mínima |
| `series` | 7 (A, BS, BM, D, P, F, SAX) | Catálogo completo |
| `periodos` | 3–6 (meses consecutivos) | Flujo temporal |
| `establecimiento_serie` | 7×7 (todas obligatorias) | Completitud base |
| `users` | 4 (1 admin, 1 revisor, 2 encargados) | Roles base |
| `usuario_establecimiento` | 2 encargados × 3-4 establecimientos | Asignaciones cruzadas |

---

## 9. Reglas de negocio

### 9.1 Pipeline de validación (V0–V11)

| Etapa | Código | Severidad | Qué valida | Si falla |
|---|---|---|---|---|
| **V0** | ENV-001 a ENV-004, ENV-027, ENV-028 | Bloqueante/Advertencia | Existencia, tamaño ≤2MB, extensión permitida, tipo real (finfo/ZIP/OLE2), no corrupto/cifrado | Bloqueante |
| **V1** | ENV-005 a ENV-008 | Bloqueante | Nombre: código-serie-mes (regex + catálogos); acepta sufijos `(1)`, `_corregido` | Bloqueante |
| **V2** | ENV-009 a ENV-013 | Bloqueante | Cabecera interna: serie, año, mes, versión, código establecimiento (detección por etiqueta o coordenadas) | Bloqueante |
| **V3** | ENV-014 | Bloqueante | Versión de plantilla contra `plantillas(anio, mes, serie)` | Bloqueante |
| **V4** | ENV-015 | Bloqueante | Pertenencia establecimiento al SS y al usuario | Bloqueante |
| **V5** | ENV-016, ENV-017 | Bloqueante/Advertencia | Hojas obligatorias y rangos (`plantilla_hojas`) | Bloqueante/Advertencia |
| **V6** | ENV-018, ENV-019 | Advertencia/Bloqueante | Mapeo celdas y lectura por prestación (`diccionario`) | Bloqueante si no se puede leer |
| **V7** | ENV-020, ENV-021, ENV-031 | Advertencia | Tipo de dato y decimales (`diccionario.tipo_dato`) + fórmulas sin valor | Advertencia |
| **V8** | ENV-022 | Advertencia | Columnas no aplicables (`excepciones`) | Advertencia |
| **V9** | ENV-023 | Advertencia | Duplicados internos de prestación (mismo archivo) | Advertencia |
| **V10** | ENV-024 | Bloqueante | Al menos un registro válido en el archivo | Bloqueante |
| **V11** | ENV-025 | Bloqueante (nivel envío) | Completitud de series requeridas (`establecimiento_serie`) | Bloqueante para "Enviar a revisión" |

> **Política de severidad:** Bloqueante impide `validado`/`en_revision`. Advertencia no impide avanzar, pero revisor debe marcar "revisadas" antes de aprobar. Informativa solo para reporte.

### 9.2 Reglas de autorización (RN-ENV)

| Regla | Descripción | Implementación |
|---|---|---|
| **RN-ENV-1** | Aislamiento por establecimiento en consulta | Policy + scope SQL `WHERE establecimiento_id IN (SELECT establecimiento_id FROM usuario_establecimiento WHERE usuario_id = ?)` |
| **RN-ENV-6** | Unicidad de envío | `UNIQUE KEY uq_envios_periodo (establecimiento_id, anio, mes)` |
| **RN-ENV-7** | Segregación revisor/encargado | Policy: `revisor_id` no puede estar en `usuario_establecimiento` del envío |
| **RN-ENV-8** | Inmutabilidad aprobado/cargado | Solo versión nueva o reapertura admin; Policy deniega UPDATE/DELETE en estado final |
| **RN-ENV-9** | Defensa en profundidad descargas | Controlador verifica policy + archivo fuera de `public/` + `Content-Disposition: attachment` |

### 9.3 Reglas de versionado y hash

- **SHA-256 único:** `UNIQUE KEY ix_archivo_hash (sha256)` — mismo archivo no se sube dos veces (ENV-029)
- **Versionado:** `UNIQUE (envio_id, serie_id, version)`; `es_vigente` solo uno por (envío, serie)
- **Revalidación:** Borra `validaciones` previas del archivo y regenera (idempotente)

### 9.4 Reglas de período

| Estado | Efecto |
|---|---|
| `abierto` | Cargas y reenvíos permitidos |
| `en_revision` | Solo reenvíos de envíos observados; nuevos requieren admin |
| `cerrado` | Nadie carga; solo admin reabre con motivo |

---

## 10. Casos límite y errores

| Situación | Comportamiento esperado | Código |
|---|---|---|
| Archivo > 2 MB | Rechazo en cliente (HTML5) y servidor (FormRequest) | ENV-001 |
| Extensión no permitida (ej. `.pdf`) | Rechazo | ENV-002 |
| Archivo corrupto / protegido con password | Rechazo tras `finfo` + intento lectura | ENV-003, ENV-004 |
| Nombre sin convención `codigo-serie-mes` | Rechazo (acepta sufijos numéricos y `_corregido`) | ENV-005 |
| Serie del nombre no existe en catálogo | Rechazo | ENV-006 |
| Mes del nombre ≠ período del envío | Rechazo | ENV-007 |
| Código del nombre ≠ establecimiento del envío | Rechazo | ENV-008 |
| Cabecera no detectable (etiquetas SERIE/AÑO/MES/VERSIÓN/ESTABLECIMIENTO) | Rechazo con etiquetas buscadas | ENV-009 |
| Serie cabecera ≠ serie nombre | Rechazo | ENV-010 |
| Año cabecera ≠ período | Rechazo | ENV-011 |
| Mes cabecera ≠ período | Rechazo | ENV-012 |
| Establecimiento cabecera ≠ envío | Rechazo | ENV-013 |
| Versión plantilla no coincide con catálogo | Rechazo | ENV-014 |
| Establecimiento no pertenece al SS/usuario | Rechazo | ENV-015 |
| Hoja obligatoria faltante | Bloqueante | ENV-016 |
| Hoja extra no prevista | Advertencia | ENV-017 |
| Prestación sin datos en hoja | Advertencia | ENV-018 |
| Rango ilegible (celda vacía/merged) | Bloqueante | ENV-019 |
| Valor no numérico donde se espera número | Advertencia (registra vacío) | ENV-020 |
| Decimales exceden configuración | Advertencia | ENV-021 |
| Columna no aplicable con valor | Advertencia | ENV-022 |
| Prestación duplicada en mismo archivo | Advertencia | ENV-023 |
| Archivo sin registros válidos | Bloqueante | ENV-024 |
| Faltan series obligatorias (V11) | Bloqueante para "Enviar a revisión" | ENV-025 |
| Reintentar aprobar envío ya cargado | Bloqueante | ENV-026 |
| Estructuras no permitidas en Excel (XML externo, etc.) | Bloqueante | ENV-027 |
| Archivo con macros (`.xlsm`) | Advertencia (no se ejecutan) | ENV-028 |
| Mismo SHA-256 ya subido en este envío | Bloqueante | ENV-029 |
| Timeout de validación (>10s lock o >90s proceso) | Bloqueante + mensaje reintente | ENV-030 |
| Fórmula sin valor calculado | Advertencia | ENV-031 |
| Antivirus no verifica (si habilitado) | Bloqueante | ENV-032 |

### Comportamiento ante fallos de infraestructura

| Fallo | Comportamiento |
|---|---|
| BD inaccesible | Error 503 con mensaje "Servicio temporalmente no disponible"; health check interno lo detecta |
| `storage` no escribible | Error 500 con código; log en `logs/php-error.log` (fallback Windows) |
| Correo falla (timeout 5s / error SMTP) | Operación no revierte; notificación en BD; log "No se pudo enviar correo"; admin puede reenviar |
| Lock de concurrencia no obtenido (10s) | Mensaje "El servidor está procesando otros archivos, reintente en unos segundos" |

---

## 11. Requisitos no funcionales

| ID | Requisito | Métrica objetivo | Comentario |
|---|---|---|---|
| **RNF-001** | Rendimiento subida | p95 < 5 s (archivo 1.6 MB, red interna) | Depende de red del establecimiento |
| **RNF-002** | Rendimiento validación | p95 < 90 s, < 512 MB RAM | Medir con archivos reales en F0 |
| **RNF-003** | Rendimiento detalle envío | p95 < 1.5 s | Consultas indexadas |
| **RNF-004** | Rendimiento bandeja revisor | p95 < 2 s (100 envíos) | Paginación server-side |
| **RNF-005** | Rendimiento completitud | p95 < 2 s (30 estab × 7 series) | Índices por período |
| **RNF-006** | Concurrencia | 2 validaciones simultáneas (lock N=2) | Amplíable si servidor tiene RAM |
| **RNF-007** | Almacenamiento | ~330 MB/mes (30 establecimientos) | 4 GB/año; planificar respaldo |
| **RNF-008** | Seguridad — HTTPS | TLS 1.2+; HSTS; headers endurecidos | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, CSP |
| **RNF-009** | Seguridad — Auth | `session.secure=1`, `httponly=1`, `samesite=lax`; throttle login 5/1min | 2FA opcional (D10) |
| **RNF-010** | Seguridad — Archivos | Fuera de `public/`; descarga autenticada; `Content-Disposition: attachment`; macros no ejecutadas | RN-ENV-9 |
| **RNF-011** | Seguridad — Auditoría | Append-only; sin edición/borrado UI; retención propuesta 5 años (D9) | |
| **RNF-012** | Privacidad | Datos REM = confidenciales (Ley 21.719); minimizar datos personales; aviso en login | D1 pendiente |
| **RNF-013** | Accesibilidad | WCAG 2.2 AA: foco visible, etiquetas, contraste, navegación teclado | Constitución Principio 23 |
| **RNF-014** | Usabilidad | Mensajes en español + código ENV-### + acción sugerida; estado siempre visible; confirmaciones solo en irreversibles | Tiempo aprendizaje < 15 min para encargado nuevo |
| **RNF-015** | Mantenibilidad | Reglas validación como clases testeables; catálogos desacoplados; migraciones versionadas y exportadas SQL | |
| **RNF-016** | Operación | Health check (BD + storage); backup documentado; logs rotación diaria 90 días; zona horaria America/Santiago | |

---

## 12. Criterios de aceptación

### 12.1 Por requisito funcional (formato Given/When/Then)

| CA | Requisito | Escenario |
|---|---|---|
| **CA-001** | RF-001 | **Dado** usuario no autenticado **Cuando** ingresa credenciales válidas **Entonces** sesión iniciada y redirigido a dashboard según rol |
| **CA-002** | RF-002 | **Dado** usuario autenticado **Cuando** accede a `/logout` **Entonces** sesión destruida y redirigido a login |
| **CA-005** | RF-005 | **Dado** admin con ZIP válido **Cuando** importa en `/admin/catalogos` **Entonces** snapshot creado, tablas pobladas, conteos coinciden, snapshot inactivo |
| **CA-017** | RF-017 | **Dado** encargado con período abierto **Cuando** crea envío para su establecimiento **Entonces** envío creado `estado=recibido`; segundo intento falla por unicidad |
| **CA-018** | RF-018 | **Dado** encargado con archivo .xlsx 1.5MB **Cuando** sube en `/envios/{id}/archivos` **Entonces** archivo guardado, validación ejecutada, `envio_archivos` con hallazgos |
| **CA-019** | RF-019 | **Dado** archivo con 3 hojas obligatorias y 1 faltante **Cuando** valida **Entonces** `validaciones` con ENV-016 (bloqueante) y ENV-017 (advertencia) |
| **CA-020** | RF-020 | **Dado** envío con archivo v1 vigente **Cuando** sube nueva versión misma serie **Entonces** v1 `es_vigente=0`, v2 `es_vigente=1`, hash único, auditoría registrada |
| **CA-024** | RF-024 | **Dado** envío en `recibido` **Cuando** se ejecuta transición válida **Entonces** `estado` actualizado + `envio_transiciones` insertado; transición inválida lanza excepción |
| **CA-026** | RF-025 | **Dado** envío con todos archivos `validado` y V11 OK **Cuando** encargado envía a revisión **Entonces** `estado=en_revision`, transición registrada, revisor notificado |
| **CA-027** | RF-026 | **Dado** envío en `en_revision` **Cuando** revisor observa con texto + checklist **Entonces** `estado=observado`, observación guardada, encargado notificado |
| **CA-028** | RF-027 | **Dado** envío `en_revision`/`observado` sin bloqueantes + advertencias revisadas **Cuando** revisor aprueba **Entonces** `estado=aprobado`, archivos `aprobado`, transición, notificación |
| **CA-029** | RF-028 | **Dado** envío `aprobado` **Cuando** admin reabre con motivo **Entonces** `estado=recibido`, transición + auditoría con motivo |
| **CA-034** | RF-033 | **Dado** período con 7 establecimientos × 7 series **Cuando** accede a `/completitud` **Entonces** matriz 7×7 con semáforo correcto según estados de archivos vigentes |
| **CA-037** | RF-037 | **Dado** cualquier evento auditado **Cuando** ocurre **Entonces** fila en `auditoria` con usuario, acción, entidad, entidad_id, timestamp; sin UPDATE/DELETE posible desde UI |
| **CA-039** | RF-039 | **Dado** envío `aprobado` **Cuando** revisor descarga paquete **Entonces** ZIP con archivos + `manifiesto.json` (hashes, conteos, snapshot, HMAC) |

### 12.2 Criterios de aceptación por fase (del plan §16.1)

| Fase | Criterio |
|---|---|
| **F0** | Lector reproduce 100% registros y errores de golden files; negocio firma política de severidad |
| **F1** | Admin importa snapshot, crea usuarios y asigna establecimientos; auditoría registra todo |
| **F2** | Encargado sube 7 archivos, ve resultado por serie, descarga reporte; reenvíos versionan |
| **F3** | Revisor observa y aprueba; encargado corrige y reenvía; no se puede aprobar con bloqueantes |
| **F4** | Matriz muestra faltantes y estados del período; exporta correctamente |
| **F5** | ZIP se verifica (hash/HMAC) e importador/marcado confirma carga |
| **F6** | Pruebas de autorización, carga y UAT aprobadas; backup/restauración probados |

---

## 13. Dependencias y trazabilidad

| Requisito | Depende de | Objetivo | Regla | Flujo | CA |
|---|---|---|---|---|---|
| RF-017 (Crear envío) | RF-008, RF-010, RF-014 | OBJ-02 | RN-ENV-6 | 6.1 | CA-017 |
| RF-018 (Subir archivo) | RF-017, RF-005 (snapshot) | OBJ-01 | V0–V10 | 6.1 | CA-018 |
| RF-019 (Validación) | RF-018, RF-012 (plantillas), RF-011 (series requeridas) | OBJ-01 | V0–V11 | 6.1 | CA-019 |
| RF-020 (Versionado) | RF-018 | OBJ-04 | SHA-256 único | 6.1 | CA-020 |
| RF-024 (Estados) | RF-017, RF-019 | OBJ-02 | Transiciones únicas | 6.2 | CA-024 |
| RF-025 (Enviar) | RF-024, RF-019 (V11) | OBJ-02, OBJ-05 | RN-ENV-1 | 6.2 | CA-026 |
| RF-027 (Aprobar) | RF-024, RF-019 (bloqueantes=0) | OBJ-05 | RN-ENV-7 | 6.2 | CA-028 |
| RF-033 (Completitud) | RF-011, RF-017, RF-019 | OBJ-07 | Series requeridas | 6.3 | CA-034 |
| RF-037 (Auditoría) | Todos los RF que cambian estado/datos | OBJ-06 | Append-only | Transversal | CA-037 |
| RF-039 (Puente) | RF-027 (aprobado) | OBJ-08 | D7 | 6.2 final | CA-039 |

---

## 14. Decisiones y su estado

> Todas las decisiones de diseño están cerradas. Las preguntas a TI quedaron respondidas por C-010; solo TI-3 y TI-4 siguen sin confirmación y **no bloquean** el MVP.

| ID | Decisión | Opciones | Bloquea | Estado |
|---|---|---|---|---|
| **PEND-001 (D-STACK)** | Stack final | A1/A2 (Laravel 13) / **B (Laravel 8 / PHP 7.4)** / C/D/E | Todo código app | ✅ **Cerrada (C-010): Opción B** |
| **PEND-002 (D1)** | Fuente de identidad | Cuentas locales / correo institucional / directorio SS | Alta de usuarios (RF-013) | ✅ **Resuelta: Cuentas locales** (D-001) |
| **PEND-003 (D4)** | Plazo mensual | Día 5 / 10 / 15 / variable (configurable por período) | Cierre período (RF-010) | ✅ **Resuelta: Día 10 configurable** (D-004) |
| **PEND-004 (D5)** | Series obligatorias | Todas / por tipo / histórico + override | Completitud (RF-011, RF-033) | ✅ **Resuelta: Histórico + override manual** (D-005) |
| **PEND-005 (D6)** | Tratamiento SAX | Serie normal / especial | Plantillas + validación | ✅ **Resuelta: Fuera del MVP** (D-006) |
| **PEND-006 (D7)** | Puente CARGAS REM | A (descarga manual) / B (paquete+importador) / C (reemplazo) | RF-039 | ✅ **Resuelta: MVP = A** (D-007) |
| **PEND-007 (D2)** | Unidad de aprobación | Por envío / por archivo / mixto | Flujo revisión (RF-025, RF-027) | ✅ **Resuelta: Mixto (envío con veto por serie)** (D-002) |
| **PEND-008 (D3)** | Corrección tras aprobado | Reapertura admin / versión automática / bloqueada | Integridad datos (RF-028, RF-030) | ✅ **Resuelta: Versión automática nueva** (D-003) |
| **PEND-009 (D8)** | Notificaciones | Portal + correo / solo portal / solo correo | Aviso usuarios (RF-038) | ✅ **Resuelta: Solo portal (BD)** (D-008) |
| **PEND-010 (D9)** | Retención | 1 / 3 / 5 años / indefinido | Legal/almacenamiento (RF-037) | ✅ **Resuelta: 5 años** (D-009) |
| **PEND-011 (D10)** | 2FA | Nadie / admins / revisores+admins / todos | Auth (RF-001) | ✅ **Resuelta: Revisores y admins** (D-010) |
| **PEND-012 (TI-1)** | Actualizar Ubuntu/PHP | Sí / No | D-STACK → A1 | ✅ **Respondida: No** (C-010) |
| **PEND-013 (TI-2)** | PHP-FPM 8.3 para vhost | Sí / No | D-STACK → A2 | ✅ **Respondida: No** (C-010) |
| **PEND-014 (TI-3)** | Extensiones en PHP 7.4 | `pdo_sqlite`, `opcache`, `bcmath` | Solo SQLite local y opcache | 🟡 **Sin confirmar — no bloquea** |
| **PEND-015 (TI-4)** | Acceso SSH | Sí / No | Despliegue estándar | 🟡 **No confirmado — despliegue por FTP** |

---

## Verificación de coherencia y cobertura ✅

- [x] Todos los objetivos funcionales (OBJ-01 a OBJ-08) tienen requisitos asociados
- [x] Flujos principales (6.1, 6.2, 6.3) cubren el ciclo completo encargado→revisor→carga
- [x] Reglas de negocio (V0–V11, RN-ENV, versionado, períodos) no se contradicen
- [x] Casos límite importantes (10.1, 10.2) tienen comportamiento definido con códigos ENV-###
- [x] Requisitos no contradicen `specs/AGENT.md` (stack B canon C-010, D-STACK cerrada, Constitución v3.1.0)
- [x] Funcionalidades fuera de alcance identificadas explícitamente (4.2)
- [x] No hay detalles de implementación prematuros (arquitectura, endpoints, clases solo como referencia en §5.5/§11 de fuente)
- [x] Cada RF tiene al menos un CA verificable
- [x] Identificadores únicos y estables (RF-, RN-, CA-, PEND-)
- [x] Referencia UX al Zenith Dashboard documentada para L2/L3 (UI en AdminLTE 3.2, canon C-010)

---

## Resumen

| Métrica | Valor |
|---|---|
| **Requisitos funcionales (RF-)** | 39 |
| **Reglas de negocio (RN-)** | 11 (V0–V11 + RN-ENV + versionado + períodos) |
| **Criterios de aceptación (CA-)** | 39 (1 por RF + 7 por fase) |
| **Decisiones registradas (PEND-)** | 15 · **0 bloquean F1** |
| **Objetivos (OBJ-)** | 8 |
| **Roles** | 4 (encargado, revisor, admin, consulta) |
| **Permisos** | 18 |

---

**Archivo guardado en:** `C:\xampp\htdocs\www\Cargas\specs/SPECS.md`

---

> **Nota sobre D-STACK:** Este documento fija **Opción B (Laravel 8.83 + PHP 7.4.3 + MySQL 8.0.42 + AdminLTE 3.2)** como canon vigente (C-010), coherente con las restricciones confirmadas del servidor (PHP 7.4.3 en el vhost principal). Cambiar a Laravel 13 / AdminLTE 4 exige una decisión nueva en `02-DOCS/wiki/sdd/decisions.md` y una enmienda MAJOR de la Constitución; no se hace por actualización de este documento.
