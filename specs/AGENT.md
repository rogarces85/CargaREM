# AGENT.md — Contexto General del Proyecto Cargas REM

> Ubicación canónica: `specs/AGENT.md`
> Fuente de verdad para agentes de IA y desarrolladores.
> No contiene especificaciones funcionales detalladas ni decisiones técnicas prematuras.

---

## 1. Nombre e identificación del proyecto

| Campo | Valor |
|---|---|
| **Proyecto** | Portal M7 — Envío de Estadísticas REM por Establecimiento |
| **Sistema legado** | **Cargas REM** (WinForms, .NET Framework, Access `Global.mdb`) |
| **Ejecutable legacy** | `ValidadorRem.exe` (nombre del binario; **no usar como nombre del sistema**) |
| **Repositorio** | `https://github.com/rogarces85/CargaREM.git` |
| **Rama de trabajo** | `docs/constitucion-y-stack` (rama base de documentación; `main` cerrada por Constitución) |
| **Estrategia de ramas** | Flujo `branches`: `main` protegida; todo código entra por PR desde `feature/*` → `docs/constitucion-y-stack`. Sin código en `main` directo. |
| **Harness** | rsc 4.0.5 — target OpenCode, flujo `branches` |

---

## 2. Descripción general

Construir un portal web que reemplace la carga manual de archivos Excel REM (Resumen Estadístico Mensual) desde establecimientos de salud hacia el sistema central, manteniendo **paridad exacta** con las reglas de validación de **Cargas REM** actual, y añadiendo trazabilidad, roles, flujo de revisión y auditoría que hoy no existen.

El sistema actual (**Cargas REM**) es una aplicación de escritorio WinForms que corre en Windows, usa Access (`Global.mdb`) como catálogo y base de datos local, y valida archivos `.xls/.xlsx/.xlsm` contra 11 etapas (V0–V11). El portal M7 debe validar **igual** (tolerancia 0 en numéricos) pero en arquitectura web, multi-usuario, con base de datos MySQL y despliegue en servidor Linux.

---

## 3. Problema o necesidad

| Problema actual | Consecuencia |
|---|---|
| ValidadorRem es WinForms, solo Windows | No corre en el servidor de producción (Ubuntu) |
| Un solo usuario a la vez (Access por archivo) | Cuellos de botella, corrupción, sin concurrencia |
| Sin roles ni permisos | Cualquiera puede borrar/alterar datos |
| Sin trazabilidad de cambios | No hay auditoría ni rendición de cuentas |
| Validación "caja negra" no documentada | No se puede auditar ni replicar |
| Despliegue manual por carpeta compartida | No hay versionado ni rollback confiable |

**Necesidad**: Portal web accesible por HTTPS, multi-usuario, con validación idéntica, roles claros, auditoría completa y despliegue reproducible.

---

## 4. Idea general de la solución

Portal web **Laravel + MySQL** (stack por confirmar — ver D-STACK) con:

- **Validación V0–V11** replicada fielmente, probada contra *golden files* del ValidadorRem
- **Máquina de estados** del envío (recibido → validado → en_revisión → observado/aprobado → cargado/anulado)
- **Tres roles**: Encargado (sus establecimientos), Revisor (todo su SS), Admin (global)
- **Aislamiento por establecimiento** en la consulta (no solo en la vista)
- **Versionado de archivos**: reenvío = nueva versión, anterior queda histórico
- **Auditoría append-only**: login, subida, validación, observación, aprobación, reapertura
- **Despliegue por FTP** (sin SSH confirmado) en subdirectorio `/sist_envios/`

---

## 5. Usuarios / Público objetivo

| Rol | Alcance | Acciones principales |
|---|---|---|
| **Encargado** | Sus establecimientos asignados | Subir archivos, ver reportes, corregir, enviar a revisión |
| **Revisor** | Establecimientos de su Servicio de Salud | Bandeja, observar, aprobar, confirmar carga |
| **Admin** | Global | Usuarios, catálogos, períodos, series, reaperturas, auditoría |
| **Consulta** (opcional) | Solo lectura, su SS | Ver completitud y estados |

> Un usuario puede tener varios establecimientos (pivot `usuario_establecimiento`).

---

## 6. Objetivo general

> Entregar un portal M7 **funcional en local (SQLite) y desplegable en producción (MySQL)** que valide archivos REM con **paridad exacta** al ValidadorRem, implemente el flujo completo encargado→revisor con auditoría, y quede listo para UAT antes de definir el puente con CARGAS REM.

**Entregables mínimos (L1 → L3):**
- L1: Modelo + esqueleto + validador stub (3–4 sem)
- L2: Validación real V0–V11 + golden files (4–5 sem)
- L3: Flujo completo + auditoría + tablero (3–4 sem)

---

## 7. Alcance conceptual inicial (MVP)

| Dentro del MVP (L1–L3) | Fuera del MVP (explícito) |
|---|---|
| Carga, validación, versionado, estados | Generación archivo MINSAL (P7) |
| Observaciones, aprobación, reapertura | Migración histórica Access → MySQL (R7) |
| Catálogos espejo (servicios, establecimientos, series, plantillas) | Firma electrónica avanzada |
| Auditoría append-only + transiciones de estado | Portal pacientes / app móvil |
| Tablero de completitud por SS | Reemplazo total de CARGAS REM |
| Despliegue FTP reproducible | Colas / workers obligatorios |

---

## 8. Tecnologías y restricciones generales

### Stack elegido (pendiente D-STACK)

| Capa | Decisión | Condición |
|---|---|---|
| **Lenguaje** | PHP 8.3 mínimo (objetivo 8.5) | Requiere TI: actualizar servidor o PHP-FPM 8.3 |
| **Framework** | Laravel 13.x | Idem |
| **Base de datos producción** | MySQL 8.0 → 8.4 LTS | 8.0 EOL abril 2026 |
| **Base de datos desarrollo** | SQLite (local) + MySQL 8 (Docker, paridad) | Paridad numérica solo en MySQL |
| **UI** | AdminLTE 4.x (Bootstrap 5.3) `dist/` precompilado | Sin Node en producción |
| **Auth** | Laravel Fortify | Deja abierta puerta a 2FA |
| **Roles/Permisos** | `spatie/laravel-permission` v8 | Tests positivos y negativos |
| **Excel** | PhpSpreadsheet 5.x | Paridad obligatoria con golden files |
| **Tests** | PHPUnit / Pest | Sin umbral de cobertura |

### Restricciones del servidor confirmadas

| Restricción | Valor confirmado | Fuente |
|---|---|---|
| PHP | **7.4.3** (EOL) | Diagnóstico directo en vhost |
| Ubuntu | 20.04.6 LTS (fuera soporte estándar) | Diagnóstico |
| Apache | `mod_php` prefork | Una sola versión PHP por instancia |
| MySQL | 8.0.42, `utf8mb4` | Compartido con `sist_observaciones` |
| Subida | `upload_max_filesize=2M`, `post_max_size=8M` | Configurable via `.htaccess` (se honra) |
| Despliegue | FTP confirmado; SSH probable no confirmado | `sist_observaciones` usa git/composer |
| Cron | Disponible (worker/minuto en otro vhost) | Confirmado indirectamente |

> **D-STACK ABIERTA** (C-009): La decisión final depende de TI.
> - Si TI actualiza a PHP 8.3 → Laravel 13 (Opción A1)
> - Si TI instala PHP-FPM 8.3 → Laravel 13 (Opción A2)
> - Si no hay cambios → Laravel 8 / PHP 7.4 (Opción B, deuda conocida)

### Restricciones de la Constitución (v3.0.0)

1. **Sin código de aplicación mientras D-STACK esté abierta** (Principio 1)
2. **Validación V0–V11**: código `ENV-###`, severidad, test obligatorio (Principio 6)
3. **Transiciones de estado**: servicio único, tests válidas y prohibidas (Principio 7)
4. **RBAC**: test positivo y negativo por permiso (Principio 8)
5. **Paridad**: golden files, tolerancia 0 en numéricos (Principio 10)
6. **Aislamiento en consulta**, no en vista (Principio 18)
7. **Archivos fuera de `public/`**, descarga autenticada (Principio 19)
7. **Sin secretos en repo**, `APP_DEBUG=false` en prod (Principio 20)
8. **Auditoría append-only** (Principio 21)
9. **Datos REM = confidenciales** (Ley 21.719) (Principio 22)
10. **WCAG 2.2 AA** (Principio 23)
11. **Presupuestos medibles**: validación p95 < 90s, 512MB (Principio 25)

---

## 9. Principios y consideraciones importantes

| Principio | Descripción |
|---|---|
| **Paridad ante todo** | El portal no inventa validación; replica la del ValidadorRem. Diferencia = bug o regla no documentada. |
| **Nada se sobrescribe** | Cada reenvío crea versión nueva; la anterior queda `reemplazado`. |
| **Estado único y auditado** | Solo el servicio de transiciones escribe `estado`. Append-only. |
| **Aislamiento real** | Filtro en `WHERE` de cada consulta, no solo en la vista. |
| **Despliegue sin sorpresas** | Artefactos construidos en local (vendor, assets, migraciones). |
| **Tests como especificación** | Un cambio sin test no se fusiona. No hay % de cobertura. |
| **Decisiones registradas** | Toda decisión significativa en `02-DOCS/wiki/sdd/decisions.md`. |
| **UI referencia** | Zenith Dashboard (shadcn/ui) como guía visual para L2/L3; L1 usa AdminLTE 4. |

---

## 10. Decisiones pendientes y limitaciones conocidas

### Decisiones pendientes (bloquean código de aplicación)

| ID | Decisión | Opciones | Bloquea |
|---|---|---|---|
| **D-STACK** | Stack final | A1/A2 (Laravel 13) / B (Laravel 8) / C/D/E | Todo código app |
| **D1** | Fuente de identidad | Cuentas locales / correo institucional / directorio SS | Alta de usuarios |
| **D4** | Plazo mensual | Día 5/10/15 / variable | Cierre y recordatorios |
| **D5** | Series obligatorias | Todas / por tipo / histórico | Completitud |
| **D7** | Puente CARGAS REM | Opción A/B/C | Alcance MVP |
| **D10** | 2FA | Nadie / admins / revisores+admins / todos | Auth |

### Limitaciones técnicas conocidas

| Limitación | Impacto | Mitigación |
|---|---|---|
| PHP 7.4.3 en producción | Laravel 13 no corre | Esperar decisión TI o caer a Laravel 8 |
| Sin `pdo_sqlite`, `opcache`, `bcmath` en servidor | Requeridas por Laravel moderno | TI debe instalarlas si se queda en 7.4 |
| Límite 2 MB/subida | Un mes = ~11 MB (7 archivos) | Subida secuencial obligatoria (ya en diseño) |
| Sin SSH confirmado | No `artisan` en prod | Artefacto completo + ruta migración one-shot |
| MySQL 8.0 EOL | Sin parches desde abril 2026 | Objetivo 8.4 LTS antes de salida |

### Preguntas a TI (bloquean D-STACK)

1. ¿Se puede actualizar Ubuntu 20.04 → 22.04/24.04?
2. ¿Se puede instalar PHP-FPM 8.3 para el vhost `sist_envios`?
3. ¿Se pueden habilitar `pdo_sqlite`, `opcache`, `bcmath` en PHP 7.4?
4. ¿Hay acceso SSH para el usuario del proyecto?

---

## Verificación de consistencia ✅

- [x] Objetivo se desprende de la información disponible
- [x] Restricciones explícitas preservadas (PHP 7.4.3, D-STACK, Constitución)
- [x] No se introdujeron funcionalidades nuevas fuera del alcance documentado
- [x] Propuestas (Laravel 13, Inertia.js) aparecen como condicionales, no decididas
- [x] Cuestiones desconocidas identificadas como pendientes (D-STACK, D1, D4, D5, D7, D10, preguntas TI)
- [x] No hay especificaciones funcionales detalladas ni arquitectura prematura
- [x] Documento guarda en `specs/AGENT.md` (ruta canónica)

---

## Resumen de cambios

**Documento creado** `specs/AGENT.md` consolidando:

1. Contexto del proyecto (ValidadorRem → Portal M7)
2. Problema real (WinForms, Access, sin roles, sin auditoría)
3. Solución objetivo (Laravel/MySQL, paridad, roles, auditoría)
4. Stack elegido condicionalmente (D-STACK abierta)
5. Restricciones duras del servidor (PHP 7.4.3 confirmado)
6. Principios de la Constitución v3.0.0
7. Decisiones pendientes que bloquean código (D-STACK + D1/D4/D5/D7/D10)
8. Referencia UX al Zenith Dashboard para L2/L3

---

**Archivo guardado en:** `C:\xampp\htdocs\www\Cargas\specs/AGENT.md`