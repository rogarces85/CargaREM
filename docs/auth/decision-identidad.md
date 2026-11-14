# Identidad de usuarios

> **Estado:** vigente para el MVP v2.
> **Decisión:** D-001 (cuentas locales) y D-021 (sin segundo factor).
> **Fuente:** `02-DOCS/wiki/sdd/decisions.md` y `specs/MVP_V2.md`.

## 1. Cuentas

- Las crea un administrador desde `/admin/usuarios`.
- No hay registro público.
- Un usuario no se elimina: se desactiva.
- Un usuario desactivado no puede iniciar sesión, aunque su contraseña sea
  correcta.

## 2. Roles

| Rol | Alcance |
|---|---|
| `encargado` | Sus establecimientos asignados. |
| `revisor` | Todos los establecimientos del Servicio. |
| `admin` | Todo el sistema. |

No existe el rol `consulta`.

## 3. Contraseña

- Mínimo 12 caracteres al crearla o cambiarla.
- Se guarda con hash de Laravel.
- El administrador puede resetear la contraseña de otro usuario.
- El administrador no puede resetear su propia contraseña desde el listado ni
  quitarse el rol.
- La recuperación por correo usa un token de un solo uso.

## 4. Sesión

- Sesión de 8 horas.
- Cookie `httponly`, `samesite=lax` y `secure` en producción.
- El login tiene límite de intentos.

## 5. Fuera de alcance

- Segundo factor (TOTP o passkeys).
- Directorio institucional o SSO.
- Registro público.
- Verificación de correo.
