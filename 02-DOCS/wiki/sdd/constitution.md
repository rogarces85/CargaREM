---
type: constitution
title: Cargas — Constitution
description: Los principios no negociables que toda fase rsc-sdd obedece. Stack canon: Laravel 13 / PHP 8.3+ / MySQL 8 / AdminLTE 4.
tags: [sdd, constitution, cargas, rem]
timestamp: 2026-11-06T19:00:00Z
topic: sdd
version: v2.0.0
---

# Cargas — Constitution

> Version: v2.0.0 · Ratified: 2026-11-06 · Last amended: 2026-11-06
> Principios no negociables que toda fase rsc-sdd obedece. El detalle técnico vive en
> `02-DOCS/wiki/stack/` y `02-DOCS/wiki/dominio/`; este archivo ratifica el principio y enlaza el detalle.
>
> **Alcance.** Rige el portal M7 de envío REM por establecimiento y la documentación técnica de
> ValidadorRem. No rige el ejecutable WinForms existente, que se documenta pero no se modifica aquí.

## 0. Stack canon (D-STACK cerrada)

La decisión de stack **está tomada** el 2026-11-06 (C-007). Investigación completa en
[`stack/opciones-stack.md`](../stack/opciones-stack.md); cierre en
[`stack/decision-stack.md`](../stack/decision-stack.md).

| # | Regla | Cómo se verifica |
|---|---|---|
| C1 | **PHP 8.3 o superior.** Producción: la versión que confirme el diagnóstico del servidor; objetivo 8.5. Local: 8.3/8.4. | `PHP_VERSION` en el diagnóstico y `php -v` local |
| C2 | **Laravel 13.x** como único framework de aplicación. | `composer.json` con `laravel/framework ^13.0` |
| C3 | **MySQL 8 en producción** (8.0 hoy, objetivo 8.4 LTS). SQLite solo para desarrollo local y suite rápida. | `DB_CONNECTION` y `migrate:fresh` en ambos motores |
| C4 | **La paridad numérica y la concurrencia se validan en MySQL real**, nunca solo en SQLite ni MariaDB. | Suite de paridad contra `mysql:8.0` y `mysql:8.4` |
| C5 | **AdminLTE 4.x con `dist/` precompilado.** Sin Node ni pipeline de build en producción. | No existe `node_modules/` ni `vite` en el artefacto de despliegue |
| C6 | **Dependencias estructurales:** Fortify para auth, `spatie/laravel-permission` v8, PhpSpreadsheet 5.x. Cualquier otra se justifica en el PR. | Revisión del diff de `composer.json` |

**Supuesto pendiente de confirmar:** la versión de PHP del servidor se asume 8.3. Si el diagnóstico
devuelve 8.2, se cae a Laravel 12 y se registra la caducidad; si devuelve 7.4, **se reabre
D-STACK**. Procedimiento: [`stack/restricciones-servidor.md`](../stack/restricciones-servidor.md).

## 1. Stack y restricciones del entorno

1. **El stack es el canon de §0.** Ningún PR introduce otro framework, runtime, versión de lenguaje
   o dependencia estructural sin enmienda de la constitución. Se permite documentación, diagramas y
   prototipos descartables que no entren a `main`.
2. **Todo cambio corre dentro de las restricciones registradas** en
   `02-DOCS/wiki/stack/restricciones-servidor.md` (Apache, PHP ≥ 8.3 asumido, MySQL 8 `utf8mb4`,
   despliegue en subdirectorio). Si exige otra condición, primero se cambia la restricción con TI y
   se registra la decisión; no se asume que "el servidor se actualizará".
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

- [ ] Respeta el canon de stack (§0 y principio 1).
- [ ] Corre dentro de las restricciones registradas o la restricción cambió por decisión (2–5).
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
| 2026-11-06 | **v2.0.0** | **MAJOR.** Cierra D-STACK y agrega el canon de stack (§0). Actualiza los principios 1–3: se permite código de aplicación, se relaja la prohibición de cron a «solo tareas opcionales», y el despliegue admite SSH. Refuerza 19 y 20 con el incidente de credenciales del sistema de Observaciones. Actualiza la Definition of Done. Reemplaza v1.0.0. | Investigación de 5 opciones, evidencia de campo en `estadisticas.ssosorno.cl` y decisión explícita del usuario (C-007). |
