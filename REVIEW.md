# REVIEW.md — qué mira el revisor en Cargas

> Ratificado con la constitución v1.0.0 (2026-11-06); revisado contra la **v6.0.0** (2026-11-26).
> Si este archivo contradice `02-DOCS/wiki/sdd/constitution.md`, manda la constitución. Se enmienda
> como ella: nunca en silencio.

## Qué es un fallo grave aquí

Un cambio no se aprueba si comete alguno de estos, aunque "funcione":

1. **Consulta sin alcance de establecimiento.** El filtro está solo en la vista o en el `where`
   del listado, no en la consulta que entrega o descarga el archivo. El Equipo REM solo ve sus
   establecimientos asignados; sin asignación, **ninguno**; el encargado, los suyos
   (R-ALC-01, `App\Dominio\Alcance`).
2. **Alcance que cae a «todos» cuando falta el dato.** Un revisor sin asignación que ve el
   Servicio completo es el error más caro de esta versión: no falla, filtra de más y nadie lo nota.
3. **Estado escrito fuera del servicio de transición.** Un controlador, modelo o seeder escribe
   `estado`, o permite una transición que §8 de `specs/MVP_V3.md` declara prohibida
   (principio 7). `aprobado` **no es reversible por la puerta de atrás**: se reabre solo con
   `EstadoEnvioService::reabrir()` y su motivo. Agregar `aprobado → observado` a `PERMITIDAS`
   es un fallo grave.
4. **Reapertura, apertura o anulación sin motivo.** Las tres exigen un texto que explique la
   decisión y las tres dejan rastro en `auditoria` (principio 21).
5. **Apertura de plazo que cambia el estado del período.** La ventana habilita a un
   establecimiento; no reabre el período para todos ni lo marca como abierto (D-044).
6. **Módulo Validador que escribe en el flujo.** Si toca `envios`, `envio_archivos`,
   `validaciones` o `envio_transiciones`, dejó de ser una ayuda y pasó a ser una etapa sin
   especificación (principio 32).
7. **Tipo de regla del Validador ignorado en silencio.** Un tipo desconocido tiene que abortar la
   ejecución con su identificador: un resultado limpio sobre reglas que no corrieron es peor que
   un error (R-VAL-02).
8. **Sobrescritura en vez de versión.** Un reenvío borra o reemplaza el archivo anterior en lugar
   de crear una versión nueva y marcar la anterior como `reemplazado` (P2, RN-ENV-8).
9. **Regla de validación sin código o con severidad equivocada.** Una regla bloqueante tratada
   como advertencia deja pasar datos malos (principio 6).
10. **Lectura de Excel que ejecuta macros** o que confía en la validación del navegador para decidir
    si el archivo es válido (P1, principio 19).
11. **Archivo servido desde `public/` o inline.** Debe salir por controlador autenticado, con
    `Content-Disposition: attachment` y `nosniff` (principio 19). Aplica también al archivo que se
    sube al Validador.
12. **SQL concatenado** o `{!! !!}` con datos de usuario; XSS o inyección evitable (principio 20).
13. **Secreto, `.env`, `.mdb`, `.sql` con datos o archivo REM real en el diff** (principio 22).
14. **Dependencia que exige un runtime fuera de las restricciones del servidor** (principios 1–2).
15. **Funcionalidad que depende de cron, cola o SSH** para completarse (principio 3). El calendario
    se genera al pedir un período que no existe, no cuando corre la tarea programada.
16. **Texto de interfaz o estado en inglés** cuando corresponde español (principio 11).
17. **Clase de Tailwind construida por interpolación** (`bg-{{ $estado }}`): el compilador no la
    genera y el elemento sale sin estilo, en silencio (principios 29–31).
18. **Gráfico como única fuente del dato.** Si la cifra no está también en texto o tabla, quien no
    ve el gráfico —o quien navega sin JavaScript— se queda sin la información (principio 30).
19. **Cambio de UI sin recompilar `public/build/`**: el servidor no tiene Node y sirve el artefacto
    anterior (principio 31).

## Rutas que no se revisan

- `vendor/`, `node_modules/` y cualquier dependencia instalada.
- `storage/`, `bootstrap/cache/` y logs.
- `portal/public/build/` (artefacto compilado por Vite: se revisa que se haya recompilado, no su
  contenido) y `*.min.*`.
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
