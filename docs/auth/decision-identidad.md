# Identidad de usuarios

> **Estado:** vigente para el MVP v2 (D-030, 2026-11-14).
> **Fuente:** `02-DOCS/wiki/sdd/decisions.md` y `specs/MVP_V2.md` §5.

## 1. Cuentas

- Las crea un administrador desde `/admin/usuarios`.
- No hay registro público ni autoservicio de alta.
- El **correo lo fija el administrador** al crear la cuenta. Basta con que sea
  un correo válido: no se exige un dominio institucional.
- El usuario no puede cambiar su correo desde **Mi cuenta**; se lo pide al
  administrador.
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

- Mínimo **12 caracteres** al crearla o cambiarla.
- Se guarda con hash de Laravel.
- **El administrador restablece la contraseña de cualquier usuario** desde el
  formulario de edición de la cuenta.
- El administrador **no** puede restablecer su propia contraseña desde el
  listado ni quitarse el rol (R-ID-05): la cambia en **Mi cuenta**.
- Cada usuario cambia la suya en **Mi cuenta** (`/perfil`), con la actual como
  comprobación.
- **No hay recuperación por correo:** no se envía enlace ni token. Si alguien
  olvida la clave, se la restablece el administrador.

## 4. Sesión

- Sesión de 8 horas.
- Cookie `httponly`, `samesite=lax` y `secure` en producción.
- El login tiene límite de intentos.

## 5. Fuera de alcance

- Recuperación de contraseña por correo.
- Segundo factor (TOTP o passkeys). v2.1.
- Directorio institucional o SSO.
- Registro público.
- Verificación de correo.
