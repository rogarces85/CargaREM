# CargaREM — Portal M7

Documentación técnica de **Cargas REM** (el sistema que existe hoy) y especificación del **Portal M7**: el módulo web de envío mensual de estadísticas REM por establecimiento.

> **Estado:** **D-STACK cerrada (C-015): Laravel 12.72 sobre PHP 8.2.** El portal corre en [`portal/`](portal/), migra contra `portal_m7`, responde por Apache y pasa sus tests con **cero avisos de seguridad**. El despliegue espera a que TI habilite PHP 8.2 en el vhost: ver [`solicitud-php-82-ti.md`](docs/infra/solicitud-php-82-ti.md). Ver [`solicitud-php-82-ti.md`](docs/infra/solicitud-php-82-ti.md).

---

## Contexto

**Cargas REM** es la aplicación de escritorio que la Unidad de Estadísticas usa hoy: WinForms, .NET Framework 4.8, x86. Carga, valida y consolida archivos Excel mensuales del REM en bases Access por Servicio de Salud, genera el archivo de envío MINSAL y sincroniza catálogos contra los servicios web del MINSAL.

Hoy los establecimientos envían sus archivos por correo y la Unidad los revisa y carga a mano. El **Portal M7** propone un portal donde cada encargado sube sus archivos, recibe validación inmediata y sigue el estado de su envío, con revisión y aprobación por la Unidad.

El portal **no reemplaza** a Cargas REM en su primera versión: valida, ordena la revisión y entrega un paquete aprobado. La generación del archivo MINSAL queda fuera del MVP.

---

## Mapa del repositorio

| Ruta | Qué contiene |
|---|---|
| [`portal/`](portal/) | **La aplicación.** Laravel **12.72.0** sobre PHP **8.2**, fijado en Composer. MySQL 8 en producción, MariaDB 10.4 en local. AdminLTE 3.2 en `public/assets` |
| [`specs/`](specs/) | **La especificación operativa del MVP.** Cuatro documentos, ~178 KB: [`AGENT.md`](specs/AGENT.md) (contexto), [`SPECS.md`](specs/SPECS.md) (requisitos funcionales y criterios de aceptación), [`PLAN.md`](specs/PLAN.md) (arquitectura, 11 módulos técnicos, modelo de datos), [`TASK.md`](specs/TASK.md) (**112 tareas atómicas** en 7 fases) |
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

## Stack canon — decisión cerrada (C-010)

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 7.4.3 |
| Framework | Laravel 8.83 |
| Base de datos | MySQL 8.0.42 (`utf8mb4`); SQLite solo en local para L1 |
| UI | AdminLTE 3.2 (Bootstrap 4), `dist/` precompilado, sin Node en producción |
| JS | Vanilla |

**Por qué esta versión y no una moderna:** el vhost principal del servidor corre **PHP 7.4.3** en Apache `mod_php`, y TI confirmó que no hay PHP-FPM 8.3 ni actualización de servidor prevista. Es deuda técnica aceptada y registrada en [`sdd/decisions.md`](02-DOCS/wiki/sdd/decisions.md) (C-010). Volver a Laravel 13 exige decisión nueva y enmienda MAJOR de la Constitución.

Otras restricciones confirmadas: `upload_max_filesize=2M`, `post_max_size=8M`, sin `pdo_sqlite`/`opcache`/`bcmath` en el servidor, acceso por FTP, sin SSH confirmado. Detalle en [`stack/restricciones-servidor.md`](02-DOCS/wiki/stack/restricciones-servidor.md).

---

## Estado del proyecto

El plan son **112 tareas atómicas** en 7 fases. Estado real hoy:

| Fase | Alcance | Marcadas como hechas |
|---|---|---|
| **F0** Descubrimiento y paridad | *Golden files*, decisiones, catálogo `ENV-###` | **7 de 10** (3 esperan personas) |
| **F1** Base L1 | Laravel, auth, RBAC, catálogos, auditoría, seed | **29 de 31** (2 pasan a F2 por dependencia de modelos) |
| **F2** Carga y validación L2 | Subida, versionado, V0–V11, reporte, estados | 0 de 31 |
| **F3** Revisión L3 | Observaciones, aprobación, bandeja, notificaciones | 0 de 14 |
| **F4** Completitud y tablero | Matriz semáforo, totales, exportación | 0 de 5 |
| **F5** Puente MVP | ZIP + manifiesto, confirmación de carga | 0 de 5 |
| **F6** Endurecimiento y salida | Seguridad, rendimiento, UAT, respaldo, manuales | 0 de 16 |

Las **12 decisiones de diseño están cerradas** (D1–D11 + D-STACK). Solo quedan abiertas D12 (antivirus), D13 (respaldos) y D14 (migración histórica), y ninguna bloquea el desarrollo. Detalle por tarea: [`specs/TASK.md`](specs/TASK.md).

### Lo que falta para arrancar F1

**Nada.** F1 no depende de F0: solo necesita C-010 cerrada, y lo está.

Lo que queda de F0 son tres tareas humanas que corren en paralelo al código y bloquean **F2**, no F1:

1. **`T-F0-02/03` — *golden files*:** 12–20 archivos Excel reales anonimizados y su salida de referencia del ValidadorRem. La estructura y el `manifest.json` ya existen en [`tests/Parity/fixtures/`](tests/Parity/fixtures/README.md); falta poblarlos.
2. **`T-F0-05` — firma de la política de severidad** por la Unidad de Estadísticas. La [hoja de firma de una página](docs/validacion/hoja-firma-severidad.md) destila los 32 códigos `ENV-###` para firmar sin leer el detalle técnico.
3. **`T-F0-06/07/08` — tres documentos de decisión**, redactados y esperando confirmación de negocio: [matriz de series](docs/validacion/matriz-series-obligatorias.md), [reglas de cierre](docs/validacion/reglas-cierre-periodo.md) e [identidad/2FA](docs/auth/decision-identidad-2fa.md).

---

## Entorno de referencia

El desarrollo va en **dos fases** (C-011): primero el MVP completo en local, después el traslado al servidor.

| Fase | Dónde | Stack real |
|---|---|---|
| **1 — MVP local** | `C:\xampp` (Windows) | PHP 8.2.12 · MariaDB 10.4.32 · Apache 2.4.58 |
| **2 — Servidor** | `estadisticas.ssosorno.cl` (Ubuntu 20.04) | PHP 7.4.3 · MySQL 8.0.42 · Apache 2.4 `mod_php` |

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



