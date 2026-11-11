# AGENT.md — Contexto General del Proyecto Cargas REM

> Ubicación canónica: `specs/AGENT.md`
> Fuente de verdad para agentes de IA y desarrolladores.
> No contiene especificaciones funcionales detalladas ni decisiones técnicas prematuras.
> **Actualizado 2026-11-11:** alineado a la Constitución v3.1.0 (C-010) y **luego afectado por la reapertura de D-STACK (C-014)**. El stack descrito en §8 es el **provisional y congelado**: si TI habilita PHP 8.1+, este documento debe rehacerse sobre Laravel 10.50.4+.

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
| **Harness** | rsc 4.1.2 — target OpenCode, flujo `branches` |

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

Portal web **Laravel 8.83 + PHP 7.4.3 + MySQL 8** (Opción B, provisional por C-014) con:

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

### Stack provisional (Opción B — D-STACK reabierta por C-014)

| Capa | Decisión | Condición |
|---|---|---|
| **Lenguaje** | PHP 7.4.3 | EOL 28-nov-2022; es la causa de la reapertura (C-014) |
| **Framework** | Laravel 8.83 | Última rama que corre en PHP 7.4 |
| **Base de datos producción** | MySQL 8.0.42, `utf8mb4` | Compartido con `sist_observaciones` |
| **Base de datos desarrollo** | SQLite (local, L1 — C-008) + MySQL 8 (paridad) | La paridad numérica se valida en MySQL, no en SQLite |
| **UI** | AdminLTE 3.2 (Bootstrap 4) `dist/` precompilado | Sin Node en producción |
| **Auth** | Laravel Fortify ^1.10 | Deja abierta la puerta a 2FA (D10) |
| **Roles/Permisos** | `spatie/laravel-permission` ^5.11 | Tests positivos y negativos |
| **Excel** | PhpSpreadsheet ^1.29 | Paridad obligatoria con golden files |
| **Tests** | PHPUnit | Sin umbral de cobertura |

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

> **D-STACK REABIERTA** (C-014, 11-nov-2026). Se había cerrado en C-010 con la Opción B porque PHP 7.4.3 es la realidad del vhost principal. Se reabre porque `composer audit` mostró que Laravel 8.83.29 acumula **5 avisos de seguridad sin parche posible**, uno crítico. **El Principio 1 vuelve a bloquear código de aplicación nuevo** y `portal/` queda congelado.
>
> Lo pedido a TI es PHP 8.1+ para poder subir a Laravel 10.50.4+: `docs/infra/solicitud-php-81-ti.md`. Detalle del cierre anterior: `02-DOCS/wiki/stack/decision-stack.md`.

### Restricciones de la Constitución (v3.2.0)

1. **D-STACK abierta**: no se introduce código de aplicación nuevo hasta cerrarla (Principio 1)
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
| **UI referencia** | AdminLTE 3.2 en L1–L3. Zenith Dashboard (shadcn/ui) queda como referencia visual, nunca como dependencia: exige Node y Bootstrap 5. |

---

## 10. Decisiones pendientes y limitaciones conocidas

### Estado de las decisiones

Todas las decisiones de diseño están tomadas. Ninguna bloquea ya el inicio de F1. Registro completo, con fecha, opciones y motivo, en `02-DOCS/wiki/sdd/decisions.md`.

| ID | Decisión | Resuelta como |
|---|---|---|
| **D-STACK** | Stack final | ✅ **Opción B** — Laravel 8.83 / PHP 7.4.3 (C-010) |
| **D1** | Fuente de identidad | ✅ Cuentas locales (D-001) |
| **D2** | Unidad de aprobación | ✅ Mixto: envío completo con veto por serie (D-002) |
| **D3** | Corrección tras aprobado | ✅ Versión automática nueva (D-003) |
| **D4** | Plazo mensual | ✅ Día 10 configurable por período (D-004) |
| **D5** | Series obligatorias | ✅ Histórico + override manual (D-005) |
| **D6** | Tratamiento SAX | ✅ Fuera del MVP, fase futura (D-006) |
| **D7** | Puente CARGAS REM | ✅ Opción A: descarga manual ZIP (D-007) |
| **D8** | Notificaciones | ✅ Solo portal, tabla en BD (D-008) |
| **D9** | Retención | ✅ 5 años (D-009) |
| **D10** | 2FA | ✅ Revisores y admins (D-010) |
| **D11** | PHP/Laravel | ✅ Quedarse en PHP 7.4 / Laravel 8 (C-010) |
| **D12** | Antivirus | 🟡 Abierta — **no bloquea**: validación de tipo real como fallback |
| **D13** | Respaldos | 🟡 Abierta — **no bloquea**: manual / phpMyAdmin como fallback |
| **D14** | Migración histórica | 🟡 Abierta — **no bloquea**: proyecto aparte tras el MVP |

### Limitaciones técnicas conocidas

| Limitación | Impacto | Mitigación |
|---|---|---|
| PHP 7.4.3 en producción | Deuda de soporte (EOL 28-nov-2022) | Deuda aceptada en C-010; migración evaluable como proyecto futuro |
| Sin `pdo_sqlite`, `opcache`, `bcmath` en servidor | Solo afecta SQLite local y opcache; producción usa MySQL | L1 con el SQLite del entorno local; no se depende de opcache |
| Límite 2 MB/subida | Un mes = ~11 MB (7 archivos) | Subida secuencial obligatoria (ya en diseño) |
| Sin SSH confirmado | No `artisan` en prod | Artefacto completo + ruta de migración one-shot |
| MySQL 8.0 EOL | Sin parches desde abril 2026 | Objetivo 8.4 LTS antes de salida |

### Preguntas a TI (respondidas — C-010)

1. ¿Actualizar Ubuntu 20.04 → 22.04/24.04? → **No prevista.**
2. ¿Instalar PHP-FPM 8.3 para `sist_envios`? → **No.**
3. ¿Habilitar `pdo_sqlite`, `opcache`, `bcmath` en PHP 7.4? → **Sin confirmar**; se asume que no y se diseña sin depender de ellas.
4. ¿Acceso SSH para el usuario del proyecto? → **Probable no confirmado**; el despliegue se diseña por FTP.

---

## Verificación de consistencia ✅

- [x] Objetivo se desprende de la información disponible
- [x] Restricciones explícitas preservadas (PHP 7.4.3, Constitución v3.1.0)
- [x] No se introdujeron funcionalidades nuevas fuera del alcance documentado
- [x] Stack alineado a la Opción B y marcado como provisional por C-014 (resuelve `CONT-01` de `PLAN.md` §17.3)
- [x] Todas las decisiones de diseño cerradas; D12–D14 no bloquean código
- [x] No hay especificaciones funcionales detalladas ni arquitectura prematura
- [x] Documento guarda en `specs/AGENT.md` (ruta canónica)

---

## Resumen de cambios

**v2 (2026-11-11)** — Alineación al canon C-010:

1. §4 y §8 dejaron de presentar el stack como pendiente
2. §8 «Stack elegido (pendiente D-STACK)» → «Stack canon (Opción B — D-STACK cerrada por C-010)»
3. §8 «D-STACK ABIERTA (C-009)» → «D-STACK CERRADA (C-010)»
4. §8 restricciones de la Constitución v3.0.0 → v3.1.0
5. §10 decisiones pendientes → estado de las decisiones (D1–D14)
6. §10 preguntas a TI marcadas como respondidas

**v1** — Creación del documento: contexto, problema, solución objetivo, stack condicional, restricciones del servidor, principios, decisiones pendientes y referencia UX.

---

**Archivo guardado en:** `C:\xampp\htdocs\www\Cargas\specs/AGENT.md`

