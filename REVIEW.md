# REVIEW.md — qué mira el revisor en Cargas

> Ratificado con la constitución v1.0.0 (2026-11-06). Si este archivo contradice
> `02-DOCS/wiki/sdd/constitution.md`, manda la constitución. Se enmienda como ella: nunca en silencio.

## Qué es un fallo grave aquí

Un cambio no se aprueba si comete alguno de estos, aunque "funcione":

1. **Consulta sin alcance de establecimiento.** El filtro está solo en la vista o en el `where`
   del listado, no en la consulta que entrega o descarga el archivo (RN-ENV-1, principio 18).
2. **Estado escrito fuera del servicio de transición.** Un controlador, modelo o seeder escribe
   `estado`, o permite una transición que §5.4 declara prohibida (principio 7).
3. **Sobrescritura en vez de versión.** Un reenvío borra o reemplaza el archivo anterior en lugar
   de crear una versión nueva y marcar la anterior como `reemplazado` (P2, RN-ENV-8).
4. **Regla de validación sin código o con severidad equivocada.** Una regla bloqueante tratada
   como advertencia deja pasar datos malos (principio 6).
5. **Lectura de Excel que ejecuta macros** o que confía en la validación del navegador para decidir
   si el archivo es válido (P1, principio 19).
6. **Archivo servido desde `public/` o inline.** Debe salir por controlador autenticado, con
   `Content-Disposition: attachment` y `nosniff` (principio 19).
7. **SQL concatenado** o `{!! !!}` con datos de usuario; XSS o inyección evitable (principio 20).
8. **Secreto, `.env`, `.mdb`, `.sql` con datos o archivo REM real en el diff** (principio 22).
9. **Dependencia que exige un runtime fuera de las restricciones del servidor** mientras D-STACK
   esté abierta (principios 1–2).
10. **Funcionalidad que depende de cron, cola o SSH** para completarse (principio 3).
11. **Texto de interfaz o estado en inglés** cuando corresponde español (principio 11).

## Rutas que no se revisan

- `vendor/`, `node_modules/` y cualquier dependencia instalada.
- `storage/`, `bootstrap/cache/` y logs.
- `public/assets/vendor/` (AdminLTE, Bootstrap, Chart.js, DataTables) y `*.min.*`.
- `Flujos/*.png`, `docs/*.svg` y binarios: se revisa que existan, no su contenido.
- `composer.lock` / `package-lock.json`: se revisa que el cambio sea intencional, no línea por línea.

## Tope de nits

**Máximo 3 nits por revisión.** El resto se descarta. Un nit no bloquea la fusión; un fallo de la
lista de arriba sí.

## Rutas que exigen una persona

Nunca se fusionan sin decisión humana explícita, aunque los tests estén verdes:

- Identidad y autenticación (D1: fuente de identidad, 2FA, política de contraseñas).
- Políticas de autorización y matriz de permisos.
- Motor de validación V0–V11 y catálogo `ENV-###`.
- Migraciones de base de datos y su SQL de despliegue.
- `.env`, secretos, configuración de producción y respaldos.
- Cualquier archivo con datos reales o anonimización.
- El cierre de D-STACK (elección de stack).

> Hoy estas rutas son **informativas**: en OpenCode no hay hook que las bloquee. La aplicación
> automática depende de `rsc risk` y del sello del proyecto; si se activan, se reflejan aquí.

## Forma del resumen

Toda revisión empieza con el conteo, antes de cualquier detalle:

```text
3 hallazgos: 1 bloqueante, 2 nits
```

Después: hallazgo, principio o regla citada, evidencia (archivo:línea) y acción concreta.
