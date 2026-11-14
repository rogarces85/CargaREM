# Decisiones pendientes de firma

Decisiones de F0 que bloquean el cierre del proyecto. Cada una trae una
recomendación técnica con su justificación, la consecuencia de adoptarla y qué
cambiaría si se rechaza.

**D4 (plazo) quedó resuelta el 2026-11-14:** quinto día hábil del mes siguiente,
con calendario de feriados de Chile (D-029). Las que siguen abiertas son D1
(identidad), D5 (matriz de series) y D6 (SAX).

Están **adoptadas técnicamente** para no detener el desarrollo: el sistema ya se
comporta como se describe aquí. Falta la firma del referente para que dejen de
ser propuestas.

---

## D5 — Matriz de series obligatorias

**Recomendación:** todo establecimiento activo exige **las seis series del MVP**
(A, BS, BM, D, P, F) desde el primer período. Las excepciones se cargan una por
una, con motivo, desde la matriz de series del portal.

**Por qué.** Arrancar con «todo se exige» es el error que se corrige hacia
abajo: si falta una serie, se ve en la matriz y alguien pregunta. Arrancar con
«nada se exige» es el error que no se ve: los envíos se aprueban incompletos y
el faltante aparece recién en la consolidación, cuando ya no se puede pedir.

**Consecuencia.** El primer mes todos los establecimientos aparecen en rojo en
las series que no entreguen. Es ruido, pero es ruido verdadero.

**Estado en el sistema.** El seeder L1 crea la matriz completa para los 7
establecimientos de prueba. Los 86 del catálogo real se cargan con la misma
regla desde la pantalla de matriz.

**Si se rechaza:** hay que definir qué serie exige cada tipo de establecimiento
(hospital, posta, CESFAM) y cargarlo antes del primer período real.

---

## Severidad de los códigos

**Recomendación:** adoptar la severidad tal como está implementada, que sigue el
catálogo del Módulo M7 con dos excepciones documentadas.

| Severidad | Códigos |
|---|---|
| **Bloqueante** | ENV-001 a 013, 015, 019, 024, 025, 029, 030 |
| **Advertencia** | ENV-017, 018, 020, 021, 022, 023, 026, 027, 028, 031 |

**Las dos excepciones, y por qué.**

- **ENV-014** (versión de plantilla) es bloqueante cuando el catálogo tiene la
  plantilla del período. Si el catálogo **no** la tiene, baja a advertencia:
  bloquear por una carencia del catálogo dejaría el portal inservible el mes en
  que el Minsal publica tarde.
- **ENV-016** (hoja faltante) es bloqueante cuando la plantilla declara hojas.
  Si no las declara, advierte: no se puede exigir lo que no se sabe.

**La regla general:** se bloquea cuando se sabe que está mal; se advierte cuando
no se puede saber. Fingir certeza con un bloqueo es tan malo como dejar pasar un
error.

**Si se rechaza:** hay que indicar, código por código, cuál cambia y con qué
criterio.

---

## D4 — Plazos y reglas de cierre — RESUELTA (D-029)

**Decisión (14-11-2026):**

| Regla | Valor |
|---|---|
| Plazo de entrega | **quinto día hábil del mes siguiente**, a las 23:59:59 de `America/Santiago` |
| Días no hábiles | sábados, domingos y feriados de la tabla `feriados` (nacionales; 16 semilla de 2026, Ley 19.973) |
| Cierre del período | **manual**, por el administrador |
| Envíos atrasados | se aceptan igual, marcados «fuera de plazo» |
| Excepción | el administrador puede fijar una fecha manual por período; el sistema la respeta y la marca como tal |

**Por qué manual y no automático.** Un cierre automático el día del plazo deja
afuera a quien estaba cargando a las 23:59, y obliga a corregir el período. Con
cierre manual, el administrador cierra cuando el Servicio ya no espera nada y la
decisión queda registrada con su nombre.

**Por qué se aceptan atrasados.** El objetivo del portal es que el dato llegue,
no castigar. Un envío fuera de plazo es información útil para la jefatura; un
envío rechazado es un dato que no existe.

**Estado en el sistema.** Implementado: `CalendarioService`, columna
`periodos.fecha_limite_manual`, tabla `feriados`, pantalla **Calendario de
plazos** y comando `php artisan rem:calendario <año> [--actualizar]`. Reglas
completas en [`../validacion/reglas-cierre-periodo.md`](../validacion/reglas-cierre-periodo.md).

**Pendiente de negocio:** confirmar si hay excepciones por convenio (se cubren
con la fecha manual) y si se cargan feriados regionales de Los Lagos.

---

## D1 y D10 — Identidad y segundo factor

**Recomendación para v2.0:**

| Rol | Cuenta | 2FA |
|---|---|---|
| Administrador | local, creada por otro administrador | **fuera de v2.0** (v2.1) |
| Revisor | local, creada por el administrador | **fuera de v2.0** (v2.1) |
| Encargado | local, creada por el administrador | fuera de v2.0 |

**Por qué local y no directorio institucional.** El servidor no tiene LDAP ni
SSO confirmado, y esperar esa integración deja el portal sin usuarios. Las
cuentas locales son reversibles: cuando exista directorio, se migra.

**Por qué el 2FA queda para v2.1.** El documento vigente lo deja fuera del corte
(§4). La recomendación de exigirlo por rol sigue en pie para cuando vuelva:
un administrador comprometido afecta a todo el Servicio.

**Estado en el sistema.** Fortify cubre login, logout y recuperación de
contraseña. El 2FA está retirado de la configuración; volver a activarlo es una
decisión aparte (v2.1), no una deuda oculta.

**Si se rechaza:** si TI confirma que hay directorio, cambia el origen de las
cuentas pero no el resto del modelo.
