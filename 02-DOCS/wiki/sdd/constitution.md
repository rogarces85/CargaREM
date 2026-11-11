---
type: constitution
title: Cargas — Constitution
description: Los principios no negociables que toda fase rsc-sdd obedece. Stack canon: Laravel 8.83 + PHP 7.4.3 + MySQL 8 + AdminLTE 3.2 (D-STACK cerrada C-010).
tags: [sdd, constitution, cargas, rem]
timestamp: 2026-11-10T12:30:00Z
topic: sdd
version: v3.2.0
---

# Cargas — Constitution

> Version: v3.2.0 · Ratified: 2026-11-06 · Last amended: 2026-11-11
> Principios no negociables que toda fase rsc-sdd obedece. El detalle técnico vive en
> `02-DOCS/wiki/stack/` y `02-DOCS/wiki/dominio/`; este archivo ratifica el principio y enlaza el detalle.
>
> **Alcance.** Rige el portal M7 de envío REM por establecimiento y la documentación técnica de
> Cargas REM. No rige el ejecutable WinForms existente, que se documenta pero no se modifica aquí.

## 0. Decisión de stack — REABIERTA (C-014)

El canon vigente hasta el 2026-11-11 era **Laravel 8.83 + PHP 7.4.3** (C-010). Se reabre porque `composer audit` sobre el proyecto real confirmó algo que la decisión anterior no evaluó: **Laravel 8.83.29 acumula 5 avisos de seguridad abiertos, uno crítico, y no recibirá parches nunca**. La rama 8.x está en fin de vida.

| # | Hecho confirmado | Consecuencia |
|---|---|---|
| S1 | PHP 7.4.3 (EOL 28-nov-2022) en Apache `mod_php` | Impide correr Laravel 10.50.4+ |
| S2 | Laravel 8.83.29: **5 avisos abiertos, 1 crítico** | No se corrige en 8.x, por diseño |
| S3 | La corrección de la crítica vive en Laravel 10.50.4+ | Requiere PHP 8.1 o superior |
| S4 | `upload_max_filesize=2M`, `display_errors=1` | Configuración de producción a corregir |

**D-STACK abierta.** El **principio 1 vuelve a bloquear** código de aplicación nuevo hasta que la decisión se cierre.

**Estado del código:** `portal/` (Laravel 8.83.29) queda **provisional y congelado**. No se extiende mientras D-STACK esté abierta.

**Lo pedido a TI:** PHP 8.1 o superior, **para** poder subir a Laravel 10.50.4+. PHP 8.1 solo no corrige nada: los avisos son del framework. Solicitud completa en `docs/infra/solicitud-php-81-ti.md`.

**Candidatos al cerrar:**

| Opción | Stack | Requiere |
|---|---|---|
| **B** (actual) | Laravel 8.83 + PHP 7.4.3 | Nada. Asume los 5 avisos y las mitigaciones |
| **C** | Laravel 10.50.4+ + PHP 8.1+ | Que TI habilite PHP 8.1 o superior en el vhost |
| **D** | Laravel 11–12 + PHP 8.2+ | Que TI actualice el servidor, no solo el intérprete |

Volver a cerrar D-STACK exige otra enmienda MAJOR de este documento.

## 1. Stack y restricciones del entorno

1. **Ningún PR introduce un framework, runtime o dependencia de lenguaje mientras D-STACK esté
   abierta.** Se permite documentación, diagramas y prototipos descartables que no entren a
   `main`. Evidencia de la restricción: `02-DOCS/wiki/stack/restricciones-servidor.md`.
2. **Todo candidato de stack debe correr dentro de las restricciones confirmadas** del servidor
   (Ubuntu 20.04, Apache 2.4 `mod_php`, **PHP 7.4.3**, MySQL 8.0.42 `utf8mb4`, acceso solo FTP,
   sin SSH confirmado, `upload_max_filesize=2M`, `post_max_size=8M`). Si un candidato no cabe,
   primero se cambia la restricción con TI y se registra la decisión; no se asume que "el servidor
   se actualizará".
3. **El camino crítico de validación no depende de cron, cola persistente, worker ni shell remoto.**
   La validación ocurre dentro de la petición. Las tareas opcionales —reportes, recordatorios,
   limpieza— pueden usar cron, y el sistema debe funcionar si cron no está disponible. Si se
   necesita concurrencia, se usa un lock con límite explícito.
4. **El despliegue es reproducible con los artefactos construidos en local** (dependencias, assets
   precompilados, migraciones). Si hay SSH se usa `git` + `composer` + `artisan`; si no, se sube el
   árbol completo por FTP y una ruta de migración protegida de un solo uso.
5. **El almacén del portal debe soportar escritura concurrente de varios usuarios y `utf8mb4`.**
   Una base Access por archivo queda descartada como almacén del portal; el Access existente es
   fuente histórica, no destino.

## 2. Calidad

6. **Toda regla de validación V0–V11 tiene: un código `ENV-###` único, una severidad asignada y al
   menos un test que la dispare.** El catálogo está en
   `backup_documentacion/MODULO_M7_PROFUNDIZADO.md` §20.1 y la síntesis en
   `02-DOCS/wiki/dominio/validacion-rem.md`.
7. **Toda transición de estado —válida y prohibida— tiene un test.** El estado del envío solo lo
   escribe un servicio único de transición; ningún controlador ni modelo escribe la columna
   `estado` directamente. Estados y guardas: `MODULO_M7_PROFUNDIZADO.md` §5.
8. **Cada permiso de la matriz RBAC tiene un test positivo y uno negativo**, incluidos el
   aislamiento entre establecimientos y la prohibición de autoaprobación del revisor
   (RN-ENV-1, RN-ENV-7).
9. **No se fusiona con la suite en rojo.** No hay umbral de cobertura global; lo que no puede faltar
   es el test de la regla, transición o permiso que el cambio toca.
10. **La paridad con ValidadorRem se prueba con *golden files*.** Un cambio en la lectura o
    interpretación de celdas (V6–V8) exige comparar contra la salida del escritorio sobre archivos
    reales anonimizados, con tolerancia 0 en numéricos. Sin paridad, el cambio no se fusiona.
    Procedimiento: `02-DOCS/wiki/dominio/validacion-rem.md` §4.

## 3. Convenciones

11. **Idioma:** tablas, columnas, valores de estado y textos de interfaz en español; clases,
    métodos y variables en inglés. Una API pública puede usar inglés, pero el mensaje al usuario
    final es español.
12. **Errores:** todo error de validación o de aplicación que llegue al usuario lleva código
    (`ENV-###` o `APP-###`), severidad y una acción sugerida, en español. Sin mensajes genéricos
    tipo "Error inesperado" sin código.
13. **Commits:** `gitmoji + Conventional Commits`, resumen en imperativo y alcance corto
    (`✨ feat(envios): valida cabecera sin coordenadas rígidas`). No hay hook que lo imponga en
    OpenCode: se verifica en `review`.
14. **Contrato del puente:** toda respuesta de la API del paquete aprobado usa una sola forma de
    error (`codigo`, `mensaje`, `severidad`) y una sola forma de éxito documentada en
    `MODULO_M7_PROFUNDIZADO.md` §12.3.

## 4. Ramas y entrega

15. **El trabajo va en una rama y entra por pull request.** `main` está cerrada para el agente.
    Antes de cada cambio de código se elige rama: la actual, una nueva o desbloquear `main`.
16. **La autoría de git es de la persona.** Sin `Co-Authored-By` de una IA y sin pie
    "generated with". Se verifica en `ship`.
17. **No se edita producción por FTP sin commit.** Un hotfix subido a mano debe quedar commiteado
    en la misma sesión; si no, se revierte.

## 5. Seguridad y privacidad

18. **Aislamiento por establecimiento en la consulta, no en la vista.** Cada consulta y cada
    descarga aplican el alcance del usuario en el servidor, en cada petición (RN-ENV-1, RN-ENV-9).
19. **Los archivos viven fuera de la raíz pública**, con nombre generado, permisos restrictivos y
    descarga autenticada con `Content-Disposition: attachment`. Las macros de `.xlsm` nunca se
    ejecutan; su presencia se registra como advertencia. **Nunca se copia código, configuración o
    `.env` a una carpeta servida.**
20. **Ningún secreto se commitea.** Credenciales en `.env` (ignorado) o en
    `01-TOOLS/<proveedor>/.env`. En producción `APP_DEBUG=false` y sin `phpinfo` ni páginas de
    diagnóstico: cualquier archivo de diagnóstico se borra en la misma sesión.
21. **La auditoría es *append-only*.** Login, subida, descarga, validación, observación,
    aprobación, reapertura y carga quedan registrados; no se edita ni se borra desde la interfaz
    (RN-ENV-3).
22. **Datos REM = confidenciales.** El repositorio no contiene archivos reales, `.mdb`, `.sql` con
    datos, credenciales ni `.env`. Los logs no guardan contenido de celdas ni observaciones
    completas. Antes de producción hay revisión legal de privacidad y retención (Ley 21.719).

## 6. UX y accesibilidad

23. **Línea base WCAG 2.2 AA:** navegación por teclado, foco visible, etiquetas de formulario,
    contraste suficiente y mensajes asociados al campo. Usar AdminLTE o Bootstrap no exime.
24. **La interfaz siempre muestra el período y el establecimiento activos.** Se confirma solo lo
    irreversible (aprobar, reabrir, anular). Un encargado nuevo completa un envío con la guía en
    menos de 15 minutos.

## 7. Rendimiento

25. **Presupuestos medibles, no aspiraciones:** subida de un archivo de ~1,6 MB p95 < 5 s en red
    interna; validación de un archivo p95 < 90 s y < 512 MB de memoria; detalle de envío p95 <
    1,5 s; bandeja de 100 envíos p95 < 2 s; completitud de un Servicio de Salud p95 < 2 s. Máximo
    2 validaciones concurrentes con lock y espera de 10 s.

## 8. Conocimiento y decisiones

26. **Toda decisión significativa se registra** en `02-DOCS/wiki/sdd/decisions.md` con fecha,
    opciones consideradas y por qué. La constitución es el registro de mayor orden.
27. **Los documentos canónicos no se duplican.** `informe.md`, `stack_modulo_envio.md` y
    `backup_documentacion/MODULO_M7_PROFUNDIZADO.md` son la fuente; la wiki enlaza y sintetiza.
    Mapa: `02-DOCS/wiki/index.md`.
28. **En ASDD no se escribe código antes de la spec aprobada.** En cambios simples (FTD) basta el
    documento de feature con intención, alcance, checklist y evidencia.

## Definition of Done (la barra que `verify` ejecuta)

Un cambio se entrega solo si TODO se cumple:

- [ ] No introduce stack mientras D-STACK esté abierta (principio 1).
- [ ] Corre dentro de las restricciones confirmadas o la restricción cambió por decisión (2–5).
- [ ] Las migraciones corren en SQLite; si toca lectura de celdas o prepara producción, también en MySQL (10, 25).
- [ ] Cada regla, transición o permiso tocado tiene su test en verde (6–9).
- [ ] Si tocó lectura de celdas, hay comparación contra *golden files* (10).
- [ ] Identificadores, errores y commits siguen las convenciones (11–14).
- [ ] Va en rama, con PR, autoría humana (15–17).
- [ ] Aislamiento, archivos, secretos y auditoría cumplen (18–22).
- [ ] Accesibilidad y presupuestos de rendimiento se cumplen donde apliquen (23–25).
- [ ] La decisión quedó registrada (26–28).

## Amendment log (append-only)

| Date | Version | Change | Why |
|------|---------|--------|-----|
| 2026-11-06 | draft | Borrador generado por `rsc onboard`. | El piso SDD necesita constitución. |
| 2026-11-06 | v1.0.0 | Ratificada la constitución inicial con 28 principios. El stack queda explícitamente abierto (D-STACK). | Cierre de la fase `constitution`; el usuario aún no decide stack y pidió dejarlo pendiente. |
| 2026-11-06 | v1.0.0 (ratificación) | El usuario ratificó la v1.0.0 sin cambios (C-006). | Cierre formal de la fase `constitution`. |
| 2026-11-06 | v2.0.0 | **MAJOR.** Cierra D-STACK y agrega el canon de stack (§0). Actualiza los principios 1–3: se permite código de aplicación, se relaja la prohibición de cron a «solo tareas opcionales», y el despliegue admite SSH. Refuerza 19 y 20 con el incidente de credenciales del sistema de Observaciones. Actualiza la Definition of Done. Reemplaza v1.0.0. | Investigación de 5 opciones, evidencia de campo en `estadisticas.ssosorno.cl` y decisión explícita del usuario (C-007). |
| 2026-11-06 | **v3.0.0** | **MAJOR.** **Reabre D-STACK** y elimina el canon de stack (§0). La confirmación de PHP 7.4.3 invalida la decisión C-007. Los principios 1–3 vuelven a su forma restrictiva. Se registra C-009. Reemplaza v2.0.0. | Diagnóstico directo en `sist_envios` devuelve PHP 7.4.3. La evidencia de PHP 8.2 pertenecía a otro vhost. |
| 2026-11-10 | **v3.1.0** | **MAJOR.** **Cierra D-STACK (C-010)** con canon: Laravel 8.83 + PHP 7.4.3 + MySQL 8 + AdminLTE 3.2. Principio 1 ya no bloquea código de aplicación. Se registra C-010 en `decisions.md`. Reemplaza v3.0.0. | TI confirmó que no hay PHP-FPM 8.3 ni actualización de servidor; PHP 7.4.3 es la realidad del vhost principal. |
| 2026-11-11 | **v3.2.0** | **MAJOR.** **Reabre D-STACK (C-014)** y retira el canon cerrado. Principio 1 vuelve a bloquear código de aplicación. `portal/` queda provisional y congelado. Se pide PHP 8.1+ a TI para poder subir a Laravel 10.50.4+. Reemplaza v3.1.0. | `composer audit` sobre el proyecto real: Laravel 8.83.29 tiene 5 avisos abiertos, uno crítico, y no recibirá parches (EOL). La decisión C-010 no había evaluado ese costo. |
