# Levantar el servidor

> Cómo dejar el portal funcionando en esta máquina, de cero.
> Verificado el **2026-11-19** con el portal respondiendo: PHP 8.2.12, MariaDB 10.4.32,
> Laravel 12.72.

---

## 1. La forma corta

En la raíz del repositorio hay un archivo:

```
C:\xampp\htdocs\www\Cargas\iniciar.bat
```

Doble clic. Hace todo solo y abre el navegador:

1. Comprueba que PHP sea 8.2 o superior.
2. Comprueba que MySQL responda.
3. Instala las dependencias si faltan.
4. Crea el `.env` si no existe y genera la clave.
5. Crea la base y aplica las migraciones.
6. Siembra los roles, permisos y usuarios si faltan.
7. Detecta si Apache ya sirve el portal. Si sí, abre esa dirección; si no, levanta
   el servidor de desarrollo.

Es seguro ejecutarlo varias veces: no repite lo que ya está hecho.

---

## 2. La forma manual, paso a paso

### Paso 1 — Encender XAMPP

Abrir el **Panel de control de XAMPP** y pulsar **Start** en dos servicios:

| Servicio | Para qué |
|---|---|
| **Apache** | Sirve el portal por HTTP |
| **MySQL** | Es la base de datos |

Los dos tienen que decir *Running* en verde.

### Paso 2 — Comprobar que la base responde

Abrir en el navegador:

```
http://localhost/Cargas/portal/public/health
```

Tiene que devolver esto:

```json
{"status":"ok","checks":{"db":"ok","storage":"ok"},"entorno":"local","version":"12.72.0","hora":"..."}
```

Si dice `"db":"error"`, MySQL está apagado o las credenciales del `.env` no coinciden.

### Paso 3 — Abrir el portal

```
http://localhost/Cargas/portal/public/login
```

Aparece la pantalla de entrada.

> **La dirección larga no es un error.** El Apache de esta máquina tiene su raíz en
> `C:/xampp/htdocs/www`, no en `htdocs`, así que el portal se sirve por ruta. Esta es
> además la forma que se parece a producción, donde también hay un subdirectorio.

---

## 3. La otra forma: servidor de desarrollo

Si no quieres tocar Apache, o necesitas ver cambios sin caché:

```bat
cd C:\xampp\htdocs\www\Cargas\portal
php artisan serve
```

Abre en:

```
http://127.0.0.1:8000/login
```

**Mientras uses esta opción, la ventana de la consola tiene que quedar abierta.** Si la
cierras, el servidor se detiene y la página deja de responder. Es la causa más común de
«no me abre el login».

Para detenerlo: `Ctrl + C` en esa ventana.

| | Apache | `php artisan serve` |
|---|---|---|
| Dirección | `http://localhost/Cargas/portal/public` | `http://127.0.0.1:8000` |
| ¿Hay que dejar una ventana abierta? | No: corre como servicio | Sí |
| Se parece a producción | Sí (subdirectorio, `mod_php`) | No (raíz limpia) |
| Recomendado para | **Mostrar el sistema** | Desarrollar |

Las direcciones generadas se adaptan solas a la forma en que entraste. No hay que
cambiar el `.env` para pasar de una a otra.

---

## 4. Entrar al sistema

| Rol | Usuario | Clave |
|---|---|---|
| Administrador | `admin@ssosorno.cl` | `Portal2026!` |
| Revisor | `revisor@ssosorno.cl` | `Portal2026!` |
| Encargado — Hospital Base | `encargado.hospital@ssosorno.cl` | `Portal2026!` |
| Encargado — Postas Rurales | `encargado.postas@ssosorno.cl` | `Portal2026!` |

El signo de exclamación final forma parte de la clave.

Son usuarios **de desarrollo**: los crea el seeder y están en el repositorio a propósito
para poder probar. En cualquier entorno compartido se cambian.

---

## 5. Si la base está vacía: datos de demostración

Una base recién sembrada tiene catálogos y usuarios, pero **ningún envío**. Las pantallas
se ven vacías y el gráfico de completitud queda todo gris.

Para dejar el período **10/2026** con actividad repartida por los cinco estados:

```bat
cd C:\xampp\htdocs\www\Cargas\portal
php artisan db:seed --class=DemoSeeder
```

Crea seis envíos de ejemplo con archivos reales, hallazgos y una observación abierta.
Es idempotente: si lo ejecutas otra vez, reemplaza los anteriores y no duplica nada.

Detalle completo en [`00-como-funciona-el-sistema.md`](00-como-funciona-el-sistema.md), §8.

---

## 6. Detener el servidor

| Forma | Cómo |
|---|---|
| Apache | Panel de XAMPP → **Stop** en Apache. El portal deja de responder |
| `php artisan serve` | `Ctrl + C` en la consola |

MySQL conviene dejarlo encendido: si lo apagas, `/health` pasa a `"db":"error"` y las
pantallas fallan.

---

## 7. Cuando algo no abre

| Síntoma | Causa | Solución |
|---|---|---|
| La página no carga y Apache está apagado | Apache detenido | Start en el panel de XAMPP |
| `/health` responde `"db":"error"` | MySQL apagado, o `.env` con otra clave | Start MySQL; revisar `DB_*` en `portal/.env` |
| `php artisan serve` abre y a los segundos deja de responder | Se cerró la ventana de la consola | Volver a levantarlo y dejarla abierta |
| Apache no arranca: puerto 80 ocupado | Otro programa usa el puerto (IIS, Skype) | Detener el otro programa, o servir con `php artisan serve` |
| «Unknown database 'cargas_rem'» | La base no existe | `iniciar.bat` la crea; a mano es el paso 4 de [`como-probar.md`](../operacion/como-probar.md) |
| La primera página tarda ~10 segundos | Arranque en frío del intérprete y de la base | Normal. Las siguientes cargas son de menos de 1 segundo |
| `php -v` dice 7.4 | Se está usando el PHP de otro XAMPP | Usar `C:\xampp\php\php.exe`, que es 8.2.12 |

---

## 8. Comprobar que todo está sano

```bat
cd C:\xampp\htdocs\www\Cargas\portal
php vendor\bin\phpunit
```

Deben pasar **261 tests**. Usan su propia base (`cargas_rem_test`), así que no tocan los
datos de desarrollo.
