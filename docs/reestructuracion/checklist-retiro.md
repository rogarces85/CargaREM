# Checklist de retiro — MVP v2

> **Rama:** `feature/mvp-v2-reestructuracion`
> **Especificación:** `specs/MVP_V2.md`
> **Regla:** lo que no está en el MVP se elimina de la superficie activa. Los
> documentos históricos se archivan y se marcan; no se borran sin decisión.

---

## 0. Preparación

- [x] Crear rama `feature/mvp-v2-reestructuracion`.
- [x] Respaldar base y archivos en `backup_mvp_v2/`.
- [x] Correr suite base y registrar conteo: **246 tests, 646 aserciones, 0 fallos**.
- [ ] Confirmar que `main` queda intacta.

---

## 1. Paquete, manifiesto y HMAC

- [ ] Borrar `portal/app/Http/Controllers/PaqueteController.php`.
- [ ] Borrar `portal/app/Services/PaqueteService.php`.
- [ ] Borrar `portal/tests/Feature/PaqueteTest.php`.
- [ ] Quitar ruta `envios.paquete` de `portal/routes/web.php`.
- [ ] Quitar `paquete()` de `portal/app/Policies/EnvioPolicy.php`.
- [ ] Quitar `paqueteNoDisponible()` de `portal/app/Exceptions/EnvioInvalidoException.php`.
- [ ] Quitar bloque `paquete` de `portal/config/rem.php`.
- [ ] Quitar `PAQUETE_HMAC_KEY` de `portal/.env.example`.
- [ ] Quitar chequeo HMAC de `portal/app/Console/Commands/Endurecer.php`.
- [ ] Quitar botón y enlace de paquete de `portal/resources/views/envios/show.blade.php`.
- [ ] Quitar prueba de throttle de paquete de `portal/tests/Feature/SeguridadTest.php`.
- [ ] Renombrar `catalogo_snapshots.hash_paquete` a `hash_catalogo`.

---

## 2. Confirmar carga

- [ ] Quitar `Envio::CARGADO`.
- [ ] Quitar `EnvioArchivo::CARGADO`.
- [ ] Quitar `fecha_carga` de `Envio` y de la migración.
- [ ] Quitar `confirmarCarga()` de `EstadoEnvioService`.
- [ ] Quitar transición a `cargado`.
- [ ] Quitar acción `cargar` de `EnvioController`.
- [ ] Quitar método `cargar` de `RevisionController`.
- [ ] Quitar ruta `revision.cargar`.
- [ ] Quitar permiso `envios.cargar`.
- [ ] Quitar modal y botón de carga de `envios/show` y `revision/index`.
- [ ] Quitar aviso `cargado` de `EnvioNotificacion` y `mail/envio`.
- [ ] Adaptar `CompletitudService` para que `aprobado` sea el estado final.
- [ ] Adaptar `SimularMes` para terminar en `aprobado`.
- [ ] Quitar pruebas de carga de `CargaYReaperturaTest`, `EstadoEnvioTest`, `NotificacionTest`, `SeguridadTest`.

---

## 3. 2FA

- [ ] Quitar `Features::twoFactorAuthentication` de `portal/config/fortify.php`.
- [ ] Quitar rutas `two-factor` de `config/fortify.php`.
- [ ] Quitar bloque `passkeys` si no se usa.
- [ ] Quitar `Fortify::twoFactorChallengeView` de `FortifyServiceProvider`.
- [ ] Borrar `portal/resources/views/auth/two-factor-challenge.blade.php`.
- [ ] Quitar `two_factor_secret` y `two_factor_recovery_codes` de `User::$hidden`.
- [ ] Archivar `docs/auth/decision-identidad-2fa.md`.
- [ ] Quitar 2FA de `docs/decisiones/pendientes-de-firma.md`.
- [ ] Quitar 2FA de `REVIEW.md`, README y wiki.

---

## 4. Rol consulta

- [ ] Quitar `consulta` de `RolePermissionSeeder::MATRIZ`.
- [ ] Quitar `consulta` de `RolePermissionSeeder::ROLES`.
- [ ] Quitar ramas de `consulta` en `Envio::scopeForUser`.
- [ ] Quitar ramas de `consulta` en `CompletitudService` y `CompletitudController`.
- [ ] Quitar `consulta` de `envios.ver.ss`, `archivos.descargar`,
      `reportes.descargar` y `completitud.ver`.
- [ ] Actualizar `portal/tests/Parity/fixtures/rbac-matrix.json` a 16 × 3.
- [ ] Actualizar `AuthTest`, `CompletitudTest` y pruebas de permisos.
- [ ] Eliminar el rol de la base al sembrar.

---

## 5. Multi-servicio

- [ ] Borrar `portal/app/Models/Servicio.php`.
- [ ] Borrar `portal/app/Http/Controllers/Admin/ServicioController.php`.
- [ ] Quitar recurso `admin/servicios` de `routes/web.php`.
- [ ] Quitar entrada «Servicios» de `layouts/panel`.
- [ ] Quitar columna «Servicio» de `resources/views/admin/matriz/index.blade.php`.
- [ ] Quitar `servicio_id` de `Establecimiento` y `User`.
- [ ] Quitar `servicio()` de `Establecimiento` y `User`.
- [ ] Quitar `servicio_id` de `Admin\EstablecimientoController`.
- [ ] Quitar `opcionesServicios()` de `Admin\CatalogoController`.
- [ ] Quitar tabla `servicios` y columnas `servicio_id` de la migración de limpieza.
- [ ] Adaptar `CatalogoService` para filtrar por `REM_SERVICIO_CODIGO=23`.
- [ ] Adaptar `ReglaV4Pertenencia` a asignación de usuario.
- [ ] Adaptar `Notificador` a revisores por rol.
- [ ] Adaptar `CompletitudService` y `CompletitudController`.
- [ ] Adaptar `Envio::scopeForUser`.
- [ ] Adaptar `L1MinimumSeeder`.
- [ ] Adaptar `ParidadService`, `ProbarMotor`, `SimularMes`.
- [ ] Actualizar todos los fixtures de pruebas que crean `Servicio`.

---

## 6. Plazo

- [ ] Agregar `envios.fecha_limite` (DATE, nullable).
- [ ] Agregar `envios.fuera_de_plazo` (BOOLEAN, nullable).
- [ ] Copiar `fecha_limite` al crear el envío.
- [ ] Calcular `fuera_de_plazo` en la primera transición a `en_revision`.
- [ ] Bloquear subidas en período `cerrado`.
- [ ] Permitir en período `en_revision` solo correcciones de `observado` o admin.
- [ ] Mostrar «Fuera de plazo» en envíos, detalle, revisión y completitud.
- [ ] Agregar filtro de atraso en revisión.
- [ ] Agregar columna de atraso en exportación de completitud.
- [ ] Agregar `PlazoTest`.

---

## 7. Aprobación completa

- [ ] Mantener guardas de `aprobar()`.
- [ ] Marcar archivos vigentes como `aprobado`.
- [ ] Al reabrir, volver archivos a `valido`.
- [ ] Reabrir solo desde `aprobado`.
- [ ] Botón «Aprobar estadística».
- [ ] Pruebas de archivos aprobados y reapertura.

---

## 8. Vistas

- [ ] `envios/index`: quitar `cargado`; agregar atraso.
- [ ] `envios/show`: quitar paquete y carga; agregar plazo y atraso.
- [ ] `revision/index`: quitar carga; agregar atraso.
- [ ] `completitud/index`: agregar atraso.
- [ ] `admin/establecimientos`: quitar servicio.
- [ ] `admin/matriz/index`: quitar servicio.
- [ ] `layouts/panel`: quitar Servicios.
- [ ] `panel`: quitar «F1 en curso».
- [ ] Borrar `welcome.blade.php`; redirigir `/`.

---

## 9. Documentación

- [ ] `specs/MVP_V2.md` creado.
- [ ] `docs/reestructuracion/matriz-funcionalidades.md` creado.
- [ ] Archivar `specs/AGENT.md`, `SPECS.md`, `PLAN.md`, `TASK.md`.
- [ ] Banner en `backup_documentacion/MODULO_M7_PROFUNDIZADO.md`.
- [ ] Banner en `docs/FLUJO_DATOS_INTEGRAL_SISTEMA.md`.
- [ ] Actualizar `README.md`.
- [ ] Actualizar `REVIEW.md`.
- [ ] Actualizar `02-DOCS/wiki/sdd/decisions.md`.
- [ ] Actualizar `02-DOCS/wiki/sdd/constitution.md`.
- [ ] Actualizar `02-DOCS/wiki/index.md` y `log.md`.
- [ ] Actualizar `docs/decisiones/pendientes-de-firma.md`.
- [ ] Actualizar `docs/validacion/reglas-cierre-periodo.md`.
- [ ] Actualizar `docs/validacion/catalogo-errores-env.md` (ENV-015).
- [ ] Actualizar `docs/validacion/hoja-firma-severidad.md`.
- [ ] Actualizar `docs/entrega/estado-del-proyecto.md`.
- [ ] Actualizar `docs/manual/manual-usuario.md` y `manual-operacion.md`.
- [ ] Actualizar `docs/operacion/como-probar.md`.
- [ ] Actualizar `docs/uat/guion-uat.md`.
- [ ] Mover infografías del portal anterior a histórico.
- [ ] Pasar barrido de referencias activas.

---

## 10. Verificación

- [ ] `php artisan migrate:fresh --seed`.
- [ ] `php artisan test`.
- [ ] `php artisan rem:endurecer`.
- [ ] `iniciar.bat` abre el portal.
- [ ] `probar-archivos.bat` valida archivos de prueba.
- [ ] Flujo manual encargado → revisor.
- [ ] README con conteos reales.
- [ ] `git diff` revisado.
- [ ] Commits en la rama.
