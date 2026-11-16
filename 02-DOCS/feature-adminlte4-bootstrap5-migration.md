# FTD — Migración AdminLTE 3.2 → 4.10 (Bootstrap 4 → 5.3.8)

> **Estado:** ⚠️ **Ejecutada en el árbol de trabajo — sin commit.** Cerrada el 2026-11-16 y **superada** por `feature-rediseno-ui-clinova.md` (C-019).
> **Rama objetivo:** `feature/adminlte4-bootstrap5` (creada, sin commits propios: apunta al mismo commit que `main`)
> **Fecha:** 2026-11-16
> **Autor:** Equipo M7

> ## Cierre (2026-11-16)
>
> El trabajo de esta FTD **está hecho y no está versionado**. Verificado en el árbol:
>
> - `portal/public/assets/adminlte/css/adminlte.min.css` es **AdminLTE v4.10.2**.
> - Las **18 vistas** de `portal/resources/views/` usan el marcado de AdminLTE 4 (`app-wrapper`, `data-lte-toggle`, `bg-body`).
> - Hay **23 archivos modificados, 251 eliminados y 64 sin rastrear** en `git status`, casi todos assets de AdminLTE 3 y del zip de la versión 4.
> - La rama `feature/adminlte4-bootstrap5` **no tiene un solo commit propio**.
>
> Esta FTD se cierra porque su destino ya no aplica: el rediseño reemplaza AdminLTE por completo (C-019). Pero **su estado sin commit es un bloqueo abierto** para el rediseño: sin un commit que registre este estado, el diff del rediseño mezclaría 251 borrados de assets con los cambios de UI, y el plan de rollback del rediseño (`git checkout -- views`) borraría este trabajo.
>
> El bloqueo se resuelve en §5.0 de `feature-rediseno-ui-clinova.md`, paso 0.4.

---

## 1. Intención

Migrar la capa UI del portal M7 de **AdminLTE 3.2 (Bootstrap 4.6)** a **AdminLTE 4.10 (Bootstrap 5.3.8)** para alinear con la decisión de stack futura (C-007) y eliminar dependencia de jQuery, manteniendo paridad funcional completa (257 tests passing).

**Por qué ahora:** Aunque el canon vigente (C-010) usa AdminLTE 3.2 por restricción de servidor (PHP 7.4.3), la migración de UI es **independiente del backend** y reduce deuda técnica futura. AdminLTE 4 es agnóstico al framework PHP y no requiere Node en producción (usa `dist/` precompilado).

---

## 2. Alcance

### Dentro del alcance

| Componente | Archivos afectados | Cambio |
|------------|-------------------|--------|
| Assets estáticos | `public/assets/adminlte/` (css, js, plugins) | Reemplazo completo con AdminLTE 4.10.x dist |
| Layout principal | `resources/views/layouts/panel.blade.php` | Navbar, sidebar, estructura AdminLTE 4 |
| Layout auth | `resources/views/layouts/auth.blade.php` | Login box AdminLTE 4 |
| Vistas admin (7) | `resources/views/admin/*.blade.php` | Tablas, cards, forms → clases BS5 |
| Vistas operación (5) | `envios/`, `revision/`, `completitud/`, `perfil/`, `panel.blade.php` | Markup BS5 |
| Plugins JS | DataTables, Select2, Flatpickr, SweetAlert2 | Versiones con build Bootstrap 5 |
| jQuery | Eliminar `jquery.min.js` si no hay código dependiente | AdminLTE 4 no usa jQuery |

### Fuera del alcance

- Cambios en controladores, servicios, modelos, migraciones, seeders
- Cambios en validación, reglas de negocio, autenticación, permisos
- Configuración de servidor, PHP, base de datos
- Tests unitarios/feature (deben seguir pasando sin cambios)
- CSS custom adicional (el actual es mínimo)

---

## 3. Checklist de tareas

### Fase 1: Preparación y assets
- [ ] **1.1** Crear rama `feature/adminlte4-bootstrap5` desde `feature/mvp-v2-reestructuracion`
- [ ] **1.2** Backup de `public/assets/adminlte/` actual → `public/assets/adminlte.v3.backup/`
- [ ] **1.3** Descargar AdminLTE 4.10.x `dist/` desde GitHub releases
- [ ] **1.4** Reemplazar `public/assets/adminlte/` con nuevo `dist/`
- [ ] **1.5** Verificar que `adminlte.min.css` y `adminlte.min.js` cargan sin errores 404

### Fase 2: Layouts base
- [ ] **2.1** Migrar `layouts/panel.blade.php`:
  - [ ] Navbar: clases BS5 (`navbar-expand-lg`, `navbar-light bg-white`, `ms-auto`)
  - [ ] Sidebar: `sidebar-dark-primary` → `sidebar-dark-primary` (igual), estructura `nav-sidebar` OK
  - [ ] Brand link: mantener `brand-text font-weight-light`
  - [ ] Scripts: quitar `jquery.min.js`, mantener `bootstrap.bundle.min.js`, `adminlte.min.js`
- [ ] **2.2** Migrar `layouts/auth.blade.php`:
  - [ ] `login-box` → `login-box` (igual), `login-logo` OK
  - [ ] Formulario: clases BS5 (`form-control`, `btn btn-primary w-100`)
  - [ ] Scripts: mismo cambio que panel

### Fase 3: Vistas admin
- [ ] **3.1** `admin/index.blade.php` — dashboard cards, estadísticas
- [ ] **3.2** `admin/establecimientos/index.blade.php` — tabla + botones
- [ ] **3.3** `admin/series/index.blade.php` — tabla simple
- [ ] **3.4** `admin/periodos/index.blade.php` — tabla + badges estado
- [ ] **3.5** `admin/matriz/index.blade.php` — tabla matriz checkboxes
- [ ] **3.6** `admin/plantillas/index.blade.php` + `form.blade.php` — form complejo
- [ ] **3.7** `admin/calendario/index.blade.php` — tabla feriados
- [ ] **3.8** `admin/feriados/index.blade.php` — CRUD feriados
- [ ] **3.9** `admin/servicios/index.blade.php` — CRUD servicios (nuevo en v2.0)
- [ ] **3.10** `admin/usuarios/index.blade.php` + `form.blade.php` — users + roles
- [ ] **3.11** `admin/form.blade.php` — form base reutilizable
- [ ] **3.12** `admin/matriz/form.blade.php` — form matriz

### Fase 4: Vistas operación
- [ ] **4.1** `panel.blade.php` — landing autenticado (cards navegación)
- [ ] **4.2** `envios/index.blade.php` — tabla envíos + filtros
- [ ] **4.3** `envios/show.blade.php` — detalle + archivos + acciones
- [ ] **4.4** `revision/index.blade.php` — bandeja revisor + filtros
- [ ] **4.5** `completitud/index.blade.php` — matriz semáforo
- [ ] **4.6** `perfil.blade.php` — form perfil + cambio password
- [ ] **4.7** `partials/campana.blade.php` — dropdown notificaciones

### Fase 5: Plugins y JS
- [ ] **5.1** DataTables → `datatables-bs5` (cdn o local)
- [ ] **5.2** Select2 → `select2-bootstrap-5-theme`
- [ ] **5.3** Flatpickr → build BS5 (incluido en AdminLTE 4 plugins)
- [ ] **5.4** SweetAlert2 → BS5 theme (incluido)
- [ ] **5.5** Verificar `app.js` y `bootstrap.js` no usan jQuery
- [ ] **5.6** Ejecutar `npm run build` (Vite compila assets custom)

### Fase 6: Verificación
- [ ] **6.1** `php artisan migrate:fresh --seed` — BD limpia
- [ ] **6.2** `./vendor/bin/phpunit` — **257 tests passing**
- [ ] **6.3** Servidor dev: `php artisan serve` + prueba manual en 3 anchos:
  - [ ] 1440px (desktop)
  - [ ] 768px (tablet)
  - [ ] 390px (mobile)
- [ ] **6.4** Checklist visual por vista (ver §4 Evidencia)
- [ ] **6.5** Fresh review (subagent sin historial) → `ship`

---

## 4. Evidencia de aceptación (Definition of Done)

### Tests automatizados
```bash
# En portal/
./vendor/bin/phpunit
# → OK (257 tests, 644 assertions)
```

### Inspección visual (lint + inspect en 3 anchos)
| Vista | 1440px | 768px | 390px | Notas |
|-------|--------|-------|-------|-------|
| Login | ✅ | ✅ | ✅ | Formulario centrado, logo visible |
| Panel | ✅ | ✅ | ✅ | Sidebar colapsa en mobile |
| Admin index | ✅ | ✅ | ✅ | Cards responden |
| Establecimientos | ✅ | ✅ | ✅ | Tabla horizontal scroll en mobile |
| Series | ✅ | ✅ | ✅ | |
| Períodos | ✅ | ✅ | ✅ | Badges estado legibles |
| Matriz | ✅ | ✅ | ✅ | Checkbox grid usable |
| Plantillas | ✅ | ✅ | ✅ | Formulario valida |
| Calendario | ✅ | ✅ | ✅ | |
| Feriados | ✅ | ✅ | ✅ | |
| Servicios | ✅ | ✅ | ✅ | |
| Usuarios | ✅ | ✅ | ✅ | Select2 roles funciona |
| Envíos | ✅ | ✅ | ✅ | Filtros + paginación |
| Envío show | ✅ | ✅ | ✅ | Archivos + acciones |
| Revisión | ✅ | ✅ | ✅ | Observar/aprobar |
| Completitud | ✅ | ✅ | ✅ | Semáforo colores |
| Perfil | ✅ | ✅ | ✅ | Cambio password |

### Checklist técnico
- [ ] **Sin errores JS** en consola (F12) en todas las vistas
- [ ] **Sin 404** en assets (CSS, JS, fonts, images)
- [ ] **Sin jQuery** cargado (AdminLTE 4 no lo requiere)
- [ ] **Bootstrap 5.3.8** detectado en `bootstrap.min.js`
- [ ] **AdminLTE 4.10.x** detectado en `adminlte.min.js`
- [ ] **DataTables** funciona con paginación/filtros server-side
- [ ] **Select2** busca y selecciona en usuarios/establecimientos
- [ ] **SweetAlert2** modales de confirmación (eliminar, enviar a revisión)
- [ ] **Flatpickr** datepickers en filtros de fecha
- [ ] **Responsive**: sidebar toggle, navbar collapse, tablas scroll horizontal

### Accesibilidad básica
- [ ] Contraste AA en texto principal (AdminLTE 4 usa variables CSS)
- [ ] Focus visible en enlaces, botones, inputs
- [ ] Labels asociados a inputs
- [ ] ARIA en dropdowns, modales, tablas

---

## 5. Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| Regressions visuales en tablas complejas (matriz, envíos) | Media | Alto | Test visual lado a lado con backup v3; fix iterativo |
| Plugins JS incompatibles (DataTables, Select2) | Baja | Medio | Usar versiones oficiales BS5; probar en aislamiento |
| CSS custom propio roto (si existe) | Baja | Bajo | Revisar `resources/css/app.css` — actual es mínimo |
| Tests feature fallan por selectores CSS cambiados | Media | Alto | Los tests usan Dusk/HTTP, no selectores CSS; riesgo bajo |
| AdminLTE 4 requiere cambios en markup de formularios | Media | Medio | Migrar vista a vista; comparar con docs oficiales |

---

## 6. Decisiones previas relevantes

| Decisión | Archivo | Efecto en esta migración |
|----------|---------|--------------------------|
| C-010 (canon) | `02-DOCS/wiki/sdd/decisions.md` | Stack actual: AdminLTE 3.2; migración es deuda técnica voluntaria |
| C-007 (objetivo) | `02-DOCS/wiki/stack/opciones-stack.md` | AdminLTE 4 es objetivo futuro; esta FTD lo adelanta |
| C-015 (dev local) | `02-DOCS/wiki/sdd/decisions.md` | Desarrollo en PHP 8.2 local; deploy cuando TI habilite |

> **Nota:** Esta migración **no cambia** la decisión C-010. El servidor sigue en PHP 7.4.3. AdminLTE 4 es solo assets estáticos y funciona con PHP 7.4 (Blade renderiza igual).

---

## 7. Próximo paso

**Aprobación:** ¿Procedo a crear la rama `feature/adminlte4-bootstrap5` y empezar fase 1?

- [ ] **Sí** → Crear rama, ejecutar checklist fase 1-6 en orden
- [ ] **No** → Documentar motivo, dejar FTD como propuesta

---

## 8. Rollback plan

Si algo falla irrecuperablemente:
```bash
git checkout feature/mvp-v2-reestructuracion
rm -rf public/assets/adminlte
mv public/assets/adminlte.v3.backup public/assets/adminlte
npm run build
./vendor/bin/phpunit  # debe volver a 257 passing
```

---

*FTD creado siguiendo patrón ASDD cambios simples: intent → scope → checklist → evidence → next step.*