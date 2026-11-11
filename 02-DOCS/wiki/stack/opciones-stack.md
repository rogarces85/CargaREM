---
type: stack
title: Opciones de stack — investigación y decisión
description: Comparación de cinco opciones de stack para el portal M7, con relojes de soporte verificados, evidencia del servidor y decisión razonada.
tags: [stack, decision, laravel, symfony, codeigniter, slim, php, mysql]
timestamp: 2026-11-06T19:00:00Z
topic: stack
status: decided
---

# Opciones de stack — investigación y decisión

> **Decisión: Opción A.** Laravel 13 + PHP 8.3 (objetivo 8.5) + MySQL 8 + AdminLTE 4.
> Ratificada en `02-DOCS/wiki/sdd/decisions.md` (C-007) y en la constitución v2.0.0.
> Este artículo conserva la comparación completa para que la decisión sea auditable.

## 1. Cómo se evaluó

Cinco criterios ponderados, más una puerta de factibilidad previa:

| Criterio | Peso | Qué mide |
|---|---:|---|
| Soporte de seguridad | 20% | Años de parches garantizados para lenguaje y framework |
| Velocidad de entrega del M7 | 20% | Ecosistema disponible para auth, RBAC, Excel, tablas, auditoría |
| Encaje en el servidor | 15% | Si corre en la infraestructura real, no en una ideal |
| Mantenimiento anual | 15% | Costo de mantenerlo vivo cada año |
| Seguridad por defecto | 15% | Cuánto hay que construir a mano |
| Talento disponible | 15% | Quién lo mantiene en 12 meses |

**Puerta previa:** si una opción no puede desplegarse en el servidor, no se puntúa: queda descartada. Por eso el encaje pesa menos de lo que parece.

## 2. Relojes de soporte (verificado en nov-2026)

| Componente | Versión | Soporte de seguridad hasta | Estado |
|---|---|---|---|
| PHP | 7.4 | 28-nov-2022 | EOL |
| PHP | 8.1 | 31-dic-2025 | EOL |
| PHP | 8.2 | 31-dic-2026 | solo seguridad |
| PHP | 8.3 | 31-dic-2027 | solo seguridad |
| PHP | 8.4 | 31-dic-2028 | activo |
| PHP | 8.5 | 31-dic-2029 | activo |
| Laravel | 8.x | enero 2023 | EOL |
| Laravel | 12.x | 24-feb-2027 | soportado |
| Laravel | 13.x | ~mar-2028 | soportado (PHP 8.3–8.5) |
| Symfony | 7.4 LTS | nov-2029 | soportado (PHP 8.2+) |
| CodeIgniter | 4.7.x | sin política LTS | soportado (PHP 8.2+, 8.5 desde 4.7.0) |
| Slim | 4.15.x | sin política LTS | soportado |
| MySQL | 8.0 | abril 2026 | EOL |
| MySQL | 8.4 LTS | ~2032 | soportado |
| Ubuntu | 20.04 | may-2025 (2030 con ESM) | fuera de soporte estándar |
| Ubuntu | 24.04 LTS | 2029 | soportado (PHP 8.3) |
| Ubuntu | 26.04 LTS | ~2031 | soportado (PHP 8.5 + MySQL 8.4) |
| AdminLTE | 3.x (Bootstrap 4) | Bootstrap 4 EOL enero 2023 | EOL |
| AdminLTE | 4.10.x (Bootstrap 5.3.8) | activo | soportado |

Dos observaciones que cambian la conversación:

1. **PHP 8.2 se agota el 31-dic-2026.** Elegir 8.2 es elegir una migración en semanas.
2. **Ubuntu 26.04 LTS trae PHP 8.5 y MySQL 8.4 en sus repositorios.** Es el destino de actualización más limpio: sin PPAs ni compilaciones.

## 3. Evidencia del servidor

Verificado contra `https://estadisticas.ssosorno.cl/sist_observaciones/` el 2026-11-06.

| Señal | Valor | Lectura |
|---|---|---|
| HTTP | `200 OK` | Aplicación viva en un subdirectorio |
| `Server` | `Apache` (sin versión) | `ServerTokens Prod` aplicado |
| `X-Powered-By` | ausente | Ocultan la versión de PHP a propósito |
| `Set-Cookie` | `secure; HttpOnly; SameSite=Lax` | Configuración endurecida real |
| `X-Frame-Options` | `DENY` | El `.htaccess` de la aplicación se honra |
| Raíz `/` | Sitio DEIS con Bootstrap 4 | Convive con la aplicación nueva |
| Frontend | Tabler 1.4 + Bootstrap 5.3.7 | Un template Bootstrap 5 moderno ya funciona ahí |

Del código fuente de ese sistema, alojado en el mismo servidor:

| Evidencia | Archivo | Qué prueba |
|---|---|---|
| MySQL en `10.8.152.199`, `utf8mb4` | `config/.env.local.php` | Comparte el motor de base de datos del proyecto |
| `php8.2`, `libapache2-mod-php8.2`, `composer` | `deploy/setup-server.sh` | El despliegue real usa PHP 8.2 + Apache + Composer |
| `phpoffice/phpspreadsheet 5.4.0` (requiere `^8.1`) | `vendor/composer/installed.json` | Dependencia moderna instalada, no teórica |
| Worker cada minuto + timer systemd + `cron.daily` | `DEPLOY.md`, `OPERATIONS.md` | Hay cron disponible |
| Import de Excel de hasta 10 MB | `OPERATIONS.md` | El límite de subida es configurable, no estructural |

**Conclusión:** la premisa «el servidor solo soporta PHP 7.4» no se sostiene. La infraestructura
ejecuta PHP 8.2 para otro sistema. La versión exacta del vhost de Cargas no se pudo leer porque la
ocultan; se asume **8.3** y se confirma con el diagnóstico descrito en
[`restricciones-servidor.md`](restricciones-servidor.md).

## 4. Las cinco opciones

### A. Laravel 13 + PHP 8.3/8.5 + MySQL 8 + AdminLTE 4

| | |
|---|---|
| Dependencias | `spatie/laravel-permission` v8, Fortify, `yajra/laravel-datatables` v13 (opcional), PhpSpreadsheet 5.x |
| Ventaja | El diseño M7 §11 se traslada cambiando versiones, no arquitectura. Ecosistema completo |
| Costo | Actualización mayor de Laravel cada ~12 meses (normalmente menos de un día) |
| Esfuerzo | 14–20 semanas |

### B. Laravel 8.83 + PHP 7.4.3 + MySQL 8.0 + AdminLTE 3

Propuesta original del repositorio. Cero cambios de TI.

| | |
|---|---|
| Ventaja | Arranca de inmediato; el diseño ya está escrito para esto |
| Costo | Cuatro componentes EOL simultáneos; CVEs 2026 de PhpSpreadsheet en la rama 1.30; Bootstrap 4 muerto; sin parches del framework |
| Esfuerzo | 13–19 semanas **+ 6–10 de migración futura** |

Su único argumento era «es lo único que corre en el servidor». Ese argumento cayó con la evidencia
de la sección 3.

### C. Symfony 7.4 LTS + PHP 8.2+ + MySQL 8 + AdminLTE 4

| | |
|---|---|
| Ventaja | LTS hasta noviembre 2029: no se toca la versión mayor en toda la vida del proyecto |
| Costo | Más configuración inicial, menos baterías incluidas; exige experiencia previa; hay que reescribir §11 |
| Esfuerzo | 17–24 semanas |

La mejor opción si el criterio dominante fuera «cinco años sin actualizaciones».

### D. CodeIgniter 4.7 + PHP 8.2+ + MySQL 8 + AdminLTE 4

| | |
|---|---|
| Ventaja | Huella pequeña, curva baja, buen encaje con `mod_php` y servidores modestos |
| Costo | Auth, RBAC, auditoría y validación se construyen a mano; ecosistema menor; sin LTS |
| Esfuerzo | 15–21 semanas |

### E. Slim 4 + Twig + AdminLTE 4 + MySQL 8

| | |
|---|---|
| Ventaja | Control total, mínimo consumo |
| Costo | Se construye el framework: routing, DI, ORM, migraciones, auth, RBAC, CSRF, sesiones, tests |
| Esfuerzo | 20–30 semanas |

## 5. Matriz comparativa

| Opción | Servidor 15% | Soporte 20% | Entrega 20% | Manten. 15% | Seguridad 15% | Talento 15% | **Total** |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| **A. Laravel 13** | 1 | 5 | 5 | 4 | 5 | 5 | **4,25** |
| **C. Symfony 7.4 LTS** | 1 | 5 | 3 | 5 | 5 | 3 | **3,70** |
| **D. CodeIgniter 4.7** | 1 | 4 | 3 | 4 | 3 | 3 | **3,05** |
| **B. Laravel 8 / PHP 7.4** | 5 | 1 | 4 | 2 | 2 | 4 | **2,95** |
| **E. Slim 4** | 1 | 4 | 2 | 3 | 2 | 2 | **2,40** |

Las notas de «servidor» reflejan el estado anterior a la evidencia de campo. Con el servidor ya
ejecutando PHP 8.2, la opción B pierde su única ventaja y las demás suben.

## 6. ¿Es factible PHP + AdminLTE + MySQL?

**Sí, sin reservas, siempre que el PHP sea 8.3 o superior.**

1. **AdminLTE es agnóstico al framework.** Son assets estáticos: funcionan con Blade, Twig o PHP plano.
2. **Node solo se necesita para compilar.** El paquete publica `dist/` precompilado: se usa tal cual y no hay Node en producción.
3. **MySQL 8 cubre el modelo M7 de sobra.** InnoDB, `utf8mb4`, transacciones, claves foráneas e índices únicos: el DDL de §4.3 no requiere cambios.
4. **AdminLTE 4 es una reescritura sobre Bootstrap 5.3.8, sin jQuery.** Hay que adaptar el markup si el diseño asumía v3, pero DataTables, Select2, Flatpickr y SweetAlert2 tienen builds para Bootstrap 5.
5. **El límite de 2 MB no es del stack.** El diseño ya resuelve con un archivo por petición, y el límite es configurable.

Lo único que hacía inviable la idea era PHP 7.4, no AdminLTE ni MySQL.

## 7. Evidencia de campo: el sistema de Observaciones

En `https://estadisticas.ssosorno.cl/sist_observaciones/` corre, con datos reales, un sistema que es
exactamente la combinación propuesta:

| Pieza | Observaciones | M7 |
|---|---|---|
| Lenguaje | PHP | PHP |
| Base de datos | MySQL 8 (`utf8mb4`) | MySQL 8 |
| UI | Tabler 1.4 (Bootstrap 5.3.7) | AdminLTE 4 (Bootstrap 5.3.8) |
| Excel | PhpSpreadsheet 5.4 | PhpSpreadsheet 5.x |
| Despliegue | Subdirectorio + `.htaccess` | igual |

Ese sistema se construyó **sin framework**, con `index.php` como router, `api/*.php` como endpoints
JSON, `models/*.php` con PDO y CSRF propio. Funciona, pero su propio historial muestra el costo:

```
refactor: eliminar 14 metodos PHP sin ningun llamador
refactor: eliminar 19 funciones JavaScript sin ningun llamador
chore: eliminar migracion SQL duplicada
fix(seguridad): ocultar credenciales de prueba fuera de desarrollo
```

Cinco commits de limpieza y un fix de seguridad en los últimos ocho. Para M7, que exige un test por
regla V0–V11, por transición y por permiso, ese modelo se queda corto: el sistema de Observaciones
tiene dos archivos de test.

También deja una lección concreta: su módulo «Versionado» copiaba el código a `uploads/`, y
`uploads/versiones/v001/config/config.php` quedaba **descargable con las credenciales de la base de
datos**. Se retiró en agosto de 2026. Es exactamente lo que prohíben los principios 19 y 22.

## 8. Impacto en el diseño M7

| Documento | Antes | Ahora |
|---|---|---|
| §11.7 dependencias | `laravel/framework ^8.83` | `^13.0` |
| §11.7 | `spatie/laravel-permission ^5.11` | `^8.0` |
| §11.7 | `yajra/... ^9.21` | `^13.0` |
| §11.7 | `phpoffice/phpspreadsheet ^1.29` | `5.x` |
| §11.7 | AdminLTE 3.2 | AdminLTE 4.10 |
| Auth | Breeze / Jetstream | Fortify (Breeze y Jetstream ya no reciben desarrollo) |
| §16 estimaciones | 13–19 semanas | 14–20 |
| §4.3 DDL | MySQL 8.0 | compatible; objetivo 8.4 |

**Riesgo controlado:** PhpSpreadsheet 5.x no lee igual que 1.x en todos los casos. Como la
constitución ya exige paridad con *golden files* (principio 10), la diferencia se detecta y se
corrige con el procedimiento que ya está escrito.

## 9. Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Versión de PHP del vhost sin confirmar | Alto | Diagnóstico temporal (`restricciones-servidor.md`) |
| Sin SSH no hay `artisan` | Alto | Artefacto completo por FTP + ruta de migración protegida de un solo uso |
| MySQL 8.0 está EOL | Medio | Objetivo 8.4 LTS; DDL compatible con 8.0 mientras tanto |
| AdminLTE 4 cambia el markup respecto de v3 | Medio | Adaptación mecánica; plugins con build Bootstrap 5 |
| PhpSpreadsheet 5.x cambia la lectura | Medio | *Golden files*, tolerancia 0 |
| Dependencia de CDN para assets | Bajo | Autohospedar AdminLTE y Chart.js |
| `mod_php` con una sola versión de PHP | Bajo | Si hace falta convivir, PHP-FPM por vhost |

## 10. Esfuerzo y costo a tres años

| Opción | Construcción | Mantenimiento/año | Migración futura |
|---|---|---|---|
| **A** | 14–20 sem | 1–2 sem | no aplica en 3 años |
| B | 13–19 sem | 3–4 sem (parches a mano) | 6–10 sem |
| C | 17–24 sem | 0,5–1 sem | no aplica en 3 años |
| D | 15–21 sem | 1–2 sem | según la rama |
| E | 20–30 sem | 2–3 sem | según el caso |

B parece más barata al inicio y termina costando más, además de cargar el riesgo durante todo ese
tiempo.

## 11. Descartadas

- **Node / Next.js:** no hay runtime Node en el servidor, el despliegue es por FTP y el equipo es PHP. Es un cambio de plataforma, no de framework.
- **Laravel 8 / PHP 7.4:** ver opción B.
- **Access (`Global.mdb`) como almacén del portal:** no soporta escritura concurrente ni corre en Linux. Es fuente histórica, no destino.

## 12. Fuentes verificadas (nov-2026)

- `php.net/supported-versions.php` — fechas de soporte de PHP.
- `laravel.com/docs/releases` — política y versiones de Laravel.
- Documentación de Symfony, CodeIgniter y Slim.
- `ubuntu.com` — fin de soporte estándar de 20.04 y disponibilidad de PHP 8.5 en 26.04.
- Blog de Oracle MySQL — EOL de 8.0 en abril 2026.
- `github.com/ColorlibHQ/AdminLTE/releases` — v4.10.1 sobre Bootstrap 5.3.8.
- Packagist — `phpoffice/phpspreadsheet`, `spatie/laravel-permission`, `yajra/laravel-datatables`.
- Verificación HTTP directa sobre `estadisticas.ssosorno.cl`.
- Código fuente de `sist_observaciones` (`DEPLOY.md`, `OPERATIONS.md`, `vendor/composer/installed.json`).
